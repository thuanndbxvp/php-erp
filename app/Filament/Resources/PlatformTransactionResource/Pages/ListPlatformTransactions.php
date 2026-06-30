<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformTransactionResource\Pages;

use App\Filament\Resources\PlatformTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformTransactions extends ListRecords
{
    protected static string $resource = PlatformTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tạo GD sàn'),
        ];
    }
}