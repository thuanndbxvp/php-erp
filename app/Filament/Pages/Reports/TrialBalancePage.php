<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Actions\Action;

/**
 * Bảng cân đối thử (Trial Balance) - trang báo cáo.
 */
class TrialBalancePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Bảng CĐ thử';

    protected static ?string $title = 'Bảng cân đối thử';

    protected static ?string $slug = 'trial-balance';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 60;

    protected string $view = 'filament.pages.reports.trial-balance';

    public ?string $fromDate = null;
    public ?string $toDate = null;
    public array $report = [];

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
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
            ->trialBalance($this->fromDate, $this->toDate);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Xuất Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->dispatch('export-trial-balance')),
        ];
    }
}