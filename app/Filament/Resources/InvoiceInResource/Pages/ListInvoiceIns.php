<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceInResource\Pages;

use App\Filament\Resources\InvoiceInResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceIns extends ListRecords
{
    protected static string $resource = InvoiceInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tạo hóa đơn'),
        ];
    }
}