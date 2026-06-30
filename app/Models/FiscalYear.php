<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Năm tài chính (Fiscal Year).
 */
class FiscalYear extends Model
{
    use HasFactory;

    protected $table = 'fiscal_years';

    protected $fillable = [
        'year',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasManyThrough(JournalEntry::class, AccountingPeriod::class, 'fiscal_year_id', 'accounting_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }
}