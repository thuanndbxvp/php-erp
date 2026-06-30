<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chi phí vận hành (Operating Expense / OPEX).
 *
 * Chi phí KHÔNG gắn với đơn hàng cụ thể: điện, nước, lương, thuê văn phòng...
 *
 * @property int $id
 * @property int $category_id
 * @property string|null $expense_number
 * @property string $title
 * @property string|null $description
 * @property string $amount
 * @property int $debit_account_id
 * @property int $credit_account_id
 * @property \Illuminate\Support\Carbon $expense_date
 * @property string $currency
 * @property string $exchange_rate
 * @property int $created_by
 * @property int|null $approved_by
 * @property string $status
 */
class OperatingExpense extends Model
{
    use HasFactory;

    protected $table = 'operating_expenses';

    protected $fillable = [
        'category_id',
        'expense_number',
        'title',
        'description',
        'amount',
        'debit_account_id',
        'credit_account_id',
        'expense_date',
        'currency',
        'exchange_rate',
        'created_by',
        'approved_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'expense_date' => 'date',
        ];
    }

    // ============= Relationships =============

    public function category(): BelongsTo
    {
        return $this->belongsTo(OpexCategory::class, 'category_id');
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
