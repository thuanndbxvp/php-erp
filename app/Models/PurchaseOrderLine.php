<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model dòng chi tiết đơn mua (Purchase Order Line) - Immutable snapshot.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property array $product_snapshot
 * @property string $quantity
 * @property string $ordered_quantity
 * @property string $received_quantity
 * @property string $unit_cost
 * @property string $discount_percent
 * @property string $discount_amount
 * @property string $tax_percent
 * @property string $line_total
 * @property string $direct_costs
 * @property int $sort_order
 */
class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_lines';

    public const UPDATED_AT = null;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_snapshot',
        'quantity',
        'ordered_quantity',
        'received_quantity',
        'unit_cost',
        'discount_percent',
        'discount_amount',
        'tax_percent',
        'line_total',
        'direct_costs',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'quantity' => 'decimal:3',
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
            'direct_costs' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    // ============= Relationships =============

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}