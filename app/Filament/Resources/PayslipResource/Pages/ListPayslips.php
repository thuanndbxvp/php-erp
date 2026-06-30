<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayslipResource\Pages;

use App\Filament\Resources\PayslipResource;
use Filament\Resources\Pages\ListRecords;

class ListPayslips extends ListRecords
{
    protected static string $resource = PayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Không có CreateAction - payslip auto-gen từ PayrollService::compute()
        ];
    }
}
