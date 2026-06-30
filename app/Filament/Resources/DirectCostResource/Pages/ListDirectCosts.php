<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectCostResource\Pages;

use App\Filament\Resources\DirectCostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDirectCosts extends ListRecords
{
    protected static string $resource = DirectCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tạo chi phí trực tiếp'),
        ];
    }
}
