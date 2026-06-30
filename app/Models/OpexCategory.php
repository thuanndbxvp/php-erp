<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Danh mục chi phí vận hành (OPEX Category).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $account_id
 * @property string|null $description
 * @property bool $is_active
 */
class OpexCategory extends Model
{
    use HasFactory;

    protected $table = 'opex_categories';

    protected $fillable = [
        'code',
        'name',
        'account_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ============= Relationships =============

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(OperatingExpense::class, 'category_id');
    }
}
