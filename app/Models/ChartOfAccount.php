<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\EntryDC;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tài khoản kế toán (Chart of Account).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property int|null $parent_id
 * @property string $currency
 * @property bool $is_detail
 * @property bool $is_active
 * @property bool $show_in_reports
 * @property string|null $description
 */
class ChartOfAccount extends Model
{
    use HasFactory;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'currency',
        'is_detail',
        'is_active',
        'show_in_reports',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_detail' => 'boolean',
            'is_active' => 'boolean',
            'show_in_reports' => 'boolean',
        ];
    }

    // ============= Relationships =============

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    // ============= Computed =============

    /**
     * Số dư tài khoản tính đến hiện tại (= Nợ - Có cho ASSET/EXPENSE,
     *  ngược lại = Có - Nợ cho LIABILITY/EQUITY/REVENUE).
     * Chỉ tính các ledger_entries status = ACTIVE.
     *
     * @param  string|null  $fromDate  Optional filter từ ngày
     * @param  string|null  $toDate    Optional filter đến ngày
     */
    public function balance(?string $fromDate = null, ?string $toDate = null): float
    {
        $query = $this->ledgerEntries()
            ->where('status', 'ACTIVE');

        if ($fromDate) {
            $query->whereDate('posting_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('posting_date', '<=', $toDate);
        }

        $debit = (float) $query->clone()->where('dc', EntryDC::DEBIT->value)->sum('amount_base');
        $credit = (float) $query->clone()->where('dc', EntryDC::CREDIT->value)->sum('amount_base');

        return match ($this->type->normalBalance()) {
            EntryDC::DEBIT => round($debit - $credit, 2),
            EntryDC::CREDIT => round($credit - $debit, 2),
        };
    }

    /**
     * Số dư đầu kỳ (tính đến ngày fromDate - 1).
     */
    public function openingBalance(string $fromDate): float
    {
        return $this->balance(null, date('Y-m-d', strtotime($fromDate . ' -1 day')));
    }

    /**
     * Phát sinh trong kỳ (fromDate..toDate).
     */
    public function movement(string $fromDate, string $toDate): array
    {
        $debit = (float) $this->ledgerEntries()
            ->where('status', 'ACTIVE')
            ->whereDate('posting_date', '>=', $fromDate)
            ->whereDate('posting_date', '<=', $toDate)
            ->where('dc', EntryDC::DEBIT->value)
            ->sum('amount_base');

        $credit = (float) $this->ledgerEntries()
            ->where('status', 'ACTIVE')
            ->whereDate('posting_date', '>=', $fromDate)
            ->whereDate('posting_date', '<=', $toDate)
            ->where('dc', EntryDC::CREDIT->value)
            ->sum('amount_base');

        return ['debit' => round($debit, 2), 'credit' => round($credit, 2)];
    }
}