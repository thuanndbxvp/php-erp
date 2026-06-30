<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hóa đơn bán ra (InvoiceOut / AR).
 *
 * Trường balance_due được lưu cứng trong DB (denormalized) để truy vấn nhanh.
 * Ngoài ra Model cung cấp accessor computedBalanceDue() tính lại on-demand
 * nếu nghi ngờ dữ liệu lệch (debugging, reconciliation).
 *
 * @property int $id
 * @property string $invoice_number
 * @property int $sales_order_id
 * @property int $customer_id
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
 * @property InvoiceType $invoice_type
 * @property InvoiceStatus $status
 * @property int $created_by
 */
class InvoiceOut extends Model
{
    use HasFactory;

    protected $table = 'invoice_outs';

    protected $fillable = [
        'invoice_number',
        'sales_order_id',
        'customer_id',
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
        'invoice_type',
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
            'invoice_type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
        ];
    }

    // ============= Relationships =============

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tất cả các lần match thanh toán của hóa đơn này với các Payment.
     * Tổng PaymentApplication.amount_applied = paid_amount.
     */
    public function paymentApplications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    // ============= Computed =============

    /**
     * Tính lại balance_due on-demand = total - paid_amount (dùng để debug/reconcile).
     */
    public function computedBalanceDue(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    /**
     * Còn nợ không?
     */
    public function isOutstanding(): bool
    {
        return $this->computedBalanceDue() > 0.005;
    }

    /**
     * Đã trả hết chưa?
     */
    public function isFullyPaid(): bool
    {
        return $this->computedBalanceDue() <= 0.005;
    }

    /**
     * Suy luật trạng thái từ dữ liệu hiện tại.
     */
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
