<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryDC;
use App\Enums\JournalStatus;
use App\Enums\JournalType;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ sổ cái kép (Double-entry Bookkeeping).
 *
 * Quy tắc cứng:
 *  - Mỗi JournalEntry khi POSTED phải có SUM(debit) = SUM(credit)
 *  - Ngày entry phải nằm trong kỳ kế toán OPEN
 *  - Sau khi POSTED không sửa được - chỉ reverse
 */
class JournalService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
    ) {}

    /**
     * Tạo + ghi sổ 1 bút toán (atomic).
     *
     * @param  array{
     *     type: JournalType|string,
     *     entry_date: string,
     *     description: string,
     *     lines: array<int, array{
     *         account_id: int, dc: EntryDC|string, amount: float|int|string,
     *         description?: string|null, party_type?: string|null, party_id?: int|null
     *     }>,
     *     ref_type?: string|null,
     *     ref_id?: int|null,
     *     notes?: string|null,
     *     currency?: string,
     *     exchange_rate?: float|int|string,
     * }  $data
     */
    public function post(array $data, User $actor, bool $autoPost = true): JournalEntry
    {
        if (empty($data['lines'])) {
            throw ValidationException::withMessages(['lines' => 'Bút toán phải có ít nhất 1 dòng.']);
        }

        $entryDate = $data['entry_date'];
        $period = $this->resolveOpenPeriod($entryDate);

        return DB::transaction(function () use ($data, $actor, $period, $autoPost) {
            $type = $data['type'] instanceof JournalType ? $data['type']->value : $data['type'];
            $currency = $data['currency'] ?? 'VND';
            $exchangeRate = (string) ($data['exchange_rate'] ?? '1');

            $journal = JournalEntry::create([
                'journal_number' => $this->orderNumber->nextJournalEntryNumber(),
                'accounting_period_id' => $period->id,
                'entry_date' => $data['entry_date'],
                'type' => $type,
                'description' => $data['description'],
                'status' => JournalStatus::DRAFT->value,
                'total_debit' => '0',
                'total_credit' => '0',
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'ref_type' => $data['ref_type'] ?? null,
                'ref_id' => $data['ref_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $totalDebit = '0';
            $totalCredit = '0';

            foreach ($data['lines'] as $row) {
                $amount = (string) $row['amount'];
                if (bccomp($amount, '0', 2) <= 0) {
                    throw ValidationException::withMessages(['lines' => 'Mỗi dòng phải có số tiền > 0.']);
                }

                $dc = $row['dc'] instanceof EntryDC ? $row['dc']->value : $row['dc'];
                $amountBase = (string) round((float) $amount * (float) $exchangeRate, 2);

                LedgerEntry::create([
                    'journal_entry_id' => $journal->id,
                    'chart_of_account_id' => (int) $row['account_id'],
                    'dc' => $dc,
                    'amount' => $amount,
                    'currency' => $currency,
                    'exchange_rate' => $exchangeRate,
                    'amount_base' => $amountBase,
                    'description' => $row['description'] ?? null,
                    'party_type' => $row['party_type'] ?? null,
                    'party_id' => $row['party_id'] ?? null,
                    'posting_date' => $data['entry_date'],
                    'status' => 'ACTIVE',
                ]);

                if ($dc === EntryDC::DEBIT->value) {
                    $totalDebit = bcadd($totalDebit, $amount, 2);
                } else {
                    $totalCredit = bcadd($totalCredit, $amount, 2);
                }
            }

            // Validate cân bằng
            if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
                throw ValidationException::withMessages([
                    'lines' => "Bút toán không cân bằng: Nợ={$totalDebit}, Có={$totalCredit}",
                ]);
            }

            $journal->total_debit = $totalDebit;
            $journal->total_credit = $totalCredit;

            if ($autoPost) {
                $journal->status = JournalStatus::POSTED->value;
                $journal->posted_by = $actor->id;
                $journal->posted_at = now();
            }

            $journal->save();

            return $journal->fresh('ledgerEntries');
        });
    }

    /**
     * Đảo ngược bút toán (tạo 1 JE mới với type = JOURNAL + reversal_of_id).
     *
     * Không xóa bút toán gốc - chỉ đánh dấu REVERSED + ghi bút toán đảo ngược dấu.
     */
    public function reverse(JournalEntry $journal, User $actor, ?string $reason = null): JournalEntry
    {
        if (! $journal->isPosted()) {
            throw ValidationException::withMessages(['status' => 'Chỉ đảo ngược được bút toán đã POSTED.']);
        }
        if ($journal->reversed_by_id) {
            throw ValidationException::withMessages(['status' => 'Bút toán đã được đảo ngược trước đó.']);
        }

        return DB::transaction(function () use ($journal, $actor, $reason) {
            $lines = [];
            foreach ($journal->ledgerEntries as $le) {
                $lines[] = [
                    'account_id' => $le->chart_of_account_id,
                    'dc' => $le->dc === EntryDC::DEBIT ? EntryDC::CREDIT : EntryDC::DEBIT,
                    'amount' => $le->amount,
                    'description' => 'Reversal: ' . ($reason ?? ''),
                    'party_type' => $le->party_type,
                    'party_id' => $le->party_id,
                ];
            }

            $reversal = $this->post([
                'type' => JournalType::JOURNAL,
                'entry_date' => now()->toDateString(),
                'description' => 'Reversal of ' . $journal->journal_number . ($reason ? ' - ' . $reason : ''),
                'lines' => $lines,
                'ref_type' => $journal->ref_type,
                'ref_id' => $journal->ref_id,
                'notes' => $reason,
                'currency' => $journal->currency,
                'exchange_rate' => $journal->exchange_rate,
            ], $actor, autoPost: true);

            $reversal->reversal_of_id = $journal->id;
            $reversal->save();

            $journal->reversed_by_id = $reversal->id;
            $journal->status = JournalStatus::REVERSED->value;
            $journal->save();

            return $reversal;
        });
    }

    /**
     * Tìm kỳ kế toán OPEN chứa ngày entry_date.
     */
    public function resolveOpenPeriod(string $entryDate): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->where('status', 'OPEN')
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->orderByDesc('start_date')
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'entry_date' => "Không có kỳ kế toán OPEN chứa ngày {$entryDate}. Hãy tạo FiscalYear + Period trước.",
            ]);
        }

        return $period;
    }

    /**
     * Lấy (hoặc tạo) accounting period cho ngày bất kỳ.
     */
    public function getOrCreatePeriodForDate(string $date): AccountingPeriod
    {
        $ts = strtotime($date);
        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        $start = date('Y-m-01', $ts);
        $end = date('Y-m-t', $ts);
        $name = sprintf('T%02d/%d', $month, $year);

        $fiscal = FiscalYear::query()->firstOrCreate(
            ['year' => $year],
            [
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'OPEN',
            ],
        );

        return AccountingPeriod::query()->firstOrCreate(
            ['fiscal_year_id' => $fiscal->id, 'period_number' => $month],
            [
                'name' => $name,
                'start_date' => $start,
                'end_date' => $end,
                'status' => 'OPEN',
            ],
        );
    }
}