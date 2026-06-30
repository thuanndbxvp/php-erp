<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconStatus;
use App\Enums\TxType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giao dịch thực tế trên sao kê ngân hàng.
 *
 * @property int $id
 * @property int $bank_account_id
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property \Illuminate\Support\Carbon|null $post_date
 * @property TxType $type
 * @property string $amount  (decimal - dấu: + vào, - ra)
 * @property string|null $balance
 * @property string|null $reference
 * @property string|null $description
 * @property string|null $counterparty_name
 * @property string|null $counterparty_account
 * @property ReconStatus $recon_status
 * @property int|null $matched_payment_id
 * @property string|null $import_batch_id
 * @property array|null $raw_data
 */
class BankTransaction extends Model
{
    use HasFactory;

    protected $table = 'bank_transactions';

    public $timestamps = false;

    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'post_date',
        'type',
        'amount',
        'balance',
        'reference',
        'description',
        'counterparty_name',
        'counterparty_account',
        'recon_status',
        'matched_payment_id',
        'import_batch_id',
        'raw_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'post_date' => 'date',
            'type' => TxType::class,
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'recon_status' => ReconStatus::class,
            'raw_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ============= Relationships =============

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Payment đã match với giao dịch này (qua FK từ bank_transactions.matched_payment_id).
     */
    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }
}
