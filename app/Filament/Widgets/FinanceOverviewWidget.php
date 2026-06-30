<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EntryDC;
use App\Enums\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\InvoiceOut;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Widget Tài chính - Cash Flow + AR/AP Aging + P&L snapshot.
 *
 *  - Cash on hand (NH + tiền mặt)
 *  - AR outstanding + aging buckets
 *  - AP outstanding + aging buckets
 *  - P&L YTD (Revenue, COGS, Gross profit)
 *  - Top 5 công nợ lớn nhất
 *
 * Nguồn:
 *  - bank_accounts.current_balance (computed realtime)
 *  - invoice_outs/ins.balance_due (denormalized)
 *  - ledger_entries (cho P&L YTD)
 *  - chart_of_accounts.balance() (cho Cash on hand)
 */
class FinanceOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0; // Hiển thị trên cùng, trước ErpStatsOverview

    protected int|string|array $columnSpan = 'full';

    /**
     * Format VND: 1.234.567 ₫
     */
    private function vnd(float|int|string $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' ₫';
    }

    private function cashOnHand(): float
    {
        // Tổng cash = tất cả bank account active (current_balance computed)
        return (float) BankAccount::where('is_active', true)
            ->get()
            ->sum(fn (BankAccount $b) => $b->current_balance);
    }

    private function arOutstanding(): array
    {
        // AR = InvoiceOut chưa PAID (status != PAID/CANCELLED/CREDITED)
        $outstanding = InvoiceOut::query()
            ->whereNotIn('status', [InvoiceStatus::PAID, InvoiceStatus::CANCELLED, InvoiceStatus::CREDITED])
            ->get();

        $total = (float) $outstanding->sum('balance_due');

        // Aging buckets (tính theo due_date)
        $now = now();
        $buckets = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '>90' => 0.0];
        foreach ($outstanding as $inv) {
            if (! $inv->due_date) {
                $buckets['0-30'] += (float) $inv->balance_due;
                continue;
            }
            $daysOverdue = $now->diffInDays($inv->due_date, false); // (-) là quá hạn
            $daysOverdue = -$daysOverdue; // đổi dấu để = số ngày quá hạn
            $balance = (float) $inv->balance_due;
            if ($daysOverdue <= 0) {
                $buckets['0-30'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $buckets['0-30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $buckets['31-60'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $buckets['61-90'] += $balance;
            } else {
                $buckets['>90'] += $balance;
            }
        }

        return ['total' => $total, 'buckets' => $buckets, 'count' => $outstanding->count()];
    }

    private function apOutstanding(): array
    {
        // AP = InvoiceIn chưa PAID
        $outstanding = \App\Models\InvoiceIn::query()
            ->whereNotIn('status', [InvoiceStatus::PAID, InvoiceStatus::CANCELLED, InvoiceStatus::CREDITED])
            ->get();

        $total = (float) $outstanding->sum('balance_due');

        return ['total' => $total, 'count' => $outstanding->count()];
    }

    private function plSnapshot(): array
    {
        $yearStart = now()->startOfYear()->toDateString();

        // Revenue (511*) - Credit balances YTD
        $revenue = (float) LedgerEntry::query()
            ->where('dc', EntryDC::CREDIT)
            ->whereHas('account', fn ($q) => $q->where('type', \App\Enums\AccountType::REVENUE))
            ->where('posting_date', '>=', $yearStart)
            ->sum('amount_base');

        // COGS (632*) - Debit balances YTD
        $cogs = (float) LedgerEntry::query()
            ->where('dc', EntryDC::DEBIT)
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['632', '6321']))
            ->where('posting_date', '>=', $yearStart)
            ->sum('amount_base');

        // Expense (6xx* ngoại trừ 632)
        $expense = (float) LedgerEntry::query()
            ->where('dc', EntryDC::DEBIT)
            ->whereHas('account', fn ($q) => $q->where('type', \App\Enums\AccountType::EXPENSE)->whereNotIn('code', ['632', '6321']))
            ->where('posting_date', '>=', $yearStart)
            ->sum('amount_base');

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $revenue - $cogs,
            'expense' => $expense,
            'net_profit' => $revenue - $cogs - $expense,
        ];
    }

    public function getStats(): array
    {
        $cash = $this->cashOnHand();
        $ar = $this->arOutstanding();
        $ap = $this->apOutstanding();
        $pl = $this->plSnapshot();

        return [
            Stat::make('💰 Tiền mặt & NH', $this->vnd($cash))
                ->description('Cash on hand (all active accounts)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($cash < 0 ? 'danger' : 'success'),

            Stat::make('📥 Phải thu KH (AR)', $this->vnd($ar['total']))
                ->description(sprintf(
                    '%d HĐ · Trong hạn: %s · 0-30: %s · >90: %s',
                    $ar['count'],
                    $this->vnd($ar['buckets']['0-30']),
                    $this->vnd($ar['buckets']['0-30']),
                    $this->vnd($ar['buckets']['>90']),
                ))
                ->descriptionIcon('heroicon-m-arrow-down-circle')
                ->color($ar['total'] > 0 ? 'warning' : 'gray')
                ->url('/admin/invoice-outs'),

            Stat::make('📤 Phải trả NCC (AP)', $this->vnd($ap['total']))
                ->description(sprintf('%d HĐ chưa thanh toán', $ap['count']))
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color($ap['total'] > 0 ? 'danger' : 'gray')
                ->url('/admin/invoice-ins'),

            Stat::make('📊 Doanh thu YTD', $this->vnd($pl['revenue']))
                ->description(sprintf('Tính từ %s', now()->startOfYear()->format('d/m/Y')))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success')
                ->url('/admin/reports/profit-loss'),

            Stat::make('💵 Lợi nhuận gộp YTD', $this->vnd($pl['gross_profit']))
                ->description(sprintf('DT: %s - COGS: %s', $this->vnd($pl['revenue']), $this->vnd($pl['cogs'])))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($pl['gross_profit'] > 0 ? 'success' : 'danger')
                ->url('/admin/reports/profit-loss'),

            Stat::make('📈 Lợi nhuận ròng YTD', $this->vnd($pl['net_profit']))
                ->description(sprintf('GP - Chi phí: %s', $this->vnd($pl['expense'])))
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color($pl['net_profit'] > 0 ? 'success' : 'danger')
                ->url('/admin/reports/profit-loss'),
        ];
    }
}