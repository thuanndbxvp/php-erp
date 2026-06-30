<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperatingExpenseResource\Pages;

use App\Filament\Resources\OperatingExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperatingExpenses extends ListRecords
{
    protected static string $resource = OperatingExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tạo phiếu chi'),
        ];
    }
}
