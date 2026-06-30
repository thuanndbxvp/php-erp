<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BankAccountType;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BankAccount;
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
 * Service nghiệp vụ Payment - Dòng tiền (thu/chi).
 *
 * - record(): tạo phiếu thanh toán (chưa gắn invoice).
 * - applyToInvoiceOut(): gắn Payment với InvoiceOut (AR - khách hàng).
 * - applyToInvoiceIn(): gắn Payment với InvoiceIn (AP - NCC).
 * - unapply(): gỡ một application (dùng khi sai sót).
 * - markFailed/refund/cancel: cập nhật trạng thái.
 *
 * Đảm bảo:
 *   - amount_applied ≤ invoice.balance_due (chưa trả quá)
 *   - SUM(amount_applied) ≤ payment.amount (chưa dùng quá số tiền payment)
 *   - payment.party_type khớp với loại invoice đang apply
 *   - Khi một invoice PAID → status được re-compute tự động.
 */
class PaymentService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
        private readonly InvoiceOutService $invoiceOutService,
        private readonly InvoiceInService $invoiceInService,
        private readonly JournalTemplates $journalTemplates,
    ) {}

    /**
     * @param  array{
     *     payment_date?: string|null,
     *     bank_account_id?: int|null,
     *     reference?: string|null,
     *     notes?: string|null,
     *     status?: PaymentStatus|string,
     *     exchange_rate?: float|int|string,
     *     currency?: string,
     * }  $meta
     */
    public function record(
        PartyType $partyType,
        Customer|Supplier|null $party,
        PaymentMethod $method,
        string|float $amount,
        User $actor,
        array $meta = [],
    ): Payment {
        $amount = (string) $amount;

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền thanh toán phải lớn hơn 0.',
            ]);
        }

        if ($partyType === PartyType::CUSTOMER && ! $party instanceof Customer) {
            throw ValidationException::withMessages(['party' => 'party_type = CUSTOMER yêu cầu Customer object.']);
        }
        if ($partyType === PartyType::SUPPLIER && ! $party instanceof Supplier) {
            throw ValidationException::withMessages(['party' => 'party_type = SUPPLIER yêu cầu Supplier object.']);
        }

        $partyId = $party?->id ?? 0;

        return DB::transaction(function () use ($partyType, $partyId, $method, $amount, $actor, $meta, $party) {
            $status = $meta['status'] ?? PaymentStatus::PENDING;
            if ($status instanceof PaymentStatus) {
                $status = $status->value;
            }

            $payment = Payment::create([
                'payment_number' => $this->orderNumber->nextPaymentNumber(),
                'party_type' => $partyType->value,
                'party_id' => $partyId,
                'customer_id' => $partyType === PartyType::CUSTOMER ? $partyId : null,
                'supplier_id' => $partyType === PartyType::SUPPLIER ? $partyId : null,
                'payment_method' => $method->value,
                'amount' => $amount,
                'applied_amount' => '0',
                'remaining_amount' => $amount,
                'currency' => $meta['currency'] ?? 'VND',
                'exchange_rate' => (string) ($meta['exchange_rate'] ?? '1'),
                'payment_date' => $meta['payment_date'] ?? now()->toDateString(),
                'bank_account_id' => $meta['bank_account_id'] ?? null,
                'reference' => $meta['reference'] ?? null,
                'status' => $status,
                'notes' => $meta['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Auto ghi sổ kép nếu được bật (mặc định: bật)
            $this->postLedgerEntryForPayment($payment->fresh(), $actor);

            return $payment->fresh();
        });
    }

    /**
     * Ghi bút toán kép cho payment - throttled khi accounting period chưa mở.
     */
    private function postLedgerEntryForPayment(\App\Models\Payment $payment, User $actor): void
    {
        try {
            if ($payment->party_type === PartyType::CUSTOMER) {
                $this->journalTemplates->postPaymentReceived($payment, $actor);
            } else {
                $this->journalTemplates->postPaymentPaid($payment, $actor);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Không chặn payment nếu posting fail (vd: chưa có kỳ OPEN, chưa seed COA)
            \Log::warning('Auto-posting journal failed for payment ' . $payment->payment_number, [
                'errors' => $e->errors(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Auto-posting journal error for payment ' . $payment->payment_number, [
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Áp dụng payment cho một InvoiceOut (AR).
     *
     * @throws ValidationException
     */
    public function applyToInvoiceOut(Payment $payment, InvoiceOut $invoice, string|float $amount): PaymentApplication
    {
        if ($payment->party_type !== PartyType::CUSTOMER) {
            throw ValidationException::withMessages(['payment' => 'Chỉ áp dụng Payment CUSTOMER cho InvoiceOut.']);
        }
        if ((int) $invoice->customer_id !== (int) $payment->party_id) {
            throw ValidationException::withMessages(['invoice' => 'InvoiceOut không thuộc khách hàng của Payment.']);
        }

        return DB::transaction(function () use ($payment, $invoice, $amount) {
            $amount = (string) $amount;
            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(['amount_applied' => 'Số tiền áp dụng phải > 0.']);
            }

            $remainingPayment = bcsub((string) $payment->amount, (string) $payment->applied_amount, 2);
            $remainingInvoice = bcsub((string) $invoice->total, (string) $invoice->paid_amount, 2);

            if (bccomp($amount, $remainingPayment, 2) > 0) {
                throw ValidationException::withMessages(['amount_applied' => "Số tiền vượt quá phần còn dư của Payment ({$remainingPayment})."]);
            }
            if (bccomp($amount, $remainingInvoice, 2) > 0) {
                throw ValidationException::withMessages(['amount_applied' => "Số tiền vượt quá phần còn nợ của InvoiceOut ({$remainingInvoice})."]);
            }

            $app = PaymentApplication::create([
                'payment_id' => $payment->id,
                'invoice_out_id' => $invoice->id,
                'invoice_in_id' => null,
                'amount_applied' => $amount,
                'applied_at' => now(),
                'notes' => null,
            ]);

            $payment->recomputeApplied()->save();
            $this->invoiceOutService->refreshTotals($invoice->fresh());

            // Nếu hết dư → chuyển trạng thái Payment sang APPLIED
            if (bccomp((string) $payment->remaining_amount, '0', 2) <= 0 && $payment->status === PaymentStatus::PENDING) {
                $payment->status = PaymentStatus::APPLIED;
                $payment->save();
            }

            return $app->fresh();
        });
    }

    /**
     * Áp dụng payment cho một InvoiceIn (AP).
     */
    public function applyToInvoiceIn(Payment $payment, InvoiceIn $invoice, string|float $amount): PaymentApplication
    {
        if ($payment->party_type !== PartyType::SUPPLIER) {
            throw ValidationException::withMessages(['payment' => 'Chỉ áp dụng Payment SUPPLIER cho InvoiceIn.']);
        }
        if ((int) $invoice->supplier_id !== (int) $payment->party_id) {
            throw ValidationException::withMessages(['invoice' => 'InvoiceIn không thuộc NCC của Payment.']);
        }

        return DB::transaction(function () use ($payment, $invoice, $amount) {
            $amount = (string) $amount;
            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(['amount_applied' => 'Số tiền áp dụng phải > 0.']);
            }

            $remainingPayment = bcsub((string) $payment->amount, (string) $payment->applied_amount, 2);
            $remainingInvoice = bcsub((string) $invoice->total, (string) $invoice->paid_amount, 2);

            if (bccomp($amount, $remainingPayment, 2) > 0) {
                throw ValidationException::withMessages(['amount_applied' => "Số tiền vượt quá phần còn dư của Payment ({$remainingPayment})."]);
            }
            if (bccomp($amount, $remainingInvoice, 2) > 0) {
                throw ValidationException::withMessages(['amount_applied' => "Số tiền vượt quá phần còn nợ của InvoiceIn ({$remainingInvoice})."]);
            }

            $app = PaymentApplication::create([
                'payment_id' => $payment->id,
                'invoice_out_id' => null,
                'invoice_in_id' => $invoice->id,
                'amount_applied' => $amount,
                'applied_at' => now(),
                'notes' => null,
            ]);

            $payment->recomputeApplied()->save();
            $this->invoiceInService->refreshTotals($invoice->fresh());

            if (bccomp((string) $payment->remaining_amount, '0', 2) <= 0 && $payment->status === PaymentStatus::PENDING) {
                $payment->status = PaymentStatus::APPLIED;
                $payment->save();
            }

            return $app->fresh();
        });
    }

    /**
     * Gỡ một application (dùng khi sai sót).
     */
    public function unapply(PaymentApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $payment = $application->payment;
            $invoice = $application->invoice_out_id
                ? $application->invoiceOut
                : $application->invoiceIn;

            $application->delete();

            $payment->recomputeApplied()->save();
            // Sau khi gỡ, nếu còn dư → trả về PENDING
            if (bccomp((string) $payment->remaining_amount, '0', 2) > 0
                && $payment->status === PaymentStatus::APPLIED) {
                $payment->status = PaymentStatus::PENDING;
                $payment->save();
            }

            if ($application->invoice_out_id && $invoice) {
                $this->invoiceOutService->refreshTotals($invoice);
            } elseif ($application->invoice_in_id && $invoice) {
                $this->invoiceInService->refreshTotals($invoice);
            }
        });
    }

    public function markFailed(Payment $payment, User $actor, ?string $reason = null): Payment
    {
        $payment->status = PaymentStatus::FAILED->value;
        $payment->notes = ($payment->notes ?? '') . "\n[FAILED " . now()->toDateTimeString() . "] " . ($reason ?? '');
        $payment->save();

        return $payment->fresh();
    }

    public function refund(Payment $payment, User $actor, ?string $reason = null): Payment
    {
        $payment->status = PaymentStatus::REFUNDED->value;
        $payment->notes = ($payment->notes ?? '') . "\n[REFUNDED " . now()->toDateTimeString() . "] " . ($reason ?? '');
        $payment->save();

        return $payment->fresh();
    }

    public function cancel(Payment $payment, User $actor, ?string $reason = null): Payment
    {
        if (bccomp((string) $payment->applied_amount, '0', 2) > 0) {
            throw ValidationException::withMessages(['status' => 'Không thể huỷ Payment đã áp dụng cho invoice. Gỡ application trước.']);
        }
        $payment->status = PaymentStatus::CANCELLED->value;
        $payment->notes = ($payment->notes ?? '') . "\n[CANCELLED " . now()->toDateTimeString() . "] " . ($reason ?? '');
        $payment->save();

        return $payment->fresh();
    }
}