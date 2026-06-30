<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        $leaveType = fake()->randomElement([
            LeaveType::ANNUAL,
            LeaveType::SICK,
            LeaveType::SICK,
            LeaveType::ANNUAL,
            LeaveType::UNPAID,
            LeaveType::MARRIAGE,
        ]);

        $status = fake()->randomElement([
            LeaveStatus::PENDING,
            LeaveStatus::APPROVED,
            LeaveStatus::APPROVED,
            LeaveStatus::APPROVED, // bias về APPROVED
            LeaveStatus::REJECTED,
        ]);

        $start = fake()->dateTimeBetween('-90 days', '+30 days');
        $days = fake()->numberBetween(1, 5);
        $end = (clone $start)->modify("+{$days} days");

        return [
            'leave_number' => sprintf('LV-%s-%06d', $start->format('Y'), random_int(1, 999999)),
            'employee_id' => Employee::inRandomOrder()->first()?->id ?? Employee::factory(),
            'leave_type' => $leaveType,
            'reason' => fake()->randomElement([
                'Nghỉ phép năm',
                'Nghỉ ốm có xác nhận của bác sĩ',
                'Việc riêng',
                'Đi công tác',
                'Nghỉ cưới',
                'Du lịch gia đình',
            ]),
            'start_date' => $start,
            'end_date' => $end,
            'total_days' => (string) ($days + 1), // inclusive
            'status' => $status,
            'approved_by' => $status === LeaveStatus::PENDING ? null : 1,
            'approved_at' => $status === LeaveStatus::PENDING ? null : Carbon::instance($end),
            'approver_notes' => $status === LeaveStatus::REJECTED ? 'Không đủ nhân sự thay thế' : null,
        ];
    }

    public function forEmployee(Employee $e): static
    {
        return $this->state(fn () => ['employee_id' => $e->id]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => LeaveStatus::PENDING,
            'approved_by' => null,
            'approved_at' => null,
            'approver_notes' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LeaveStatus::APPROVED,
            'approved_by' => 1,
            'approved_at' => now(),
        ]);
    }

    public function unpaid(int $days = 3): static
    {
        return $this->state(fn () => [
            'leave_type' => LeaveType::UNPAID,
            'total_days' => (string) ($days + 1),
        ]);
    }
}
