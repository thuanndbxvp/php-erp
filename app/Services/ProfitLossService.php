<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\EntryDC;
use App\Enums\OrderStatus;
use App\Models\ChartOfAccount;
use App\Models\DirectCost;
use App\Models\LedgerEntry;
use App\Models\OperatingExpense;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Báo cáo Lãi/Lỗ (Profit & Loss / Kết quả Kinh doanh).
 *
 * Trái tim của hệ thống báo cáo quản trị.
 *
 * Triết lý tính toán theo nguyên lý số 4 (Master Plan):
 *  - TÁCH RIÊNG Direct Costs (gắn với từng SO) vs OPEX (không gắn SO).
 *  - Gross Profit     = Revenue - COGS
 *  - Contribution     = Gross Profit - Direct Costs
 *  - Net Profit       = Contribution - OPEX
 *
 * Tất cả số liệu quy về VND (amount_base từ ledger_entries, amount native
 * từ các bảng chi phí + xử lý quy đổi theo exchange_rate).
 */
class ProfitLossService
{
    /**
     * Sinh báo cáo P&L cho 1 kỳ (fromDate → toDate).
     *
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     revenue: array{total: float, rows: array<int, array{code, name, amount, percentage}>},
     *     cogs:    array{total: float, rows: array<int, array{order_number, order_date, line_total}>},
     *     gross_profit:         array{amount: float, margin: float},
     *     direct_costs: array{total: float, rows: array<int, array{type, label, amount}>},
     *     contribution_profit:  array{amount: float, margin: float},
     *     opex:        array{total: float, rows: array<int, array{category, code, name, amount}>},
     *     net_profit:          array{amount: float, margin: float},
     * }
     */
    public function generate(Carbon $startDate, Carbon $endDate): array
    {
        $startStr = $startDate->copy()->startOfDay()->format('Y-m-d');
        $endStr = $endDate->copy()->endOfDay()->format('Y-m-d');

        // Bước 1: Doanh thu (tổng credit các TK REVENUE trong kỳ)
        $revenue = $this->calculateRevenue($startStr, $endStr);

        // Bước 2: Giá vốn (COGS) - tổng baseCost × qty của các SO đã SHIPPED trong kỳ
        $cogs = $this->calculateCogs($startStr, $endStr);

        // Bước 3: Direct Costs - tổng amount các direct_costs trong kỳ
        $directCosts = $this->calculateDirectCosts($startStr, $endStr);

        // Bước 4: OPEX - tổng amount các operating_expenses trong kỳ
        $opex = $this->calculateOpex($startStr, $endStr);

        // Bước 5: Tính các mức lợi nhuận + margin
        $revenueTotal = round((float) $revenue['total'], 2);
        $cogsTotal = round((float) $cogs['total'], 2);
        $directTotal = round((float) $directCosts['total'], 2);
        $opexTotal = round((float) $opex['total'], 2);

        $grossProfit = round($revenueTotal - $cogsTotal, 2);
        $contributionProfit = round($grossProfit - $directTotal, 2);
        $netProfit = round($contributionProfit - $opexTotal, 2);

        return [
            'start_date' => $startStr,
            'end_date' => $endStr,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => [
                'amount' => $grossProfit,
                'margin' => $this->marginPercent($grossProfit, $revenueTotal),
            ],
            'direct_costs' => $directCosts,
            'contribution_profit' => [
                'amount' => $contributionProfit,
                'margin' => $this->marginPercent($contributionProfit, $revenueTotal),
            ],
            'opex' => $opex,
            'net_profit' => [
                'amount' => $netProfit,
                'margin' => $this->marginPercent($netProfit, $revenueTotal),
            ],
        ];
    }

    /**
     * BƯỚC 1: Tính Doanh thu (REVENUE) - Sum amount các LedgerEntry
     * có TK REVENUE (5xx theo TT200: 511, 515...) trong kỳ.
     *
     * Doanh thu sinh CÓ (CREDIT) → dùng credit - debit để có số dương.
     * Chỉ lấy các dòng ACTIVE, không tính REVERSED.
     */
    private function calculateRevenue(string $startDate, string $endDate): array
    {
        $accountIds = ChartOfAccount::query()
            ->where('type', AccountType::REVENUE->value)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($accountIds)) {
            return ['total' => 0.0, 'rows' => []];
        }

        $rows = ChartOfAccount::query()
            ->whereIn('id', $accountIds)
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $acc) use ($startDate, $endDate) {
                $debit = (float) LedgerEntry::query()
                    ->where('chart_of_account_id', $acc->id)
                    ->where('status', 'ACTIVE')
                    ->whereBetween(DB::raw('posting_date'), [$startDate, $endDate])
                    ->where('dc', EntryDC::DEBIT->value)
                    ->sum('amount_base');

                $credit = (float) LedgerEntry::query()
                    ->where('chart_of_account_id', $acc->id)
                    ->where('status', 'ACTIVE')
                    ->whereBetween(DB::raw('posting_date'), [$startDate, $endDate])
                    ->where('dc', EntryDC::CREDIT->value)
                    ->sum('amount_base');

                // REVENUE: tăng bằng CREDIT → balance = credit - debit
                $balance = round($credit - $debit, 2);

                return [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount' => $balance,
                    'percentage' => 0.0, // Tính sau khi biết total
                ];
            })
            ->filter(fn ($r) => abs($r['amount']) > 0.005)
            ->values()
            ->toArray();

        $total = array_sum(array_column($rows, 'amount'));

        // Gắn % doanh thu mỗi tài khoản
        foreach ($rows as &$r) {
            $r['percentage'] = $this->marginPercent((float) $r['amount'], (float) $total);
        }

        return [
            'total' => round($total, 2),
            'rows' => $rows,
        ];
    }

    /**
     * BƯỚC 2: Tính Giá vốn (COGS) - Sum (baseCost × quantity) của
     * các SalesOrderLine thuộc SO đã SHIPPED trong kỳ.
     *
     * Dùng nguyên lý "Cost tracking at line level": baseCost đã được
     * snapshot tại dòng SO lúc tạo đơn nên không phụ thuộc giá vốn TB sau này.
     */
    private function calculateCogs(string $startDate, string $endDate): array
    {
        $orderIds = SalesOrder::query()
            ->where('status', OrderStatus::SHIPPED->value)
            ->whereBetween(DB::raw('order_date'), [$startDate, $endDate])
            ->pluck('id')
            ->all();

        if (empty($orderIds)) {
            return ['total' => 0.0, 'rows' => []];
        }

        $rows = SalesOrder::query()
            ->whereIn('id', $orderIds)
            ->orderBy('order_date')
            ->get()
            ->map(function (SalesOrder $so) {
                $lineTotal = (float) SalesOrderLine::query()
                    ->where('sales_order_id', $so->id)
                    ->sum('line_cost');

                return [
                    'order_number' => $so->order_number,
                    'order_date' => $so->order_date?->format('Y-m-d'),
                    'line_total' => round($lineTotal, 2),
                ];
            })
            ->filter(fn ($r) => $r['line_total'] > 0)
            ->values()
            ->toArray();

        $total = array_sum(array_column($rows, 'line_total'));

        return [
            'total' => round($total, 2),
            'rows' => $rows,
        ];
    }

    /**
     * BƯỚC 3: Tính Direct Costs - Sum amount các DirectCost phát sinh
     * trong kỳ (lọc theo expense_date, NOT NULL sales_order_id).
     */
    private function calculateDirectCosts(string $startDate, string $endDate): array
    {
        $grouped = DirectCost::query()
            ->where('status', 'APPROVED')
            ->whereBetween(DB::raw('expense_date'), [$startDate, $endDate])
            ->selectRaw('cost_type, SUM(amount) as amount, COUNT(*) as cnt')
            ->groupBy('cost_type')
            ->get();

        $rows = [];
        $total = 0.0;

        foreach ($grouped as $g) {
            $amount = round((float) $g->amount, 2);
            $total += $amount;

            // Map enum → nhãn tiếng Việt (label())
            $type = \App\Enums\DirectCostType::tryFrom((string) $g->cost_type);
            $rows[] = [
                'type' => (string) $g->cost_type,
                'label' => $type?->label() ?? (string) $g->cost_type,
                'count' => (int) $g->cnt,
                'amount' => $amount,
            ];
        }

        return [
            'total' => round($total, 2),
            'rows' => $rows,
        ];
    }

    /**
     * BƯỚC 4: Tính OPEX - Sum amount các OperatingExpense trong kỳ,
     * group theo OpexCategory để dễ phân tích cơ cấu chi phí.
     */
    private function calculateOpex(string $startDate, string $endDate): array
    {
        $rows = DB::table('operating_expenses as oe')
            ->join('opex_categories as oc', 'oc.id', '=', 'oe.category_id')
            ->where('oe.status', 'APPROVED')
            ->whereBetween(DB::raw('oe.expense_date'), [$startDate, $endDate])
            ->groupBy('oc.id', 'oc.code', 'oc.name')
            ->selectRaw('oc.id as category_id, oc.code, oc.name, SUM(oe.amount) as amount, COUNT(*) as cnt')
            ->get()
            ->map(fn ($r) => [
                'category_id' => (int) $r->category_id,
                'code' => $r->code,
                'name' => $r->name,
                'count' => (int) $r->cnt,
                'amount' => round((float) $r->amount, 2),
            ])
            ->toArray();

        $total = array_sum(array_column($rows, 'amount'));

        return [
            'total' => round($total, 2),
            'rows' => $rows,
        ];
    }

    /**
     * Tính % margin so với doanh thu. Trả 0 nếu DT = 0 để tránh chia cho 0.
     */
    private function marginPercent(float $numerator, float $revenue): float
    {
        if (abs($revenue) < 0.005) {
            return 0.0;
        }

        return round(($numerator / $revenue) * 100, 2);
    }
}
