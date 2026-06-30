<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkPaymentStatus;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phiếu gom đơn thanh toán (Bulk Payment).
 *
 * Gom nhiều invoice vào 1 lần thanh toán duy nhất (VD khách "Cty A" trả 1 lần 5 đơn).
 *
 * @property int $id
 * @property string $bulk_number
 * @property PartyType $party_type
 * @property int $party_id
 * @property int|null $customer_id
 * @property int|null $supplier_id
 * @property string $total_amount
 * @property PaymentMethod $payment_method
 * @property int|null $bank_account_id
 * @property \Illuminate\Support\Carbon $payment_date
 * @property string|null $reference
 * @property string|null $description
 * @property BulkPaymentStatus $status
 * @property int $created_by
 */
class BulkPayment extends Model
{
    use HasFactory;

    protected $table = 'bulk_payments';

    protected $fillable = [
        'bulk_number',
        'party_type',
        'party_id',
        'customer_id',
        'supplier_id',
        'total_amount',
        'payment_method',
        'bank_account_id',
        'payment_date',
        'reference',
        'description',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'payment_method' => PaymentMethod::class,
            'status' => BulkPaymentStatus::class,
            'total_amount' => 'decimal:2',
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

    /**
     * Danh sách các Invoice được gom trong phiếu này.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(BulkPaymentApplication::class);
    }

    /**
     * Tất cả các Payment thuộc BulkPayment này (tách để tiện commission/refund).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
