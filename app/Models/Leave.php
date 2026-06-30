<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đơn nghỉ phép (Leave).
 *
 * @property int $id
 * @property string $leave_number
 * @property int $employee_id
 * @property LeaveType $leave_type
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string $total_days
 * @property LeaveStatus $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $approver_notes
 */
class Leave extends Model
{
    use HasFactory;

    protected $table = 'leaves';

    protected $fillable = [
        'leave_number',
        'employee_id',
        'leave_type',
        'reason',
        'start_date',
        'end_date',
        'total_days',
        'status',
        'approved_by',
        'approved_at',
        'approver_notes',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
            'total_days' => 'decimal:2',
        ];
    }

    // ============= Relationships =============

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
