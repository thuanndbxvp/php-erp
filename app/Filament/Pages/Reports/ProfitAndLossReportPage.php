<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\ProfitLossService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Báo cáo Lãi/Lỗ Quản trị (Management P&L).
 *
 * Khác với ProfitLossPage (TT200 - chỉ Revenue - Expense):
 *  - Tách riêng Direct Costs vs OPEX.
 *  - Hiển thị đầy đủ: Gross Profit → Contribution Profit → Net Profit.
 *  - Phục vụ phân tích cơ cấu chi phí, phục vụ ra quyết định giá bán.
 */
class ProfitAndLossReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'P&L Quản trị';

    protected static ?string $title = 'Báo cáo Lãi/Lỗ (Quản trị)';

    protected static ?string $slug = 'profit-loss-management';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 63;

    protected string $view = 'filament.pages.reports.profit-and-loss';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $report = [];

    public function mount(): void
    {
        // Mặc định tháng hiện tại
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->runReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Khoảng thời gian báo cáo')
                ->columns(3)
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Từ ngày')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn () => $this->runReport()),

                    DatePicker::make('endDate')
                        ->label('Đến ngày')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn () => $this->runReport()),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('presetThisMonth')
                            ->label('Tháng này')
                            ->button()
                            ->color('gray')
                            ->action(function () {
                                $this->startDate = now()->startOfMonth()->format('Y-m-d');
                                $this->endDate = now()->endOfMonth()->format('Y-m-d');
                                $this->runReport();
                            }),
                        \Filament\Forms\Components\Actions\Action::make('presetThisYear')
                            ->label('Năm nay')
                            ->button()
                            ->color('gray')
                            ->action(function () {
                                $this->startDate = now()->startOfYear()->format('Y-m-d');
                                $this->endDate = now()->endOfYear()->format('Y-m-d');
                                $this->runReport();
                            }),
                        \Filament\Forms\Components\Actions\Action::make('presetLastMonth')
                            ->label('Tháng trước')
                            ->button()
                            ->color('gray')
                            ->action(function () {
                                $this->startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
                                $this->endDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
                                $this->runReport();
                            }),
                    ]),
                ]),
        ]);
    }

    public function runReport(): void
    {
        if (! $this->startDate || ! $this->endDate) {
            return;
        }

        $this->report = app(ProfitLossService::class)->generate(
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
        );
    }
}
