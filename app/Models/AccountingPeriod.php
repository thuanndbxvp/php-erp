<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kỳ kế toán (tháng) thuộc 1 Năm tài chính.
 */
class AccountingPeriod extends Model
{
    use HasFactory;

    protected $table = 'accounting_periods';

    protected $fillable = [
        'fiscal_year_id',
        'period_number',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    /**
     * Ngày entry_date có thuộc kỳ này không?
     */
    public function containsDate(string|\DateTimeInterface $date): bool
    {
        $ts = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;
        return $ts >= $this->start_date->format('Y-m-d')
            && $ts <= $this->end_date->format('Y-m-d');
    }
}