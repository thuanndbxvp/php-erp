<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\SalaryType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['M', 'F']);
        $firstName = $gender === 'F' ? fake()->firstNameFemale() : fake()->firstNameMale();
        $lastName = fake()->lastName();

        $employeeType = fake()->randomElement(EmployeeType::cases());
        $status = fake()->randomElement([
            EmployeeStatus::PROBATION,
            EmployeeStatus::ACTIVE,
            EmployeeStatus::ACTIVE,
            EmployeeStatus::ACTIVE, // bias về ACTIVE
            EmployeeStatus::ON_LEAVE,
        ]);

        $baseSalary = match ($employeeType) {
            EmployeeType::FULLTIME => fake()->numberBetween(8_000_000, 45_000_000),
            EmployeeType::PARTTIME => fake()->numberBetween(3_000_000, 8_000_000),
            EmployeeType::CONTRACTOR => fake()->numberBetween(5_000_000, 30_000_000),
            EmployeeType::INTERN => fake()->numberBetween(2_000_000, 4_000_000),
            EmployeeType::COMMISSION_ONLY => 0,
        };

        $startDate = fake()->dateTimeBetween('-5 years', '-1 month');
        $probationEnd = (clone $startDate)->modify('+60 days');
        $endDate = $status === EmployeeStatus::TERMINATED
            ? fake()->dateTimeBetween($startDate, 'now')
            : null;

        return [
            'employee_code' => sprintf('EMP-%s-%06d', $startDate->format('Y'), random_int(1, 999999)),
            'full_name' => "{$lastName} {$firstName}",
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0'.fake()->numerify('9########'),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years'),
            'gender' => $gender,
            'id_card_number' => fake()->numerify('0##########'),
            'address' => fake()->address(),
            'user_id' => null, // gán sau khi User có sẵn
            'department_id' => Department::inRandomOrder()->first()?->id ?? Department::factory(),
            'position_id' => Position::inRandomOrder()->first()?->id ?? Position::factory(),
            'manager_id' => null, // gán sau
            'employee_type' => $employeeType,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'probation_end_date' => $probationEnd,
            'base_salary' => (string) $baseSalary,
            'salary_type' => $baseSalary === 0 ? SalaryType::COMMISSION_ONLY : SalaryType::MONTHLY,
            'bank_name' => fake()->randomElement(['VCB', 'ACB', 'Techcombank', 'VietinBank', 'BIDV', 'MB Bank', 'TPBank']),
            'bank_account_number' => fake()->numerify('################'),
            'bank_account_holder' => "{$lastName} {$firstName}",
            'tax_code' => fake()->numerify('###########'),
            'dependents_count' => fake()->numberBetween(0, 3),
            'avatar_path' => null,
        ];
    }

    /**
     * State: gán cho phòng ban cụ thể.
     */
    public function forDepartment(Department $dept): static
    {
        return $this->state(fn () => ['department_id' => $dept->id]);
    }

    /**
     * State: gán cho chức vụ cụ thể.
     */
    public function forPosition(Position $pos): static
    {
        return $this->state(fn () => ['department_id' => $pos->department_id, 'position_id' => $pos->id]);
    }

    /**
     * State: gán cho quản lý cụ thể.
     */
    public function managedBy(Employee $manager): static
    {
        return $this->state(fn () => ['manager_id' => $manager->id]);
    }

    /**
     * State: gán user_id cho NV.
     */
    public function withUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /**
     * State: NV đang active (fulltime).
     */
    public function activeFulltime(): static
    {
        return $this->state(fn () => [
            'employee_type' => EmployeeType::FULLTIME,
            'status' => EmployeeStatus::ACTIVE,
            'salary_type' => SalaryType::MONTHLY,
        ]);
    }

    /**
     * State: NV đã nghỉ việc.
     */
    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => EmployeeStatus::TERMINATED,
            'end_date' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }
}
