<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment - dòng tiền (thu hoặc chi).
 *
 * Phân biệt hướng tiền bằng party_type:
 *   CUSTOMER → tiền VÀO (AR); SUPPLIER → tiền RA (AP)
 *
 * @property int $id
 * @property string $payment_number
 * @property PartyType $party_type
 * @property int $party_id
 * @property int|null $customer_id
 * @property int|null $supplier_id
 * @property PaymentMethod $payment_method
 * @property string $amount
 * @property string $applied_amount
 * @property string $remaining_amount
 * @property string $currency
 * @property string $exchange_rate
 * @property \Illuminate\Support\Carbon $payment_date
 * @property int|null $bank_account_id
 * @property string|null $reference
 * @property PaymentStatus $status
 * @property int|null $bulk_payment_id
 */
class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'payment_number',
        'party_type',
        'party_id',
        'customer_id',
        'supplier_id',
        'payment_method',
        'amount',
        'applied_amount',
        'remaining_amount',
        'currency',
        'exchange_rate',
        'payment_date',
        'bank_account_id',
        'reference',
        'status',
        'bulk_payment_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'payment_date' => 'date',
        ];
    }

    // ============= Relationships =============

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bulkPayment(): BelongsTo
    {
        return $this->belongsTo(BulkPayment::class);
    }

    /**
     * Tất cả các lần match thanh toán của Payment này với InvoiceOut/InvoiceIn.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    /**
     * Giao dịch NH đã match với Payment này (đối soát 1-N).
     *
     * bank_transactions.matched_payment_id là FK trực tiếp tới payments.id,
     * nên dùng hasMany đơn giản là đủ.
     */
    public function matchedBankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'matched_payment_id');
    }

    // ============= Computed =============

    /**
     * Recompute remaining_amount = amount - SUM(applications.amount_applied).
     */
    public function recomputeApplied(): self
    {
        $applied = (float) $this->applications()->sum('amount_applied');
        $this->applied_amount = round($applied, 2);
        $this->remaining_amount = round((float) $this->amount - $applied, 2);

        return $this;
    }
}
