<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformTxStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giao dịch SÀN TMĐT (Shopee, Lazada, Tiki, TiktokShop...).
 *
 * @property int $id
 * @property string $platform_id
 * @property string $platform_order_id
 * @property int|null $sales_order_id
 * @property int|null $clearing_bank_account_id
 * @property string $gross_amount
 * @property string $platform_fee
 * @property string $net_amount
 * @property string|null $actual_received
 * @property \Illuminate\Support\Carbon|null $settlement_date
 * @property int|null $matched_payment_id
 * @property int|null $matched_bank_transaction_id
 * @property PlatformTxStatus $status
 * @property array|null $raw_data
 */
class PlatformTransaction extends Model
{
    use HasFactory;

    protected $table = 'platform_transactions';

    protected $fillable = [
        'platform_id',
        'platform_order_id',
        'sales_order_id',
        'clearing_bank_account_id',
        'gross_amount',
        'platform_fee',
        'net_amount',
        'actual_received',
        'settlement_date',
        'matched_payment_id',
        'matched_bank_transaction_id',
        'status',
        'raw_data',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'actual_received' => 'decimal:2',
            'settlement_date' => 'date',
            'status' => PlatformTxStatus::class,
            'raw_data' => 'array',
        ];
    }

    // ============= Relationships =============

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function clearingBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'clearing_bank_account_id');
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    public function matchedBankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'matched_bank_transaction_id');
    }

    // ============= Computed =============

    /**
     * Kiểm tra net_amount = gross - fee có khớp không (dùng để import validation).
     */
    public function isBalanced(float $epsilon = 0.01): bool
    {
        return abs((float) $this->net_amount - ((float) $this->gross_amount - (float) $this->platform_fee)) <= $epsilon;
    }

    /**
     * Khoảng chênh giữa tiền thực nhận và tiền sàn công bố (dùng phát hiện sai phí).
     *
     *   actualReceived vs netAmount → chênh do tỷ giá/lệch ngân hàng
     */
    public function actualVsNet(): ?float
    {
        if ($this->actual_received === null) {
            return null;
        }

        return round((float) $this->actual_received - (float) $this->net_amount, 2);
    }
}
