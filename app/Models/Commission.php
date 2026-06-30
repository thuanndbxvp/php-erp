<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hoa hồng phát sinh (Commission).
 *
 * Mỗi record là 1 khoản hoa hồng phát sinh từ 1 SO cụ thể theo 1 rule.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $sales_order_id
 * @property int $rule_id
 * @property string $order_amount
 * @property string $target_value
 * @property string $commission_amount
 * @property CommissionStatus $status
 * @property \Illuminate\Support\Carbon $earned_date
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property int|null $payslip_id
 * @property string|null $notes
 */
class Commission extends Model
{
    use HasFactory;

    protected $table = 'commissions';

    protected $fillable = [
        'employee_id',
        'sales_order_id',
        'rule_id',
        'order_amount',
        'target_value',
        'commission_amount',
        'status',
        'earned_date',
        'approved_at',
        'paid_at',
        'payslip_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'order_amount' => 'decimal:2',
            'target_value' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'earned_date' => 'date',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    // ============= Relationships =============

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
