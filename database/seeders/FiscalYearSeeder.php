<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Database\Seeder;

/**
 * Seeder khởi tạo Năm tài chính hiện tại + 12 kỳ tháng.
 */
class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = (int) date('Y');
        $years = [$currentYear, $currentYear + 1];

        foreach ($years as $year) {
            $fiscal = FiscalYear::firstOrCreate(
                ['year' => $year],
                [
                    'start_date' => "{$year}-01-01",
                    'end_date' => "{$year}-12-31",
                    'status' => 'OPEN',
                ],
            );

            for ($m = 1; $m <= 12; $m++) {
                $start = sprintf('%d-%02d-01', $year, $m);
                $end = date('Y-m-t', strtotime($start));
                $name = sprintf('T%02d/%d', $m, $year);

                AccountingPeriod::firstOrCreate(
                    ['fiscal_year_id' => $fiscal->id, 'period_number' => $m],
                    ['name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => 'OPEN'],
                );
            }
        }
    }
}