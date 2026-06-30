<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model dòng chi tiết đơn bán (Sales Order Line) - Immutable snapshot.
 *
 * Chỉ INSERT, không UPDATE. Mọi sửa đổi phải tạo điều chỉnh (Adjustment).
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int $product_id
 * @property array $product_snapshot
 * @property string $quantity
 * @property string $unit_price
 * @property string $base_cost
 * @property string $line_cost
 * @property string $discount_percent
 * @property string $discount_amount
 * @property string $tax_percent
 * @property string $line_total
 * @property string $direct_costs
 * @property int|null $linked_purchase_order_line_id
 * @property int $sort_order
 */
class SalesOrderLine extends Model
{
    use HasFactory;

    protected $table = 'sales_order_lines';

    public const UPDATED_AT = null;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'product_snapshot',
        'quantity',
        'unit_price',
        'base_cost',
        'line_cost',
        'discount_percent',
        'discount_amount',
        'tax_percent',
        'line_total',
        'direct_costs',
        'linked_purchase_order_line_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'base_cost' => 'decimal:2',
            'line_cost' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
            'direct_costs' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    // ============= Relationships =============

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Dòng PO tương ứng (cho luồng dropship).
     */
    public function linkedPurchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'linked_purchase_order_line_id');
    }
}