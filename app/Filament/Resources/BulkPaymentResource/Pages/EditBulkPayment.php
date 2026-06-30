<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkPaymentResource\Pages;

use App\Filament\Resources\BulkPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBulkPayment extends EditRecord
{
    protected static string $resource = BulkPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa'),
        ];
    }
}