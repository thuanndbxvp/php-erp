<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\InvoiceOut;
use App\Models\PaymentApplication;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ hóa đơn bán (InvoiceOut - AR).
 *
 * Chức năng:
 *  - Tạo hóa đơn từ đơn bán (SalesOrder): tự tính subtotal/discount/tax/total.
 *  - Phát hành (DRAFT → ISSUED).
 *  - Hủy hóa đơn (CANCELLED), cho phép tạo credit note (CREDITED).
 *  - Cập nhật paid_amount khi PaymentApplication thay đổi (được PaymentService gọi).
 */
class InvoiceOutService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
        private readonly JournalTemplates $journalTemplates,
    ) {}

    /**
     * @param  array{
     *     invoice_date?: string|null,
     *     due_date?: string|null,
     *     subtotal?: float|int|string,
     *     discount_amount?: float|int|string,
     *     tax_amount?: float|int|string,
     *     tax_rate?: float|int|string,
     *     total?: float|int|string|null,
     *     currency?: string,
     *     exchange_rate?: float|int|string,
     *     invoice_type?: InvoiceType|string,
     *     notes?: string|null,
     * }  $meta
     */
    public function createFromSalesOrder(
        SalesOrder $salesOrder,
        User $actor,
        array $meta = [],
    ): InvoiceOut {
        if ($salesOrder->invoice_out_id) {
            throw ValidationException::withMessages([
                'sales_order_id' => 'Đơn bán này đã có hóa đơn (#' . $salesOrder->invoice_out_id . ').',
            ]);
        }

        return DB::transaction(function () use ($salesOrder, $actor, $meta) {
            $subtotal = (string) ($meta['subtotal'] ?? $salesOrder->subtotal ?? '0');
            $discountAmount = (string) ($meta['discount_amount'] ?? $salesOrder->discount_amount ?? '0');
            $taxAmount = (string) ($meta['tax_amount'] ?? $salesOrder->tax_amount ?? '0');
            $total = (string) ($meta['total'] ?? bcadd(bcsub($subtotal, $discountAmount, 2), $taxAmount, 2));

            $invoiceDate = $meta['invoice_date'] ?? now()->toDateString();
            $paymentTermDays = (int) ($salesOrder->customer?->payment_term_days ?? 0);
            $dueDate = $meta['due_date'] ?? now()->addDays($paymentTermDays)->toDateString();

            $invoiceType = $meta['invoice_type'] ?? InvoiceType::DOMESTIC;

            $invoice = InvoiceOut::create([
                'invoice_number' => $this->orderNumber->nextInvoiceOutNumber(),
                'sales_order_id' => $salesOrder->id,
                'customer_id' => $salesOrder->customer_id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'paid_amount' => '0',
                'balance_due' => $total,
                'currency' => $meta['currency'] ?? $salesOrder->currency ?? 'VND',
                'exchange_rate' => (string) ($meta['exchange_rate'] ?? $salesOrder->exchange_rate ?? '1'),
                'tax_rate' => (string) ($meta['tax_rate'] ?? '10.00'),
                'invoice_type' => $invoiceType instanceof InvoiceType ? $invoiceType->value : $invoiceType,
                'status' => InvoiceStatus::DRAFT->value,
                'notes' => $meta['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Gắn FK ngược từ SalesOrder
            $salesOrder->invoice_out_id = $invoice->id;
            $salesOrder->save();

            return $invoice->fresh();
        });
    }

    /**
     * Phát hành hóa đơn (DRAFT → ISSUED).
     */
    public function issue(InvoiceOut $invoice, User $actor): InvoiceOut
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ phát hành được hóa đơn đang ở trạng thái DRAFT.',
            ]);
        }

        if (bccomp((string) $invoice->total, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'total' => 'Tổng hóa đơn phải lớn hơn 0 trước khi phát hành.',
            ]);
        }

        $invoice->status = InvoiceStatus::ISSUED->value;
        $invoice->save();

        // Auto ghi bút toán doanh thu
        if ($this->journalTemplates) {
            try {
                $this->journalTemplates->postInvoiceOutIssued($invoice->fresh(), $actor);
            } catch (\Illuminate\Validation\ValidationException $e) {
                \Log::warning('Auto-posting journal failed for InvoiceOut ' . $invoice->invoice_number, ['errors' => $e->errors()]);
            }
        }

        return $invoice->fresh();
    }

    /**
     * Hủy hóa đơn (chỉ cho phép khi chưa thanh toán).
     */
    public function cancel(InvoiceOut $invoice, User $actor, ?string $reason = null): InvoiceOut
    {
        if (bccomp((string) $invoice->paid_amount, '0', 2) > 0) {
            throw ValidationException::withMessages([
                'status' => 'Không thể hủy hóa đơn đã có thanh toán (paid_amount > 0). Hãy tạo Credit Note.',
            ]);
        }

        $invoice->status = InvoiceStatus::CANCELLED->value;
        $invoice->notes = ($invoice->notes ?? '') . "\n[HUỶ " . now()->toDateTimeString() . "] " . ($reason ?? '');
        $invoice->save();

        // Gỡ FK ngược từ SalesOrder để cho phép tạo lại invoice khác nếu cần
        if ($invoice->salesOrder) {
            $invoice->salesOrder->invoice_out_id = null;
            $invoice->salesOrder->save();
        }

        return $invoice->fresh();
    }

    /**
     * Đánh dấu Credit Note (áp dụng cho invoice đã hủy/bù trừ).
     */
    public function markAsCredited(InvoiceOut $invoice, User $actor): InvoiceOut
    {
        $invoice->status = InvoiceStatus::CREDITED->value;
        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * Cập nhật lại paid_amount/balance_due/status từ các PaymentApplication.
     *
     * Được PaymentService gọi sau khi tạo/xóa application.
     * Dùng DB transaction để tránh race.
     */
    public function refreshTotals(InvoiceOut $invoice): InvoiceOut
    {
        return DB::transaction(function () use ($invoice) {
            $paid = (string) PaymentApplication::query()
                ->where('invoice_out_id', $invoice->id)
                ->sum('amount_applied');

            $balance = bcsub((string) $invoice->total, $paid, 2);

            $invoice->paid_amount = $paid;
            $invoice->balance_due = $balance;
            $invoice->status = InvoiceStatus::resolve(
                total: (float) $invoice->total,
                paid: (float) $paid,
                dueDate: $invoice->due_date,
                isDraft: false,
            )->value;
            $invoice->save();

            return $invoice->fresh();
        });
    }
}