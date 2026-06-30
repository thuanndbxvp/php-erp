<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionResource\Pages;

use App\Filament\Resources\CommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Không có CreateAction - commission auto-gen từ SO Observer
        ];
    }
}
