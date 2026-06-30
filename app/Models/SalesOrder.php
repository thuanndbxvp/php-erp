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
 * Model đơn bán (Sales Order).
 *
 * Hỗ trợ 2 luồng:
 *  - WAREHOUSE: warehouse_id NOT NULL, linked_purchase_order_id NULL
 *  - DROPSHIP:  warehouse_id NULL, linked_purchase_order_id = PO tự sinh
 *
 * @property int $id
 * @property string $order_number
 * @property OrderType $type
 * @property OrderStatus $status
 * @property int $customer_id
 * @property int|null $warehouse_id
 * @property \Illuminate\Support\Carbon $order_date
 * @property \Illuminate\Support\Carbon|null $ship_date
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $total_cost
 * @property string $currency
 * @property string $exchange_rate
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property int|null $linked_purchase_order_id
 * @property int|null $invoice_out_id
 * @property int $created_by
 * @property int|null $approved_by
 * @property int|null $sales_person_id  NV sale phụ trách đơn (dùng cho commission)
 */
class SalesOrder extends Model
{
    use HasFactory;

    protected $table = 'sales_orders';

    protected $fillable = [
        'order_number',
        'type',
        'status',
        'customer_id',
        'warehouse_id',
        'order_date',
        'ship_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'total_cost',
        'currency',
        'exchange_rate',
        'notes',
        'internal_notes',
        'linked_purchase_order_id',
        'invoice_out_id',
        'sales_person_id',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'order_date' => 'date',
            'ship_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
        ];
    }

    // ============= Relationships =============

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
     * NV sale phụ trách đơn - dùng để tính hoa hồng khi SO đạt SHIPPED/COMPLETED.
     * NULL = đơn nội bộ / không phát sinh commission.
     */
    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_person_id');
    }

    /**
     * PO tự động sinh cho luồng dropship.
     */
    public function linkedPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'linked_purchase_order_id');
    }

    /**
     * PO ngược trỏ về SO này (khi PO được sinh ra từ SO dropship).
     */
    public function generatedPurchaseOrder(): BelongsTo
    {
        // 1 PO có linked_sales_order_id = id của SO này
        return $this->hasOne(PurchaseOrder::class, 'linked_sales_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    /**
     * Lịch sử trạng thái polymorphic - dùng morphMany với type prefix.
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(OrderStatusHistory::class, 'order', 'order_type', 'order_id')
            ->where('order_type', 'SALES_ORDER');
    }
}