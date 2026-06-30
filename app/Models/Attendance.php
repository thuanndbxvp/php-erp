<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chấm công (Attendance).
 *
 * @property int $id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $check_in
 * @property string|null $check_out
 * @property string $work_hours
 * @property string $overtime_hours
 * @property AttendanceStatus $status
 * @property string|null $notes
 */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'work_hours',
        'overtime_hours',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'string',
            'check_out' => 'string',
            'work_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'status' => AttendanceStatus::class,
        ];
    }

    // ============= Relationships =============

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
