<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Tạo PayrollRun - sử dụng PayrollService::createDraft() để:
 *  - validate period_year/month
 *  - check unique (year, month)
 *  - auto-sinh run_number theo format PR-YYYY-MM
 *
 * Sau khi tạo xong → redirect về edit page để user có thể "Tính lương".
 */
class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    /**
     * Override: Gọi Service thay vì gọi CreateRecord::create() mặc định.
     * Return một instance Page redirect về edit của record vừa tạo.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(\App\Services\HR\PayrollService::class);
        $run = $service->createDraft($data, auth()->user());

        // Redirect sang edit page
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $run]));

        return $run;
    }
}
