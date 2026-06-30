<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryDC;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dòng bút toán (Ledger Entry / Sổ cái).
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property int $chart_of_account_id
 * @property EntryDC $dc
 * @property string $amount
 * @property string $currency
 * @property string $exchange_rate
 * @property string $amount_base
 * @property string|null $description
 * @property string|null $party_type
 * @property int|null $party_id
 * @property \Illuminate\Support\Carbon $posting_date
 * @property string $status
 * @property int|null $reversal_of_id
 */
class LedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'ledger_entries';

    protected $fillable = [
        'journal_entry_id',
        'chart_of_account_id',
        'dc',
        'amount',
        'currency',
        'exchange_rate',
        'amount_base',
        'description',
        'party_type',
        'party_id',
        'posting_date',
        'status',
        'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'dc' => EntryDC::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_base' => 'decimal:2',
            'posting_date' => 'date',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}