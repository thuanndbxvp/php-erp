<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    /**
     * Danh sách chức vụ phổ biến trong ERP Việt Nam.
     *
     * @return array<int, array{title: string, level: int}>
     */
    private const POSITION_POOL = [
        ['title' => 'Giám đốc', 'level' => 1],
        ['title' => 'Phó giám đốc', 'level' => 2],
        ['title' => 'Trưởng phòng', 'level' => 3],
        ['title' => 'Phó phòng', 'level' => 4],
        ['title' => 'Trưởng nhóm', 'level' => 4],
        ['title' => 'Senior Backend Developer', 'level' => 5],
        ['title' => 'Senior Frontend Developer', 'level' => 5],
        ['title' => 'Senior Sales', 'level' => 5],
        ['title' => 'Senior Accountant', 'level' => 5],
        ['title' => 'Backend Developer', 'level' => 6],
        ['title' => 'Frontend Developer', 'level' => 6],
        ['title' => 'Sales Executive', 'level' => 6],
        ['title' => 'Accountant', 'level' => 6],
        ['title' => 'HR Executive', 'level' => 6],
        ['title' => 'Marketing Executive', 'level' => 6],
        ['title' => 'Customer Service', 'level' => 7],
        ['title' => 'Warehouse Staff', 'level' => 8],
        ['title' => 'Nhân viên kho', 'level' => 8],
        ['title' => 'Tạp vụ', 'level' => 10],
        ['title' => 'Intern', 'level' => 10],
    ];

    public function definition(): array
    {
        $slot = self::POSITION_POOL[array_rand(self::POSITION_POOL)];
        $code = sprintf('POS-%s-%06d', now()->format('Y'), random_int(1, 999999));

        // Salary range theo level (VND)
        $salaryMinMax = match (true) {
            $slot['level'] <= 2 => [40_000_000, 100_000_000], // GĐ/PGĐ
            $slot['level'] === 3 => [25_000_000, 50_000_000],  // Trưởng phòng
            $slot['level'] === 4 => [18_000_000, 35_000_000],  // Phó/TN
            $slot['level'] === 5 => [15_000_000, 30_000_000],  // Senior
            $slot['level'] === 6 => [10_000_000, 20_000_000],  // Exec
            $slot['level'] === 7 => [8_000_000, 15_000_000],   // CS
            $slot['level'] >= 8 => [6_000_000, 12_000_000],    // Kho/Tạp vụ
            default => [7_000_000, 12_000_000],
        };

        return [
            'code' => $code,
            'title' => $slot['title'],
            'department_id' => Department::inRandomOrder()->first()?->id ?? Department::factory(),
            'level' => $slot['level'],
            'min_salary' => (string) $salaryMinMax[0],
            'max_salary' => (string) $salaryMinMax[1],
            'description' => fake()->sentence(8),
            'is_active' => true,
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
     * State: chức vụ ngừng sử dụng.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
