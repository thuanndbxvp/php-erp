<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceOutResource\Pages;

use App\Filament\Resources\InvoiceOutResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceOut extends EditRecord
{
    protected static string $resource = InvoiceOutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa'),
        ];
    }
}