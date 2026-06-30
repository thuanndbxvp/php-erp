<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReconStatus;
use App\Enums\TxType;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ BankTransaction (sao kê ngân hàng).
 *
 * Chức năng:
 *  - importBatch(): nhập nhiều dòng từ file MT940/CSV vào hệ thống.
 *  - reconcileWithPayment(): đối soát 1 giao dịch với 1 Payment.
 *  - unmatch(): gỡ match khi sai.
 *  - autoMatchByReference(): thử match tự động theo reference hoặc amount+date.
 */
class BankTransactionService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
    ) {}

    /**
     * Import một lô giao dịch từ sao kê ngân hàng.
     *
     * @param  array<int, array{
     *     transaction_date: string,
     *     post_date?: string|null,
     *     type: string|TxType,
     *     amount: float|int|string,
     *     balance?: float|int|string|null,
     *     reference?: string|null,
     *     description?: string|null,
     *     counterparty_name?: string|null,
     *     counterparty_account?: string|null,
     *     raw_data?: array|null,
     * }>  $rows
     *
     * @return array{batch_id: string, count: int, ids: array<int>}
     */
    public function importBatch(BankAccount $bankAccount, array $rows, User $actor): array
    {
        if ($bankAccount->account_type === \App\Enums\BankAccountType::PLATFORM_CLEARING) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Tài khoản trung gian sàn không nhận import trực tiếp - dùng PlatformTransaction.',
            ]);
        }

        if (count($rows) === 0) {
            throw ValidationException::withMessages(['rows' => 'Không có dòng nào để import.']);
        }

        return DB::transaction(function () use ($bankAccount, $rows) {
            $batchId = $this->orderNumber->nextBankImportBatchId();
            $ids = [];

            foreach ($rows as $row) {
                $type = $row['type'] instanceof TxType ? $row['type']->value : $row['type'];

                // Ép dấu amount theo quy ước TxType (an toàn hơn cho người dùng)
                $txType = TxType::tryFrom($type);
                $signedAmount = (string) $row['amount'];
                if ($txType && $txType->defaultSign() !== 0) {
                    // Nếu user nhập giá trị tuyệt đối, ép dấu theo defaultSign
                    $abs = ltrim($signedAmount, '-');
                    $signedAmount = $txType->defaultSign() === 1 ? $abs : '-' . $abs;
                }

                $bt = BankTransaction::create([
                    'bank_account_id' => $bankAccount->id,
                    'transaction_date' => $row['transaction_date'],
                    'post_date' => $row['post_date'] ?? null,
                    'type' => $type,
                    'amount' => $signedAmount,
                    'balance' => isset($row['balance']) ? (string) $row['balance'] : null,
                    'reference' => $row['reference'] ?? null,
                    'description' => $row['description'] ?? null,
                    'counterparty_name' => $row['counterparty_name'] ?? null,
                    'counterparty_account' => $row['counterparty_account'] ?? null,
                    'recon_status' => ReconStatus::UNRECONCILED->value,
                    'import_batch_id' => $batchId,
                    'raw_data' => $row['raw_data'] ?? null,
                    'created_at' => now(),
                ]);
                $ids[] = $bt->id;
            }

            return [
                'batch_id' => $batchId,
                'count' => count($ids),
                'ids' => $ids,
            ];
        });
    }

    /**
     * Đối soát 1 giao dịch NH với 1 Payment.
     */
    public function reconcileWithPayment(BankTransaction $bankTx, Payment $payment, User $actor): BankTransaction
    {
        if ($bankTx->recon_status === ReconStatus::MATCHED) {
            throw ValidationException::withMessages(['status' => 'Giao dịch đã được đối soát trước đó.']);
        }

        if ($payment->status === \App\Enums\PaymentStatus::FAILED
            || $payment->status === \App\Enums\PaymentStatus::CANCELLED) {
            throw ValidationException::withMessages(['payment' => 'Payment đã FAILED/CANCELLED.']);
        }

        $bankTx->matched_payment_id = $payment->id;
        $bankTx->recon_status = ReconStatus::MATCHED;
        $bankTx->save();

        return $bankTx->fresh();
    }

    /**
     * Gỡ match (khi đối soát sai).
     */
    public function unmatch(BankTransaction $bankTx, User $actor): BankTransaction
    {
        $bankTx->matched_payment_id = null;
        $bankTx->recon_status = ReconStatus::UNRECONCILED;
        $bankTx->save();

        return $bankTx->fresh();
    }

    /**
     * Tự động match dựa trên reference hoặc amount + date gần đúng.
     *
     * Trả về số lượng match thành công.
     */
    public function autoMatchByReference(BankAccount $bankAccount, User $actor, int $dateToleranceDays = 3): int
    {
        $count = 0;
        $unmatched = $bankAccount->transactions()
            ->where('recon_status', ReconStatus::UNRECONCILED->value)
            ->whereNotNull('reference')
            ->get();

        foreach ($unmatched as $tx) {
            // Tìm Payment có reference trùng + amount khớp dấu + trong vòng dateTolerance ngày
            $payment = Payment::query()
                ->where('reference', $tx->reference)
                ->whereDate('payment_date', '>=', $tx->transaction_date->copy()->subDays($dateToleranceDays))
                ->whereDate('payment_date', '<=', $tx->transaction_date->copy()->addDays($dateToleranceDays))
                ->whereNull('matched_by_bank')
                ->get()
                ->first(function (Payment $p) use ($tx) {
                    return bccomp((string) $p->amount, (string) abs((float) $tx->amount), 2) === 0;
                });

            if ($payment) {
                $this->reconcileWithPayment($tx, $payment, $actor);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Tính chênh lệch số dư giữa sao kê thực tế (current_balance từ giao dịch mới nhất)
     * và số dư trong sổ (opening_balance + sum).
     *
     * Trả về: ['book_balance' => float, 'bank_balance' => float|null, 'diff' => float|null]
     */
    public function reconciliationReport(BankAccount $bankAccount): array
    {
        $bookBalance = (float) $bankAccount->opening_balance + (float) $bankAccount->transactions()->sum('amount');

        $latest = $bankAccount->transactions()
            ->whereNotNull('balance')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        $bankBalance = $latest ? (float) $latest->balance : null;
        $diff = $bankBalance !== null ? round($bankBalance - $bookBalance, 2) : null;

        return [
            'book_balance' => round($bookBalance, 2),
            'bank_balance' => $bankBalance !== null ? round($bankBalance, 2) : null,
            'diff' => $diff,
        ];
    }
}