<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommissionStatus;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 *
 * Lưu ý: Commission thường được auto-tạo từ SalesOrderObserver khi SO → SHIPPED/COMPLETED.
 * Factory này dùng để tạo bản ghi thủ công cho test/seeder (không qua observer).
 */
class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        $orderAmount = fake()->numberBetween(5_000_000, 100_000_000);
        $rule = CommissionRule::inRandomOrder()->first() ?? CommissionRule::factory();
        $rate = (float) ($rule->rate_percent ?? '5');
        $commissionAmount = $orderAmount * $rate / 100;
        if ($rule->max_commission_amount && $commissionAmount > (float) $rule->max_commission_amount) {
            $commissionAmount = (float) $rule->max_commission_amount;
        }

        // Không auto-tạo SalesOrder ở seeder HR để tránh side-effect observer + side data.
        // Nếu project có SalesOrderFactory sau này có thể refactor để link thật.
        $soId = null;
        try {
            $soId = \App\Models\SalesOrder::inRandomOrder()->first()?->id;
        } catch (\Throwable) {
            $soId = null;
        }

        return [
            'employee_id' => Employee::inRandomOrder()->first()?->id ?? Employee::factory(),
            'sales_order_id' => $soId,
            'rule_id' => $rule->id,
            'order_amount' => (string) $orderAmount,
            'target_value' => (string) $orderAmount,
            'commission_amount' => (string) round($commissionAmount, 2),
            'status' => fake()->randomElement([
                CommissionStatus::PENDING,
                CommissionStatus::PENDING,
                CommissionStatus::APPROVED,
                CommissionStatus::PAID,
            ]),
            'earned_date' => fake()->dateTimeBetween('-90 days', 'now'),
            'approved_at' => null,
            'paid_at' => null,
            'payslip_id' => null,
            'notes' => null,
        ];
    }

    public function forEmployee(Employee $e): static
    {
        return $this->state(fn () => ['employee_id' => $e->id]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => CommissionStatus::PENDING,
            'approved_at' => null,
            'paid_at' => null,
            'payslip_id' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => CommissionStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
