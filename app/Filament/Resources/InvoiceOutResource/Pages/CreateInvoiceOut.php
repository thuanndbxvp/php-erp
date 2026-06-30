<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceOutResource\Pages;

use App\Filament\Resources\InvoiceOutResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInvoiceOut extends CreateRecord
{
    protected static string $resource = InvoiceOutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['balance_due'] = (string) ((float) ($data['total'] ?? 0) - (float) ($data['paid_amount'] ?? 0));
        return $data;
    }
}