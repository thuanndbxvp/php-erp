<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * Danh sách phòng ban hay dùng cho demo data.
     *
     * @return array<string, array{name: string, code_prefix: string}>
     */
    private const DEPT_POOL = [
        ['name' => 'Ban Giám đốc', 'code_prefix' => 'BGĐ'],
        ['name' => 'Phòng Kinh doanh', 'code_prefix' => 'KD'],
        ['name' => 'Phòng Marketing', 'code_prefix' => 'MKT'],
        ['name' => 'Phòng Kế toán', 'code_prefix' => 'KT'],
        ['name' => 'Phòng Nhân sự', 'code_prefix' => 'NS'],
        ['name' => 'Phòng IT', 'code_prefix' => 'IT'],
        ['name' => 'Phòng Kho vận', 'code_prefix' => 'KV'],
        ['name' => 'Phòng Chăm sóc khách hàng', 'code_prefix' => 'CSKH'],
    ];

    public function definition(): array
    {
        $pool = self::DEPT_POOL[array_rand(self::DEPT_POOL)];
        $code = sprintf('%s-%s-%06d', $pool['code_prefix'], now()->format('Y'), random_int(1, 999999));

        return [
            'code' => $code,
            'name' => $pool['name'].' '.fake()->randomElement(['Miền Bắc', 'Miền Nam', 'Hà Nội', 'HCM', 'Đà Nẵng', fake()->company()]),
            'parent_id' => null,
            'manager_id' => null, // sẽ được set sau khi Employee được tạo
            'description' => fake()->sentence(10),
            'is_active' => true,
        ];
    }

    /**
     * State: phòng ban ngừng hoạt động.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * State: phòng ban có phòng ban cha.
     */
    public function withParent(Department $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
