<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2024, (int) now()->format('Y'));
        $month = fake()->numberBetween(1, 12);
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $runNumber = sprintf('PR-%04d-%02d', $year, $month);

        return [
            'run_number' => $runNumber,
            'period_month' => $month,
            'period_year' => $year,
            'period_label' => sprintf('Tháng %02d/%04d', $month, $year),
            'period_start_date' => $start,
            'period_end_date' => $end,
            'payment_date' => null,
            'total_gross' => '0',
            'total_deduction' => '0',
            'total_net' => '0',
            'status' => PayrollStatus::DRAFT,
            'created_by' => User::inRandomOrder()->first()?->id ?? 1,
            'approved_by' => null,
            'approved_at' => null,
            'paid_by' => null,
            'paid_at' => null,
            'notes' => fake()->boolean(30) ? fake()->sentence(8) : null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => PayrollStatus::APPROVED,
            'approved_by' => 1,
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => array_merge([
            'status' => PayrollStatus::PAID,
            'approved_by' => 1,
            'approved_at' => now()->subDay(),
            'paid_by' => 1,
            'paid_at' => now(),
            'payment_date' => now()->toDateString(),
        ], []));
    }
}
