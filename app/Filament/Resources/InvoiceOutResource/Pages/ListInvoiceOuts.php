<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceOutResource\Pages;

use App\Filament\Resources\InvoiceOutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceOuts extends ListRecords
{
    protected static string $resource = InvoiceOutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tạo hóa đơn'),
        ];
    }
}