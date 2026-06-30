<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DirectCostType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chi phí trực tiếp gắn với Đơn bán (Direct Cost).
 *
 * Theo nguyên lý số 4: BẮT BUỘC gắn với SalesOrder - phí ship, đóng gói, hoa hồng...
 * Tính theo từng SO, KHÔNG phải chi phí vận hành chung (đó là OPEX).
 *
 * @property int $id
 * @property int $sales_order_id
 * @property DirectCostType $cost_type
 * @property string $title
 * @property string|null $description
 * @property string $amount
 * @property int $debit_account_id
 * @property int $credit_account_id
 * @property \Illuminate\Support\Carbon $expense_date
 * @property string $currency
 * @property string $exchange_rate
 * @property int|null $payment_id
 * @property int $created_by
 * @property string $status
 */
class DirectCost extends Model
{
    use HasFactory;

    protected $table = 'direct_costs';

    protected $fillable = [
        'sales_order_id',
        'cost_type',
        'title',
        'description',
        'amount',
        'debit_account_id',
        'credit_account_id',
        'expense_date',
        'currency',
        'exchange_rate',
        'payment_id',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost_type' => DirectCostType::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'expense_date' => 'date',
        ];
    }

    // ============= Relationships =============

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
