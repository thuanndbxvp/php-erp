<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TargetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Luật hoa hồng (CommissionRule).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property TargetType $target_type
 * @property string $rate_percent
 * @property string|null $min_target_amount
 * @property string|null $max_commission_amount
 * @property \Illuminate\Support\Carbon|null $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 * @property bool $is_active
 */
class CommissionRule extends Model
{
    use HasFactory;

    protected $table = 'commission_rules';

    protected $fillable = [
        'name',
        'description',
        'target_type',
        'rate_percent',
        'min_target_amount',
        'max_commission_amount',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => TargetType::class,
            'rate_percent' => 'decimal:2',
            'min_target_amount' => 'decimal:2',
            'max_commission_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // ============= Relationships =============

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'rule_id');
    }
}
