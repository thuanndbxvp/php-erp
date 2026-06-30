<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kỳ tính lương (PayrollRun).
 *
 * @property int $id
 * @property string $run_number
 * @property int $period_month
 * @property int $period_year
 * @property string $period_label
 * @property \Illuminate\Support\Carbon $period_start_date
 * @property \Illuminate\Support\Carbon $period_end_date
 * @property \Illuminate\Support\Carbon|null $payment_date
 * @property string $total_gross
 * @property string $total_deduction
 * @property string $total_net
 * @property PayrollStatus $status
 * @property int $created_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $paid_by
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $notes
 */
class PayrollRun extends Model
{
    use HasFactory;

    protected $table = 'payroll_runs';

    protected $fillable = [
        'run_number',
        'period_month',
        'period_year',
        'period_label',
        'period_start_date',
        'period_end_date',
        'payment_date',
        'total_gross',
        'total_deduction',
        'total_net',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'payment_date' => 'date',
            'total_gross' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'total_net' => 'decimal:2',
            'status' => PayrollStatus::class,
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    // ============= Relationships =============

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
