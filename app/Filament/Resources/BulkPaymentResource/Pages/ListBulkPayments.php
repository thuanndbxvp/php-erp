<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkPaymentResource\Pages;

use App\Filament\Resources\BulkPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBulkPayments extends ListRecords
{
    protected static string $resource = BulkPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tạo phiếu gom'),
        ];
    }
}