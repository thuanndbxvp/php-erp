<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollRun extends EditRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa')
                ->visible(fn () => false), // ẩn mặc định để giữ audit trail
        ];
    }
}
