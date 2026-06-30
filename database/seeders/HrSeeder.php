<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payslip;
use App\Models\PayrollRun;
use App\Models\Position;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * HrSeeder — Phase 5 Bước 3a: Seed dữ liệu demo cho module HR & Payroll.
 *
 * Quy trình theo thứ tự dependency:
 *   1. Departments      (không depend gì)
 *   2. Positions        (depend department)
 *   3. Employees        (depend department + position)
 *   4. Commission Rules (không depend)
 *   5. Attendance       (depend employee)
 *   6. Leaves           (depend employee)
 *   7. Commissions      (depend employee + rule + sales_order)
 *   8. Payroll Runs     (không depend nhưng cần employee tồn tại)
 *   9. Payslips         (depend payroll_run + employee)
 *
 * Có thể seed lặp lại nhờ WithoutModelEvents (không fire observer)
 * và DB transaction bọc ngoài.
 */
class HrSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Tắt observer để seed không tự tạo commission/attendance khi save employee */
    public function run(): void
    {
        DB::transaction(function () {
            $this->command->info('[HrSeeder] Bắt đầu seed module HR & Payroll...');

            $departments = $this->seedDepartments();
            $this->command->info("  ✓ Departments: {$departments->count()} phòng ban");

            $positions = $this->seedPositions($departments);
            $this->command->info("  ✓ Positions: {$positions->count()} chức vụ");

            $employees = $this->seedEmployees($departments, $positions);
            $this->command->info("  ✓ Employees: {$employees->count()} nhân viên");

            $rules = $this->seedCommissionRules();
            $this->command->info("  ✓ Commission Rules: {$rules->count()} quy tắc");

            $this->seedAttendance($employees);
            $this->command->info('  ✓ Attendance: '.\App\Models\Attendance::count().' bản ghi');

            $this->seedLeaves($employees);
            $this->command->info('  ✓ Leaves: '.\App\Models\Leave::count().' bản ghi');

            $this->seedCommissions($employees, $rules);
            $this->command->info('  ✓ Commissions: '.\App\Models\Commission::count().' bản ghi');

            $payrollRuns = $this->seedPayrollRuns();
            $this->command->info("  ✓ Payroll Runs: {$payrollRuns->count()} kỳ lương");

            $this->seedPayslips($payrollRuns, $employees);
            $this->command->info('  ✓ Payslips: '.\App\Models\Payslip::count().' phiếu lương');

            $this->command->info('[HrSeeder] Hoàn tất.');
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Department>
     */
    private function seedDepartments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::factory()->count(8)->create();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Department>  $departments
     * @return \Illuminate\Database\Eloquent\Collection<int, Position>
     */
    private function seedPositions(\Illuminate\Database\Eloquent\Collection $departments): \Illuminate\Database\Eloquent\Collection
    {
        return Position::factory()
            ->count(20)
            ->state(fn () => ['department_id' => $departments->random()->id])
            ->create();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Department>  $departments
     * @param  \Illuminate\Database\Eloquent\Collection<int, Position>  $positions
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function seedEmployees(
        \Illuminate\Database\Eloquent\Collection $departments,
        \Illuminate\Database\Eloquent\Collection $positions,
    ): \Illuminate\Support\Collection {
        // Tạo 25 NV: random phòng ban + chức vụ tương ứng
        $employees = collect();
        for ($i = 0; $i < 25; $i++) {
            $position = $positions->random();
            $employees->push(
                Employee::factory()
                    ->forDepartment($position->department)
                    ->forPosition($position)
                    ->create(),
            );
        }

        // Gán manager cho khoảng 60% NV (trừ các Manager/Lead đã tự tạo)
        $managers = $employees->filter(function (Employee $e) {
            return $e->position && $e->position->level <= 3;
        });
        if ($managers->isNotEmpty()) {
            $employees->each(function (Employee $e) use ($managers) {
                if (fake()->boolean(60) && $e->position && $e->position->level > 3) {
                    $candidate = $managers->firstWhere('department_id', $e->department_id)
                        ?? $managers->random();
                    if ($candidate->id !== $e->id) {
                        $e->manager_id = $candidate->id;
                        $e->save();
                    }
                }
            });
        }

        // Sau khi có manager, update department.manager_id = NV cấp cao nhất
        $departments->each(function (Department $d) use ($employees) {
            $manager = $employees->where('department_id', $d->id)
                ->sortBy(fn (Employee $e) => $e->position->level ?? 99)
                ->first();
            if ($manager) {
                $d->manager_id = $manager->id;
                $d->save();
            }
        });

        return $employees;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CommissionRule>
     */
    private function seedCommissionRules(): \Illuminate\Database\Eloquent\Collection
    {
        // Đảm bảo luôn có 1 rule mặc định REVENUE 5% để SalesOrderObserver dùng được
        $rules = CommissionRule::factory()->defaultRevenue()->count(1)->create();

        // Thêm các rule biến thể
        $rules = $rules->merge(CommissionRule::factory()->count(4)->create());

        return $rules;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function seedAttendance(\Illuminate\Support\Collection $employees): void
    {
        // Mỗi NV: 20 ngày chấm công gần nhất (tránh duplicate cùng employee+date)
        foreach ($employees as $employee) {
            $dates = collect();
            for ($d = 0; $d < 20; $d++) {
                $date = now()->subDays($d)->toDateString();
                if ($dates->contains($date)) {
                    continue;
                }
                $dates->push($date);

                // 90% có mặt, 5% vắng, 5% lễ/tuỳ context
                $roll = fake()->numberBetween(1, 100);
                if ($roll <= 5) {
                    Attendance::factory()->forEmployee($employee)->onDate($date)->absent()->create();
                } elseif ($roll <= 15) {
                    Attendance::factory()->forEmployee($employee)->onDate($date)->heavyOvertime(2.5)->create();
                } else {
                    Attendance::factory()->forEmployee($employee)->onDate($date)->create();
                }
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function seedLeaves(\Illuminate\Support\Collection $employees): void
    {
        // Mỗi NV: 1-2 đơn nghỉ phép, mixed status
        foreach ($employees as $employee) {
            $count = fake()->numberBetween(1, 2);
            for ($i = 0; $i < $count; $i++) {
                $state = fake()->randomElement([null, null, null, 'pending', 'approved', 'approved']);
                $factory = Leave::factory()->forEmployee($employee);
                if ($state === 'pending') {
                    $factory = $factory->pending();
                } elseif ($state === 'approved') {
                    $factory = $factory->approved();
                }
                $factory->create();
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @param  \Illuminate\Support\Collection<int, CommissionRule>  $rules
     */
    private function seedCommissions(
        \Illuminate\Support\Collection $employees,
        \Illuminate\Support\Collection $rules,
    ): void {
        // Chỉ tạo Commission cho NV phòng KD (hoặc 30% NV bất kỳ nếu không có KD)
        $salesEmployees = $employees->filter(function (Employee $e) {
            return str_contains((string) $e->department?->name, 'Kinh doanh')
                || str_contains((string) $e->position?->title, 'Sales');
        });
        if ($salesEmployees->isEmpty()) {
            $salesEmployees = $employees->random(min(5, $employees->count()));
        }

        // 30% trong sales: có 1 commission PENDING (chưa duyệt)
        $salesEmployees->random(max(1, (int) ceil($salesEmployees->count() * 0.3)))
            ->each(function (Employee $e) use ($rules) {
                $rule = $rules->random();
                Commission::factory()
                    ->forEmployee($e)
                    ->state(fn () => ['rule_id' => $rule->id])
                    ->pending()
                    ->create();
            });
    }

    /**
     * @return \Illuminate\Support\Collection<int, PayrollRun>
     */
    private function seedPayrollRuns(): \Illuminate\Support\Collection
    {
        // Tạo 3 kỳ lương gần nhất: tháng trước, 2 tháng trước, 3 tháng trước
        $runs = collect();
        for ($m = 1; $m <= 3; $m++) {
            $ref = now()->subMonths($m);
            $year = (int) $ref->format('Y');
            $month = (int) $ref->format('n');
            $runs->push(
                PayrollRun::factory()
                    ->state(fn () => [
                        'run_number' => sprintf('PR-%04d-%02d', $year, $month),
                        'period_month' => $month,
                        'period_year' => $year,
                        'period_label' => sprintf('Tháng %02d/%04d', $month, $year),
                    ])
                    ->approved()
                    ->create(),
            );
        }
        // 1 kỳ hiện tại: DRAFT (chưa compute)
        $runs->push(
            PayrollRun::factory()
                ->state(fn () => [
                    'run_number' => sprintf('PR-%04d-%02d', (int) now()->format('Y'), (int) now()->format('n')),
                    'period_month' => (int) now()->format('n'),
                    'period_year' => (int) now()->format('Y'),
                    'period_label' => sprintf('Tháng %02d/%04d', (int) now()->format('n'), (int) now()->format('Y')),
                ])
                ->create(),
        );

        return $runs;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollRun>  $runs
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function seedPayslips(
        \Illuminate\Support\Collection $runs,
        \Illuminate\Support\Collection $employees,
    ): void {
        // Tạo payslip cho 3 kỳ APPROVED (không tạo cho kỳ DRAFT)
        $approvedRuns = $runs->where('status', 'APPROVED');
        foreach ($approvedRuns as $run) {
            foreach ($employees as $employee) {
                // Chỉ tạo payslip cho NV đang active và fulltime MONTHLY
                if ($employee->status === 'TERMINATED' || $employee->base_salary == 0) {
                    continue;
                }
                Payslip::factory()
                    ->forEmployee($employee)
                    ->state(fn () => ['payroll_run_id' => $run->id])
                    ->create();
            }
        }
    }
}