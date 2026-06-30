<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BulkPaymentStatus;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BulkPayment;
use App\Models\BulkPaymentApplication;
use App\Models\Customer;
use App\Models\InvoiceIn;
use App\Models\InvoiceOut;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ BulkPayment (gom đơn thanh toán).
 *
 * Use case: Khách hàng "Công ty A" có 5 đơn hàng, trả 1 lần cho cả 5.
 *
 * Workflow:
 *   1. create(): tạo BulkPayment (PENDING) + N BulkPaymentApplication (chưa trừ tiền).
 *   2. process(): tạo 1 Payment + N PaymentApplication → trừ tiền thật.
 *   3. cancel(): huỷ phiếu (nếu chưa process).
 */
class BulkPaymentService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Tạo BulkPayment với danh sách Invoice cần gom.
     *
     * @param  array<int, array{invoice_out_id?: int, invoice_in_id?: int, amount_applied: float|int|string, notes?: string|null}>  $items
     * @param  array{
     *     payment_date?: string|null,
     *     bank_account_id?: int|null,
     *     reference?: string|null,
     *     description?: string|null,
     *     notes?: string|null,
     *     payment_method?: PaymentMethod|string,
     * }  $meta
     */
    public function create(
        PartyType $partyType,
        Customer|Supplier|null $party,
        PaymentMethod $method,
        array $items,
        User $actor,
        array $meta = [],
    ): BulkPayment {
        if ($partyType === PartyType::CUSTOMER && ! $party instanceof Customer) {
            throw ValidationException::withMessages(['party' => 'CUSTOMER yêu cầu Customer.']);
        }
        if ($partyType === PartyType::SUPPLIER && ! $party instanceof Supplier) {
            throw ValidationException::withMessages(['party' => 'SUPPLIER yêu cầu Supplier.']);
        }
        if (count($items) === 0) {
            throw ValidationException::withMessages(['items' => 'BulkPayment phải có ít nhất 1 invoice.']);
        }

        return DB::transaction(function () use ($partyType, $party, $method, $items, $actor, $meta) {
            $total = '0';
            foreach ($items as $item) {
                $amount = (string) $item['amount_applied'];
                if (bccomp($amount, '0', 2) <= 0) {
                    throw ValidationException::withMessages(['amount_applied' => 'Mỗi dòng phải có số tiền > 0.']);
                }
                $total = bcadd($total, $amount, 2);
            }

            $paymentMethod = $method instanceof PaymentMethod ? $method->value : $method;
            $partyId = $party?->id ?? 0;

            $bulk = BulkPayment::create([
                'bulk_number' => $this->orderNumber->nextBulkPaymentNumber(),
                'party_type' => $partyType->value,
                'party_id' => $partyId,
                'customer_id' => $partyType === PartyType::CUSTOMER ? $partyId : null,
                'supplier_id' => $partyType === PartyType::SUPPLIER ? $partyId : null,
                'total_amount' => $total,
                'payment_method' => $paymentMethod,
                'bank_account_id' => $meta['bank_account_id'] ?? null,
                'payment_date' => $meta['payment_date'] ?? now()->toDateString(),
                'reference' => $meta['reference'] ?? null,
                'description' => $meta['description'] ?? null,
                'status' => BulkPaymentStatus::PENDING->value,
                'notes' => $meta['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($items as $item) {
                $invoiceOutId = $item['invoice_out_id'] ?? null;
                $invoiceInId = $item['invoice_in_id'] ?? null;

                // Validate invoice thuộc về party
                if ($invoiceOutId) {
                    $inv = InvoiceOut::findOrFail($invoiceOutId);
                    if ((int) $inv->customer_id !== (int) $partyId) {
                        throw ValidationException::withMessages(['items' => "InvoiceOut #{$inv->invoice_number} không thuộc khách hàng."]);
                    }
                    if (bccomp((string) $item['amount_applied'], (string) $inv->balance_due, 2) > 0) {
                        throw ValidationException::withMessages(['items' => "InvoiceOut #{$inv->invoice_number}: số tiền vượt balance_due."]);
                    }
                }
                if ($invoiceInId) {
                    $inv = InvoiceIn::findOrFail($invoiceInId);
                    if ((int) $inv->supplier_id !== (int) $partyId) {
                        throw ValidationException::withMessages(['items' => "InvoiceIn #{$inv->invoice_number} không thuộc NCC."]);
                    }
                    if (bccomp((string) $item['amount_applied'], (string) $inv->balance_due, 2) > 0) {
                        throw ValidationException::withMessages(['items' => "InvoiceIn #{$inv->invoice_number}: số tiền vượt balance_due."]);
                    }
                }

                BulkPaymentApplication::create([
                    'bulk_payment_id' => $bulk->id,
                    'invoice_out_id' => $invoiceOutId,
                    'invoice_in_id' => $invoiceInId,
                    'amount_applied' => (string) $item['amount_applied'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $bulk->fresh(['applications']);
        });
    }

    /**
     * Xử lý BulkPayment → tạo Payment + PaymentApplication (cập nhật AR/AP).
     */
    public function process(BulkPayment $bulk, User $actor): Payment
    {
        if ($bulk->status !== BulkPaymentStatus::PENDING) {
            throw ValidationException::withMessages(['status' => 'Chỉ xử lý được BulkPayment đang ở trạng thái PENDING.']);
        }

        return DB::transaction(function () use ($bulk, $actor) {
            $bulk->status = BulkPaymentStatus::PROCESSING;
            $bulk->save();

            $partyType = is_string($bulk->party_type) ? PartyType::from($bulk->party_type) : $bulk->party_type;
            $method = is_string($bulk->payment_method) ? PaymentMethod::from($bulk->payment_method) : $bulk->payment_method;
            $party = $partyType === PartyType::CUSTOMER
                ? Customer::find($bulk->party_id)
                : Supplier::find($bulk->party_id);

            // Tạo 1 Payment tổng
            $payment = $this->paymentService->record(
                partyType: $partyType,
                party: $party,
                method: $method,
                amount: $bulk->total_amount,
                actor: $actor,
                meta: [
                    'payment_date' => $bulk->payment_date,
                    'bank_account_id' => $bulk->bank_account_id,
                    'reference' => $bulk->reference,
                    'notes' => 'Auto-created from BulkPayment ' . $bulk->bulk_number,
                ],
            );

            // Gắn bulk_payment_id
            $payment->bulk_payment_id = $bulk->id;
            $payment->save();

            // Apply cho từng invoice
            foreach ($bulk->applications as $app) {
                if ($app->invoice_out_id) {
                    $this->paymentService->applyToInvoiceOut(
                        $payment,
                        $app->invoiceOut,
                        $app->amount_applied,
                    );
                } elseif ($app->invoice_in_id) {
                    $this->paymentService->applyToInvoiceIn(
                        $payment,
                        $app->invoiceIn,
                        $app->amount_applied,
                    );
                }
            }

            $bulk->status = BulkPaymentStatus::COMPLETED;
            $bulk->save();

            return $payment->fresh();
        });
    }

    public function cancel(BulkPayment $bulk, User $actor, ?string $reason = null): BulkPayment
    {
        if ($bulk->status === BulkPaymentStatus::COMPLETED) {
            throw ValidationException::withMessages(['status' => 'Không thể huỷ BulkPayment đã hoàn tất.']);
        }
        $bulk->status = BulkPaymentStatus::FAILED;
        $bulk->notes = ($bulk->notes ?? '') . "\n[FAILED " . now()->toDateTimeString() . "] " . ($reason ?? '');
        $bulk->save();

        return $bulk->fresh();
    }
}