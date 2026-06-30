<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceInResource\Pages;

use App\Filament\Resources\InvoiceInResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInvoiceIn extends CreateRecord
{
    protected static string $resource = InvoiceInResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['balance_due'] = (string) ((float) ($data['total'] ?? 0) - (float) ($data['paid_amount'] ?? 0));
        return $data;
    }
}