<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryDC;
use App\Enums\JournalType;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\InvoiceIn;
use App\Models\InvoiceOut;
use App\Models\Payment;
use App\Models\User;

/**
 * Helper sinh bút toán kế toán chuẩn từ các nghiệp vụ (Payment, Invoice).
 *
 * Tập trung mọi logic bút toán tại đây để dễ bảo trì và test.
 *
 * Mapping tài khoản chuẩn (TT200):
 *  - Tiền mặt/Ví:           1111
 *  - Tiền gửi NH:            1121
 *  - TK trung gian sàn:      1128
 *  - Phải thu KH:            131
 *  - Trả trước cho NCC:      141
 *  - Phải trả NCC:           331
 *  - Người mua trả tiền trước: 1311
 *  - Doanh thu bán hàng:     5111
 *  - Doanh thu nội bộ:       5112
 *  - Thuế GTGT đầu ra:       33311
 *  - Thuế GTGT đầu vào:      1331
 *  - Giá vốn hàng bán:       632
 *  - Chi phí quản lý:        642
 */
class JournalTemplates
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly ChartOfAccountRepository $coa,
    ) {}

    /**
     * Bút toán ghi nhận doanh thu khi phát hành InvoiceOut (AR).
     *
     *   Nợ  131  Phải thu KH      = total
     *   Có  5111 Doanh thu bán    = subtotal - discount
     *   Có  33311 Thuế GTGT đầu ra = tax_amount
     */
    public function postInvoiceOutIssued(InvoiceOut $invoice, User $actor): void
    {
        $total = (string) $invoice->total;
        if (bccomp($total, '0', 2) <= 0) {
            return;
        }

        $arAccount = $this->coa->idByCode('131');
        $revenueAccount = $this->coa->idByCode('5111');
        $taxAccount = $this->coa->idByCode('33311');

        $netRevenue = bcsub((string) $invoice->total, (string) $invoice->tax_amount, 2);

        $this->journalService->post([
            'type' => JournalType::JOURNAL,
            'entry_date' => $invoice->invoice_date->format('Y-m-d'),
            'description' => "Phát hành HĐ bán {$invoice->invoice_number} cho KH #{$invoice->customer_id}",
            'ref_type' => 'INVOICE_OUT',
            'ref_id' => $invoice->id,
            'lines' => [
                ['account_id' => $arAccount, 'dc' => EntryDC::DEBIT, 'amount' => $total, 'description' => 'Phải thu KH', 'party_type' => 'CUSTOMER', 'party_id' => $invoice->customer_id],
                ['account_id' => $revenueAccount, 'dc' => EntryDC::CREDIT, 'amount' => $netRevenue, 'description' => 'Doanh thu bán hàng'],
                ['account_id' => $taxAccount, 'dc' => EntryDC::CREDIT, 'amount' => (string) $invoice->tax_amount, 'description' => 'Thuế GTGT đầu ra'],
            ],
        ], $actor, autoPost: true);
    }

    /**
     * Bút toán ghi nhận chi phí/mua hàng khi nhập InvoiceIn (AP).
     *
     *   Nợ  632   Giá vốn / HH    = subtotal - discount
     *   Nợ  1331  Thuế GTGT đầu vào = tax_amount
     *   Có  331   Phải trả NCC     = total
     */
    public function postInvoiceInIssued(InvoiceIn $invoice, User $actor): void
    {
        $total = (string) $invoice->total;
        if (bccomp($total, '0', 2) <= 0) {
            return;
        }

        $cogsAccount = $this->coa->idByCode('632');
        $taxInputAccount = $this->coa->idByCode('1331');
        $apAccount = $this->coa->idByCode('331');

        $netCost = bcsub((string) $invoice->total, (string) $invoice->tax_amount, 2);

        $this->journalService->post([
            'type' => JournalType::JOURNAL,
            'entry_date' => $invoice->invoice_date->format('Y-m-d'),
            'description' => "Nhập HĐ mua {$invoice->invoice_number} từ NCC #{$invoice->supplier_id}",
            'ref_type' => 'INVOICE_IN',
            'ref_id' => $invoice->id,
            'lines' => [
                ['account_id' => $cogsAccount, 'dc' => EntryDC::DEBIT, 'amount' => $netCost, 'description' => 'Giá vốn hàng bán'],
                ['account_id' => $taxInputAccount, 'dc' => EntryDC::DEBIT, 'amount' => (string) $invoice->tax_amount, 'description' => 'Thuế GTGT đầu vào'],
                ['account_id' => $apAccount, 'dc' => EntryDC::CREDIT, 'amount' => $total, 'description' => 'Phải trả NCC', 'party_type' => 'SUPPLIER', 'party_id' => $invoice->supplier_id],
            ],
        ], $actor, autoPost: true);
    }

    /**
     * Bút toán thu tiền (Customer → Tiền/NH).
     *
     *   Nợ  1111/1121/1128 Tiền    = amount
     *   Có  131             Phải thu KH = amount
     */
    public function postPaymentReceived(Payment $payment, User $actor): void
    {
        $this->postPaymentInternal($payment, $actor, 'Thu tiền từ KH');
    }

    /**
     * Bút toán chi tiền (Tiền/NH → Supplier).
     *
     *   Nợ  331             Phải trả NCC = amount
     *   Có  1111/1121/1128 Tiền         = amount
     */
    public function postPaymentPaid(Payment $payment, User $actor): void
    {
        $this->postPaymentInternal($payment, $actor, 'Chi tiền trả NCC');
    }

    private function postPaymentInternal(Payment $payment, User $actor, string $defaultDescription): void
    {
        if (bccomp((string) $payment->amount, '0', 2) <= 0) {
            return;
        }

        $amount = (string) $payment->amount;
        $cashAccountId = $this->resolveCashAccountId($payment);
        $isReceive = $payment->party_type === PartyType::CUSTOMER;

        if ($isReceive) {
            $arOrApAccountId = $this->coa->idByCode('131');
            $lines = [
                ['account_id' => $cashAccountId, 'dc' => EntryDC::DEBIT, 'amount' => $amount, 'description' => 'Tiền thu vào'],
                ['account_id' => $arOrApAccountId, 'dc' => EntryDC::CREDIT, 'amount' => $amount, 'description' => 'Giảm phải thu KH', 'party_type' => 'CUSTOMER', 'party_id' => $payment->party_id],
            ];
        } else {
            $arOrApAccountId = $this->coa->idByCode('331');
            $lines = [
                ['account_id' => $arOrApAccountId, 'dc' => EntryDC::DEBIT, 'amount' => $amount, 'description' => 'Giảm phải trả NCC', 'party_type' => 'SUPPLIER', 'party_id' => $payment->party_id],
                ['account_id' => $cashAccountId, 'dc' => EntryDC::CREDIT, 'amount' => $amount, 'description' => 'Tiền chi ra'],
            ];
        }

        $this->journalService->post([
            'type' => $isReceive ? JournalType::PAYMENT_IN : JournalType::PAYMENT_OUT,
            'entry_date' => $payment->payment_date->format('Y-m-d'),
            'description' => $payment->notes ?: ($defaultDescription . ' - ' . $payment->payment_number),
            'ref_type' => 'PAYMENT',
            'ref_id' => $payment->id,
            'lines' => $lines,
        ], $actor, autoPost: true);
    }

    /**
     * Xác định tài khoản tiền (1111/1121/1128) dựa trên BankAccount.account_type.
     */
    private function resolveCashAccountId(Payment $payment): int
    {
        $bank = $payment->bankAccount;
        if (! $bank) {
            // Không có tài khoản chỉ định → mặc định 1111 (tiền mặt)
            return $this->coa->idByCode('1111');
        }

        return match ($bank->account_type->value) {
            \App\Enums\BankAccountType::CHECKING->value, \App\Enums\BankAccountType::SAVINGS->value => $this->coa->idByCode('1121'),
            \App\Enums\BankAccountType::PLATFORM_CLEARING->value => $this->coa->idByCode('1128'),
            default => $this->coa->idByCode('1111'),
        };
    }
}