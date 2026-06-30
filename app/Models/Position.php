<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chức vụ (Position).
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property int $department_id
 * @property int $level
 * @property string|null $min_salary
 * @property string|null $max_salary
 * @property string|null $description
 * @property bool $is_active
 */
class Position extends Model
{
    use HasFactory;

    protected $table = 'positions';

    protected $fillable = [
        'code',
        'title',
        'department_id',
        'level',
        'min_salary',
        'max_salary',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // ============= Relationships =============

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
