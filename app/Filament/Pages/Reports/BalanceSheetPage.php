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
 * Bảng cân đối kế toán (Balance Sheet).
 */
class BalanceSheetPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Bảng CĐ kế toán';

    protected static ?string $title = 'Bảng cân đối kế toán';

    protected static ?string $slug = 'balance-sheet';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 62;

    protected string $view = 'filament.pages.reports.balance-sheet';

    public ?string $asOfDate = null;
    public array $report = [];

    public function mount(): void
    {
        $this->asOfDate = now()->format('Y-m-d');
        $this->runReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('asOfDate')
                ->label('Tính đến ngày')
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->runReport()),
        ]);
    }

    public function runReport(): void
    {
        if (! $this->asOfDate) {
            return;
        }

        $this->report = app(ReportService::class)
            ->balanceSheet($this->asOfDate);
    }
}