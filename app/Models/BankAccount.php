<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tài khoản ngân hàng / tiền mặt / ví điện tử / tài khoản trung gian sàn.
 *
 * Một bank_account có 1 số dư đầu kỳ (opening_balance).
 * Số dư cuối kỳ = opening_balance + Σ(bank_transactions.signed_amount).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $account_number
 * @property string|null $bank_name
 * @property string|null $bank_branch
 * @property BankAccountType $account_type
 * @property string $currency
 * @property string $opening_balance
 * @property \Illuminate\Support\Carbon $opening_date
 * @property bool $is_active
 * @property bool $is_default
 * @property string|null $platform_id
 * @property array|null $meta
 * @property string|null $notes
 * @property int|null $created_by
 */
class BankAccount extends Model
{
    use HasFactory;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'code',
        'name',
        'account_number',
        'bank_name',
        'bank_branch',
        'account_type',
        'currency',
        'opening_balance',
        'opening_date',
        'is_active',
        'is_default',
        'platform_id',
        'meta',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => BankAccountType::class,
            'opening_balance' => 'decimal:2',
            'opening_date' => 'date',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'meta' => 'array',
        ];
    }

    // ============= Relationships =============

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * Tính số dư hiện tại (computed): opening_balance + Σ(amount) của các giao dịch.
     *
     * Lưu ý: bank_transactions.amount đã bao gồm dấu (+/-) theo quy ước TxType.
     */
    public function getCurrentBalanceAttribute(): float
    {
        $opening = (float) $this->opening_balance;
        $sumTx = (float) $this->transactions()->sum('amount');

        return round($opening + $sumTx, 2);
    }
}
