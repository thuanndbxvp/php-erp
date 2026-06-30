<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\TargetType;
use App\Models\CommissionRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Luật hoa hồng (CommissionRule).
 *
 * - Validate rate_percent 0..100, effective_from <= effective_to (nếu cả 2).
 * - Khi is_active = false KHÔNG xoá (giữ lịch sử đã tính).
 */
class CommissionRuleService
{
    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     target_type: TargetType|string,
     *     rate_percent: float|string,
     *     min_target_amount?: float|string|null,
     *     max_commission_amount?: float|string|null,
     *     effective_from?: string|null,
     *     effective_to?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(array $data): CommissionRule
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Tên luật không được trống.']);
        }

        $rate = (string) ($data['rate_percent'] ?? '0');
        if ((float) $rate < 0 || (float) $rate > 100) {
            throw ValidationException::withMessages([
                'rate_percent' => 'Tỷ lệ % phải nằm trong [0, 100].',
            ]);
        }

        $from = $data['effective_from'] ?? null;
        $to = $data['effective_to'] ?? null;
        if ($from && $to && $from > $to) {
            throw ValidationException::withMessages([
                'effective_to' => 'Ngày hiệu lực đến phải >= từ ngày.',
            ]);
        }

        $targetType = $data['target_type'] instanceof TargetType
            ? $data['target_type']->value
            : (string) $data['target_type'];

        return DB::transaction(function () use ($data, $name, $rate, $from, $to, $targetType) {
            return CommissionRule::create([
                'name' => $name,
                'description' => $data['description'] ?? null,
                'target_type' => $targetType,
                'rate_percent' => $rate,
                'min_target_amount' => $data['min_target_amount'] ?? null,
                'max_commission_amount' => $data['max_commission_amount'] ?? null,
                'effective_from' => $from,
                'effective_to' => $to,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CommissionRule $rule, array $data): CommissionRule
    {
        if (array_key_exists('rate_percent', $data)) {
            $rate = (string) $data['rate_percent'];
            if ((float) $rate < 0 || (float) $rate > 100) {
                throw ValidationException::withMessages([
                    'rate_percent' => 'Tỷ lệ % phải nằm trong [0, 100].',
                ]);
            }
        }

        $rule->fill($data);
        $rule->save();

        return $rule->fresh();
    }

    /**
     * Tắt rule. Không xoá để giữ lịch sử commission đã tính.
     */
    public function deactivate(CommissionRule $rule): CommissionRule
    {
        $rule->is_active = false;
        $rule->save();

        return $rule->fresh();
    }

    /**
     * Danh sách rule còn hiệu lực tại 1 thời điểm (mặc định: hôm nay).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CommissionRule>
     */
    public function activeRules(?string $asOfDate = null)
    {
        $date = $asOfDate ?? now()->toDateString();

        return CommissionRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('rate_percent', 'desc')
            ->get();
    }
}
