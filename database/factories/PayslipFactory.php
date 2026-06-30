<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Payslip>
 *
 * Payslip thường được auto-tạo bởi PayrollService::compute() cho từng kỳ lương.
 * Factory này dành cho seeder độc lập (chạy 1 lần tạo dữ liệu mẫu) hoặc test.
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    public function definition(): array
    {
        $employee = Employee::inRandomOrder()->first() ?? Employee::factory();
        $run = PayrollRun::inRandomOrder()->first() ?? PayrollRun::factory();

        // Snapshot employee info (theo pattern của PayrollService)
        $baseSalary = (float) $employee->base_salary;
        $overtimePay = fake()->numberBetween(0, 2000000);
        $commissionAmount = fake()->boolean(30) ? fake()->numberBetween(100000, 3000000) : 0;
        $allowances = fake()->numberBetween(0, 1000000);
        $otherEarnings = 0;
        $gross = $baseSalary + $allowances + $overtimePay + $commissionAmount + $otherEarnings;

        // BHXH/BHYT/BHTN (chỉ cho FULLTIME MONTHLY)
        $si = fake()->boolean(70) ? round($baseSalary * 0.08, 2) : 0;
        $hi = fake()->boolean(70) ? round($baseSalary * 0.015, 2) : 0;
        $ui = fake()->boolean(70) ? round($baseSalary * 0.01, 2) : 0;

        // PIT (lũy tiến VN - đơn giản hoá random)
        $personalTax = fake()->boolean(60) ? fake()->numberBetween(0, 1000000) : 0;

        $totalDeduction = $si + $hi + $ui + $personalTax;
        $net = $gross - $totalDeduction;

        return [
            'payslip_number' => sprintf('PS-%s-%06d', $run->period_year.'-'.str_pad((string) $run->period_month, 2, '0', STR_PAD_LEFT), random_int(1, 999999)),
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_code_snapshot' => $employee->employee_code,
            'employee_name_snapshot' => $employee->full_name,
            'department_name_snapshot' => $employee->department?->name,
            'position_name_snapshot' => $employee->position?->title,
            'base_salary' => (string) $baseSalary,
            'allowances' => (string) $allowances,
            'overtime_pay' => (string) $overtimePay,
            'commission_amount' => (string) $commissionAmount,
            'other_earnings' => (string) $otherEarnings,
            'gross_salary' => (string) round($gross, 2),
            'personal_tax' => (string) $personalTax,
            'social_insurance' => (string) $si,
            'health_insurance' => (string) $hi,
            'unemployment_insurance' => (string) $ui,
            'advance_deduction' => '0',
            'other_deduction' => '0',
            'total_deduction' => (string) round($totalDeduction, 2),
            'net_salary' => (string) round($net, 2),
            'work_days' => (string) fake()->numberBetween(20, 26),
            'paid_leave_days' => '0',
            'unpaid_leave_days' => '0',
            'overtime_hours' => (string) (rand(0, 8)),
            'payment_date' => null,
            'status' => 'DRAFT',
            'notes' => null,
        ];
    }

    public function forEmployee(Employee $e): static
    {
        $run = PayrollRun::inRandomOrder()->first() ?? PayrollRun::factory();

        return $this->state(fn () => [
            'employee_id' => $e->id,
            'employee_code_snapshot' => $e->employee_code,
            'employee_name_snapshot' => $e->full_name,
            'department_name_snapshot' => $e->department?->name,
            'position_name_snapshot' => $e->position?->title,
            'payroll_run_id' => $run->id,
        ]);
    }
}
