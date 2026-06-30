<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperatingExpenseResource\Pages;

use App\Filament\Resources\OperatingExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperatingExpense extends EditRecord
{
    protected static string $resource = OperatingExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa'),
        ];
    }
}
