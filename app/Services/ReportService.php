<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\EntryDC;
use App\Models\ChartOfAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Service báo cáo tài chính (TT200 - VAS).
 *
 * Cung cấp:
 *  - trialBalance()  : Bảng cân đối thử
 *  - profitLoss()    : Báo cáo KQKD (P&L)
 *  - balanceSheet()  : Bảng cân đối kế toán
 *
 * Tất cả đều snapshot theo VND (amount_base).
 */
class ReportService
{
    /**
     * Bảng cân đối thử (Trial Balance) - tổng Nợ/Có cho từng TK.
     *
     * @return array{
     *     from_date: string, to_date: string,
     *     rows: array<int, array{code, name, type, opening_debit, opening_credit, movement_debit, movement_credit, closing_debit, closing_credit}>,
     *     totals: array{opening_debit, opening_credit, movement_debit, movement_credit, closing_debit, closing_credit}
     * }
     */
    public function trialBalance(string $fromDate, string $toDate): array
    {
        $rows = ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $acc) use ($fromDate, $toDate) {
                // Số dư đầu kỳ (tính đến fromDate - 1)
                $openingDebit = (float) LedgerEntry::query()
                    ->where('chart_of_account_id', $acc->id)
                    ->where('status', 'ACTIVE')
                    ->whereDate('posting_date', '<', $fromDate)
                    ->where('dc', EntryDC::DEBIT->value)
                    ->sum('amount_base');

                $openingCredit = (float) LedgerEntry::query()
                    ->where('chart_of_account_id', $acc->id)
                    ->where('status', 'ACTIVE')
                    ->whereDate('posting_date', '<', $fromDate)
                    ->where('dc', EntryDC::CREDIT->value)
                    ->sum('amount_base');

                // Phát sinh trong kỳ
                $movementDebit = (float) LedgerEntry::query()
                    ->where('chart_of_account_id', $acc->id)
                    ->where('status', 'ACTIVE')
                    ->whereBetween(DB::raw('posting_date'), [$fromDate, $toDate])
                    ->where('dc', EntryDC::DEBIT->value)
                    ->sum('amount_base');

                $movementCredit = (float) LedgerEntry::query()
                    ->where('chart_of_account_id', $acc->id)
                    ->where('status', 'ACTIVE')
                    ->whereBetween(DB::raw('posting_date'), [$fromDate, $toDate])
                    ->where('dc', EntryDC::CREDIT->value)
                    ->sum('amount_base');

                // Số dư cuối kỳ = (opening_debit - opening_credit) + (movement_debit - movement_credit)
                $closing = $acc->type->normalBalance() === EntryDC::DEBIT
                    ? ($openingDebit - $openingCredit) + ($movementDebit - $movementCredit)
                    : ($openingCredit - $openingDebit) + ($movementCredit - $movementDebit);

                if ($closing >= 0) {
                    $closingDebit = $acc->type->normalBalance() === EntryDC::DEBIT ? $closing : 0;
                    $closingCredit = $acc->type->normalBalance() === EntryDC::CREDIT ? $closing : 0;
                } else {
                    $closingDebit = $acc->type->normalBalance() === EntryDC::CREDIT ? -$closing : 0;
                    $closingCredit = $acc->type->normalBalance() === EntryDC::DEBIT ? -$closing : 0;
                }

                $opening = $acc->type->normalBalance() === EntryDC::DEBIT
                    ? ($openingDebit - $openingCredit)
                    : ($openingCredit - $openingDebit);

                if ($opening >= 0) {
                    $od = $acc->type->normalBalance() === EntryDC::DEBIT ? $opening : 0;
                    $oc = $acc->type->normalBalance() === EntryDC::CREDIT ? $opening : 0;
                } else {
                    $od = $acc->type->normalBalance() === EntryDC::CREDIT ? -$opening : 0;
                    $oc = $acc->type->normalBalance() === EntryDC::DEBIT ? -$opening : 0;
                }

                return [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'type' => $acc->type->value,
                    'opening_debit' => round($od, 2),
                    'opening_credit' => round($oc, 2),
                    'movement_debit' => round($movementDebit, 2),
                    'movement_credit' => round($movementCredit, 2),
                    'closing_debit' => round($closingDebit, 2),
                    'closing_credit' => round($closingCredit, 2),
                ];
            })
            ->filter(fn ($r) => $r['opening_debit'] + $r['opening_credit'] + $r['movement_debit'] + $r['movement_credit'] > 0)
            ->values()
            ->toArray();

        $totals = [
            'opening_debit' => array_sum(array_column($rows, 'opening_debit')),
            'opening_credit' => array_sum(array_column($rows, 'opening_credit')),
            'movement_debit' => array_sum(array_column($rows, 'movement_debit')),
            'movement_credit' => array_sum(array_column($rows, 'movement_credit')),
            'closing_debit' => array_sum(array_column($rows, 'closing_debit')),
            'closing_credit' => array_sum(array_column($rows, 'closing_credit')),
        ];

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Báo cáo Kết quả kinh doanh (Profit & Loss).
     *
     *   Doanh thu (REVENUE) - Chi phí (EXPENSE) = Lợi nhuận
     *
     * @return array{
     *     from_date: string, to_date: string,
     *     revenue: array{rows: array, total: float},
     *     expense: array{rows: array, total: float},
     *     net_income: float
     * }
     */
    public function profitLoss(string $fromDate, string $toDate): array
    {
        $revenueRows = $this->aggregateByType(AccountType::REVENUE, $fromDate, $toDate);
        $expenseRows = $this->aggregateByType(AccountType::EXPENSE, $fromDate, $toDate);

        $revenueTotal = array_sum(array_column($revenueRows, 'balance'));
        $expenseTotal = array_sum(array_column($expenseRows, 'balance'));

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'revenue' => [
                'rows' => $revenueRows,
                'total' => round($revenueTotal, 2),
            ],
            'expense' => [
                'rows' => $expenseRows,
                'total' => round($expenseTotal, 2),
            ],
            'net_income' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    /**
     * Bảng cân đối kế toán (Balance Sheet) tại 1 thời điểm.
     *
     *   Tài sản (ASSET) = Nợ phải trả (LIABILITY) + Vốn chủ sở hữu (EQUITY) + Lợi nhuận giữa kỳ
     */
    public function balanceSheet(string $asOfDate): array
    {
        $assets = $this->aggregateByType(AccountType::ASSET, null, $asOfDate);
        $liabilities = $this->aggregateByType(AccountType::LIABILITY, null, $asOfDate);
        $equities = $this->aggregateByType(AccountType::EQUITY, null, $asOfDate);

        // Lợi nhuận giữa kỳ = doanh thu từ đầu năm - chi phí từ đầu năm
        $yearStart = date('Y-01-01', strtotime($asOfDate));
        $pl = $this->profitLoss($yearStart, $asOfDate);

        $totalAssets = array_sum(array_column($assets, 'balance'));
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
        $totalEquity = array_sum(array_column($equities, 'balance'));
        $totalEquityWithPL = $totalEquity + $pl['net_income'];

        return [
            'as_of_date' => $asOfDate,
            'assets' => ['rows' => $assets, 'total' => round($totalAssets, 2)],
            'liabilities' => ['rows' => $liabilities, 'total' => round($totalLiabilities, 2)],
            'equities' => ['rows' => $equities, 'total' => round($totalEquity, 2)],
            'current_period_pl' => round($pl['net_income'], 2),
            'total_equity_with_pl' => round($totalEquityWithPL, 2),
            'total_liabilities_equity' => round($totalLiabilities + $totalEquityWithPL, 2),
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquityWithPL)) < 0.01,
        ];
    }

    /**
     * Aggregate balance theo AccountType (1 nhóm).
     *
     * @return array<int, array{code, name, balance}>
     */
    private function aggregateByType(AccountType $type, ?string $fromDate, ?string $toDate): array
    {
        $query = ChartOfAccount::query()
            ->where('type', $type->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return $query->map(function (ChartOfAccount $acc) use ($fromDate, $toDate) {
            $debit = (float) LedgerEntry::query()
                ->where('chart_of_account_id', $acc->id)
                ->where('status', 'ACTIVE')
                ->when($fromDate, fn ($q) => $q->whereDate('posting_date', '>=', $fromDate))
                ->when($toDate, fn ($q) => $q->whereDate('posting_date', '<=', $toDate))
                ->where('dc', EntryDC::DEBIT->value)
                ->sum('amount_base');

            $credit = (float) LedgerEntry::query()
                ->where('chart_of_account_id', $acc->id)
                ->where('status', 'ACTIVE')
                ->when($fromDate, fn ($q) => $q->whereDate('posting_date', '>=', $fromDate))
                ->when($toDate, fn ($q) => $q->whereDate('posting_date', '<=', $toDate))
                ->where('dc', EntryDC::CREDIT->value)
                ->sum('amount_base');

            $balance = $acc->type->normalBalance() === EntryDC::DEBIT
                ? $debit - $credit
                : $credit - $debit;

            return [
                'code' => $acc->code,
                'name' => $acc->name,
                'balance' => round($balance, 2),
            ];
        })
        ->filter(fn ($r) => abs($r['balance']) > 0.005)
        ->values()
        ->toArray();
    }
}