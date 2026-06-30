<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TargetType;
use App\Models\CommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    /**
     * Luật hoa hồng phổ biến cho sales ERP Việt Nam.
     *
     * @return array<int, array{target_type: TargetType, rate: float, min: int|null, max: int|null, name: string}>
     */
    private const RULE_POOL = [
        [
            'name' => 'Hoa hồng 5% doanh thu',
            'target_type' => TargetType::REVENUE,
            'rate' => 5.00,
            'min' => 0,
            'max' => 50_000_000,
        ],
        [
            'name' => 'Hoa hồng 7% doanh thu',
            'target_type' => TargetType::REVENUE,
            'rate' => 7.00,
            'min' => 50_000_000,
            'max' => 100_000_000,
        ],
        [
            'name' => 'Hoa hồng 10% lợi nhuận',
            'target_type' => TargetType::PROFIT,
            'rate' => 10.00,
            'min' => null,
            'max' => null,
        ],
        [
            'name' => 'Thưởng 50K / đơn hoàn thành',
            'target_type' => TargetType::ORDER_COUNT,
            'rate' => 50_000.0, // sẽ được dùng làm fixed amount
            'min' => null,
            'max' => 20_000_000,
        ],
        [
            'name' => 'Thưởng khách hàng mới',
            'target_type' => TargetType::NEW_CUSTOMER,
            'rate' => 100_000.0,
            'min' => null,
            'max' => null,
        ],
    ];

    public function definition(): array
    {
        $slot = self::RULE_POOL[array_rand(self::RULE_POOL)];
        $from = fake()->dateTimeBetween('-1 year', 'now');
        $to = (clone $from)->modify('+1 year');

        return [
            'name' => $slot['name'],
            'description' => fake()->sentence(12),
            'target_type' => $slot['target_type'],
            'rate_percent' => (string) $slot['rate'],
            'min_target_amount' => $slot['min'] !== null ? (string) $slot['min'] : null,
            'max_commission_amount' => $slot['max'] !== null ? (string) $slot['max'] : null,
            'effective_from' => $from,
            'effective_to' => $to,
            'is_active' => true,
        ];
    }

    /**
     * State: REVENUE 5% (rule mặc định nếu không có rule nào khác).
     */
    public function defaultRevenue(): static
    {
        return $this->state(fn () => [
            'name' => 'Hoa hồng 5% doanh thu (mặc định)',
            'target_type' => TargetType::REVENUE,
            'rate_percent' => '5.00',
            'min_target_amount' => '0',
            'max_commission_amount' => '50000000',
        ]);
    }

    /**
     * State: rule đã hết hạn.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'effective_from' => now()->subYears(2),
            'effective_to' => now()->subYear(),
            'is_active' => false,
        ]);
    }
}
