<?php

declare(strict_types=1);

namespace App\Filament\Resources\FiscalYearResource\Pages;

use App\Filament\Resources\FiscalYearResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateFiscalYear extends CreateRecord
{
    protected static string $resource = FiscalYearResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }
}