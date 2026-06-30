<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectCostResource\Pages;

use App\Filament\Resources\DirectCostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDirectCost extends EditRecord
{
    protected static string $resource = DirectCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa'),
        ];
    }
}
