<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Model đơn mua (Purchase Order).
 *
 * @property int $id
 * @property string $order_number
 * @property OrderType $type
 * @property OrderStatus $status
 * @property int $supplier_id
 * @property int|null $warehouse_id
 * @property \Illuminate\Support\Carbon $order_date
 * @property \Illuminate\Support\Carbon|null $receive_date
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $currency
 * @property string $exchange_rate
 * @property string|null $notes
 * @property int|null $linked_sales_order_id
 * @property int|null $invoice_in_id
 * @property int $created_by
 * @property int|null $approved_by
 */
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_number',
        'type',
        'status',
        'supplier_id',
        'warehouse_id',
        'order_date',
        'receive_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'exchange_rate',
        'notes',
        'linked_sales_order_id',
        'invoice_in_id',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'order_date' => 'date',
            'receive_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
        ];
    }

    // ============= Relationships =============

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * SO đã sinh ra PO này (luồng dropship).
     */
    public function linkedSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'linked_sales_order_id');
    }

    /**
     * SO ngược trỏ về PO này (1 PO dropship được sinh ra từ 1 SO).
     */
    public function generatedFromSalesOrder(): BelongsTo
    {
        // Trỏ ngược: SO có linked_purchase_order_id = id của PO này
        return $this->belongsTo(SalesOrder::class, 'id', 'linked_purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function statusHistory(): MorphMany
    {
        return $this->morphMany(OrderStatusHistory::class, 'order', 'order_type', 'order_id')
            ->where('order_type', 'PURCHASE_ORDER');
    }
}