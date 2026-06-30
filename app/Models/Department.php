<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phòng ban (Department).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property int|null $manager_id
 * @property string|null $description
 * @property bool $is_active
 */
class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'manager_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
