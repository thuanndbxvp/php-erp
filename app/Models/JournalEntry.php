<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalStatus;
use App\Enums\JournalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Bút toán (Journal Entry - header).
 *
 * @property int $id
 * @property string $journal_number
 * @property int $accounting_period_id
 * @property \Illuminate\Support\Carbon $entry_date
 * @property JournalType $type
 * @property string $description
 * @property JournalStatus $status
 * @property string $total_debit
 * @property string $total_credit
 * @property string $currency
 * @property string $exchange_rate
 * @property int $created_by
 * @property int|null $posted_by
 * @property \Illuminate\Support\Carbon|null $posted_at
 * @property int|null $reversal_of_id
 * @property int|null $reversed_by_id
 * @property string|null $ref_type
 * @property int|null $ref_id
 */
class JournalEntry extends Model
{
    use HasFactory;

    protected $table = 'journal_entries';

    protected $fillable = [
        'journal_number',
        'accounting_period_id',
        'entry_date',
        'type',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'currency',
        'exchange_rate',
        'created_by',
        'posted_by',
        'posted_at',
        'reversal_of_id',
        'reversed_by_id',
        'notes',
        'ref_type',
        'ref_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'type' => JournalType::class,
            'status' => JournalStatus::class,
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
        ];
    }

    // ============= Relationships =============

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Ref ngược về nguồn phát sinh (Payment, InvoiceOut, BankTransaction...).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ref_type', 'ref_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    // ============= Status helpers =============

    public function isBalanced(): bool
    {
        return bccomp((string) $this->total_debit, (string) $this->total_credit, 2) === 0;
    }

    public function isPosted(): bool
    {
        return $this->status === JournalStatus::POSTED;
    }

    public function isReversed(): bool
    {
        return $this->status === JournalStatus::REVERSED;
    }
}