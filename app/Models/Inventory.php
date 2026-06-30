<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tồn kho hiện tại - 1 dòng / (product, warehouse).
 *
 * quantity_available là GENERATED column ở MySQL 8 nên
 * KHÔNG cần (và KHÔNG được) ghi đè từ application.
 *
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property string $quantity_on_hand
 * @property string $quantity_reserved
 * @property string $quantity_in_transit
 * @property string|null $quantity_available
 * @property string $average_cost
 */
class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_in_transit',
        'average_cost',
    ];

    /**
     * Cast: lưu DB là DECIMAL, đọc ra dưới dạng string để giữ chính xác tuyệt đối
     * cho các phép tính tài chính (tránh float rounding).
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'quantity_in_transit' => 'decimal:3',
            'quantity_available' => 'decimal:3',
            'average_cost' => 'decimal:2',
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

    /**
     * Sổ cái biến động tồn kho của sản phẩm tại kho này.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id', 'product_id')
            ->whereColumn('inventory_movements.warehouse_id', 'inventories.warehouse_id');
    }
}