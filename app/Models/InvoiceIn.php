<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hóa đơn mua vào (InvoiceIn / AP).
 *
 * Đối xứng với InvoiceOut. Phục vụ công nợ phải trả nhà cung cấp.
 *
 * @property int $id
 * @property string $invoice_number
 * @property int $purchase_order_id
 * @property int $supplier_id
 * @property \Illuminate\Support\Carbon $invoice_date
 * @property \Illuminate\Support\Carbon $due_date
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $tax_amount
 * @property string $total
 * @property string $paid_amount
 * @property string $balance_due
 * @property string $currency
 * @property string $exchange_rate
 * @property string $tax_rate
 * @property InvoiceStatus $status
 * @property int $created_by
 */
class InvoiceIn extends Model
{
    use HasFactory;

    protected $table = 'invoice_ins';

    protected $fillable = [
        'invoice_number',
        'purchase_order_id',
        'supplier_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'paid_amount',
        'balance_due',
        'currency',
        'exchange_rate',
        'tax_rate',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    // ============= Relationships =============

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tất cả các lần match thanh toán của hóa đơn này với các Payment (đã trả cho NCC).
     */
    public function paymentApplications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    // ============= Computed =============

    public function computedBalanceDue(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isOutstanding(): bool
    {
        return $this->computedBalanceDue() > 0.005;
    }

    public function isFullyPaid(): bool
    {
        return $this->computedBalanceDue() <= 0.005;
    }

    public function computeStatus(): InvoiceStatus
    {
        return InvoiceStatus::resolve(
            total: (float) $this->total,
            paid: (float) $this->paid_amount,
            dueDate: $this->due_date,
            isDraft: $this->status === InvoiceStatus::DRAFT,
        );
    }
}
