<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformTransactionResource\Pages;

use App\Filament\Resources\PlatformTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformTransaction extends EditRecord
{
    protected static string $resource = PlatformTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa'),
        ];
    }
}