<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\InvoiceIn;
use App\Models\PaymentApplication;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ hóa đơn mua (InvoiceIn - AP).
 *
 * Đối xứng với InvoiceOutService.
 */
class InvoiceInService
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
     *     notes?: string|null,
     * }  $meta
     */
    public function createFromPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        User $actor,
        array $meta = [],
    ): InvoiceIn {
        if ($purchaseOrder->invoice_in_id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Đơn mua này đã có hóa đơn (#' . $purchaseOrder->invoice_in_id . ').',
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder, $actor, $meta) {
            $subtotal = (string) ($meta['subtotal'] ?? $purchaseOrder->subtotal ?? '0');
            $discountAmount = (string) ($meta['discount_amount'] ?? $purchaseOrder->discount_amount ?? '0');
            $taxAmount = (string) ($meta['tax_amount'] ?? $purchaseOrder->tax_amount ?? '0');
            $total = (string) ($meta['total'] ?? bcadd(bcsub($subtotal, $discountAmount, 2), $taxAmount, 2));

            $invoiceDate = $meta['invoice_date'] ?? now()->toDateString();
            $paymentTermDays = (int) ($purchaseOrder->supplier?->payment_term_days ?? 0);
            $dueDate = $meta['due_date'] ?? now()->addDays($paymentTermDays)->toDateString();

            $invoice = InvoiceIn::create([
                'invoice_number' => $this->orderNumber->nextInvoiceInNumber(),
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'paid_amount' => '0',
                'balance_due' => $total,
                'currency' => $meta['currency'] ?? $purchaseOrder->currency ?? 'VND',
                'exchange_rate' => (string) ($meta['exchange_rate'] ?? $purchaseOrder->exchange_rate ?? '1'),
                'tax_rate' => (string) ($meta['tax_rate'] ?? '10.00'),
                'status' => InvoiceStatus::DRAFT->value,
                'notes' => $meta['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $purchaseOrder->invoice_in_id = $invoice->id;
            $purchaseOrder->save();

            return $invoice->fresh();
        });
    }

    /**
     * Phát hành hóa đơn mua (DRAFT → ISSUED).
     */
    public function issue(InvoiceIn $invoice, User $actor): InvoiceIn
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

        if ($this->journalTemplates) {
            try {
                $this->journalTemplates->postInvoiceInIssued($invoice->fresh(), $actor);
            } catch (\Illuminate\Validation\ValidationException $e) {
                \Log::warning('Auto-posting journal failed for InvoiceIn ' . $invoice->invoice_number, ['errors' => $e->errors()]);
            }
        }

        return $invoice->fresh();
    }

    /**
     * Hủy hóa đơn mua.
     */
    public function cancel(InvoiceIn $invoice, User $actor, ?string $reason = null): InvoiceIn
    {
        if (bccomp((string) $invoice->paid_amount, '0', 2) > 0) {
            throw ValidationException::withMessages([
                'status' => 'Không thể hủy hóa đơn đã có thanh toán. Hãy tạo Credit Note.',
            ]);
        }

        $invoice->status = InvoiceStatus::CANCELLED->value;
        $invoice->notes = ($invoice->notes ?? '') . "\n[HUỶ " . now()->toDateTimeString() . "] " . ($reason ?? '');
        $invoice->save();

        if ($invoice->purchaseOrder) {
            $invoice->purchaseOrder->invoice_in_id = null;
            $invoice->purchaseOrder->save();
        }

        return $invoice->fresh();
    }

    public function markAsCredited(InvoiceIn $invoice, User $actor): InvoiceIn
    {
        $invoice->status = InvoiceStatus::CREDITED->value;
        $invoice->save();

        return $invoice->fresh();
    }

    public function refreshTotals(InvoiceIn $invoice): InvoiceIn
    {
        return DB::transaction(function () use ($invoice) {
            $paid = (string) PaymentApplication::query()
                ->where('invoice_in_id', $invoice->id)
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