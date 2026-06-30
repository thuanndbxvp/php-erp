<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Báo cáo Kết quả kinh doanh (P&L).
 */
class ProfitLossPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Báo cáo KQKD';

    protected static ?string $title = 'Báo cáo kết quả kinh doanh';

    protected static ?string $slug = 'profit-loss';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 61;

    protected string $view = 'filament.pages.reports.profit-loss';

    public ?string $fromDate = null;
    public ?string $toDate = null;
    public array $report = [];

    public function mount(): void
    {
        $this->fromDate = now()->startOfYear()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');
        $this->runReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('fromDate')
                ->label('Từ ngày')
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->runReport()),

            DatePicker::make('toDate')
                ->label('Đến ngày')
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->runReport()),
        ]);
    }

    public function runReport(): void
    {
        if (! $this->fromDate || ! $this->toDate) {
            return;
        }

        $this->report = app(ReportService::class)
            ->profitLoss($this->fromDate, $this->toDate);
    }
}