<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperatingExpenseResource\Pages;

use App\Filament\Resources\OperatingExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperatingExpense extends CreateRecord
{
    protected static string $resource = OperatingExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Mặc định lấy người tạo = user đang login
        $data['created_by'] = auth()->id();

        return $data;
    }
}
