<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $checkIn = fake()->time('08:00:00', '09:30:00');
        $checkOut = fake()->time('17:00:00', '18:30:00');
        $checkInTs = strtotime($checkIn);
        $checkOutTs = strtotime($checkOut);
        $workHours = $checkOutTs > $checkInTs ? round(($checkOutTs - $checkInTs) / 3600, 2) : 8.0;
        $overtimeHours = (float) fake()->boolean(30) ? fake()->randomFloat(1, 1, 4) : 0;

        // Status dựa trên pattern chấm công
        $status = match (true) {
            $overtimeHours > 0 => AttendanceStatus::OVERTIME,
            str_starts_with($checkIn, '09:') || str_starts_with($checkIn, '1') => AttendanceStatus::LATE,
            fake()->boolean(15) => AttendanceStatus::WORK_FROM_HOME,
            fake()->boolean(5) => AttendanceStatus::HOLIDAY,
            default => AttendanceStatus::PRESENT,
        };

        return [
            'employee_id' => Employee::inRandomOrder()->first()?->id ?? Employee::factory(),
            'date' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'work_hours' => (string) $workHours,
            'overtime_hours' => (string) $overtimeHours,
            'status' => $status,
            'notes' => fake()->boolean(20) ? fake()->sentence(6) : null,
        ];
    }

    public function forEmployee(Employee $e): static
    {
        return $this->state(fn () => ['employee_id' => $e->id]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn () => ['date' => $date]);
    }

    /**
     * State: NV vắng mặt.
     */
    public function absent(): static
    {
        return $this->state(fn () => [
            'check_in' => null,
            'check_out' => null,
            'work_hours' => '0',
            'overtime_hours' => '0',
            'status' => AttendanceStatus::ABSENT,
        ]);
    }

    /**
     * State: NV đi làm OT nhiều.
     */
    public function heavyOvertime(float $hours = 4.0): static
    {
        return $this->state(fn () => [
            'overtime_hours' => (string) $hours,
            'status' => AttendanceStatus::OVERTIME,
        ]);
    }
}
