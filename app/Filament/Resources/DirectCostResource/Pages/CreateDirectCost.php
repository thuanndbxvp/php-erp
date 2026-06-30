<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectCostResource\Pages;

use App\Filament\Resources\DirectCostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDirectCost extends CreateRecord
{
    protected static string $resource = DirectCostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
