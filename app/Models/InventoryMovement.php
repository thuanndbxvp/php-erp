<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use App\Enums\RefType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Model biến động tồn kho - Sổ cái BẤT BIẾN.
 *
 * Chỉ INSERT, không UPDATE / DELETE. Mọi sai sót đều phải tạo dòng reversal.
 *
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property MovementType $type
 * @property string $quantity
 * @property string $unit_cost
 * @property string $total_value
 * @property RefType|null $ref_type
 * @property int|null $ref_id
 * @property string|null $reason
 * @property string|null $notes
 * @property int $created_by
 */
class InventoryMovement extends Model
{
    use HasFactory;

    protected $table = 'inventory_movements';

    /**
     * Bảng này chỉ có created_at (immutable - không updated_at).
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_cost',
        'total_value',
        'ref_type',
        'ref_id',
        'reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'ref_type' => RefType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total_value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    // ============= Relationships =============

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Polymorphic: trỏ về SalesOrder / PurchaseOrder / ...
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ref_type', 'ref_id');
    }
}