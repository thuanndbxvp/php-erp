<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phiếu lương nhân viên (Payslip).
 *
 * Bao gồm 2 khối Earnings + Deductions, cùng snapshot thông tin NV tại
 * thời điểm tính lương để tránh phụ thuộc vào dữ liệu employee sau này.
 *
 * @property int $id
 * @property string $payslip_number
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $employee_code_snapshot
 * @property string $employee_name_snapshot
 * @property string|null $department_name_snapshot
 * @property string|null $position_name_snapshot
 * @property string $base_salary
 * @property string $allowances
 * @property string $overtime_pay
 * @property string $commission_amount
 * @property string $other_earnings
 * @property string $gross_salary
 * @property string $personal_tax
 * @property string $social_insurance
 * @property string $health_insurance
 * @property string $unemployment_insurance
 * @property string $advance_deduction
 * @property string $other_deduction
 * @property string $total_deduction
 * @property string $net_salary
 * @property string $work_days
 * @property string $paid_leave_days
 * @property string $unpaid_leave_days
 * @property string $overtime_hours
 * @property \Illuminate\Support\Carbon|null $payment_date
 * @property string $status
 * @property string|null $notes
 */
class Payslip extends Model
{
    use HasFactory;

    protected $table = 'payslips';

    protected $fillable = [
        'payslip_number',
        'payroll_run_id',
        'employee_id',
        'employee_code_snapshot',
        'employee_name_snapshot',
        'department_name_snapshot',
        'position_name_snapshot',
        'base_salary',
        'allowances',
        'overtime_pay',
        'commission_amount',
        'other_earnings',
        'gross_salary',
        'personal_tax',
        'social_insurance',
        'health_insurance',
        'unemployment_insurance',
        'advance_deduction',
        'other_deduction',
        'total_deduction',
        'net_salary',
        'work_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'overtime_hours',
        'payment_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'personal_tax' => 'decimal:2',
            'social_insurance' => 'decimal:2',
            'health_insurance' => 'decimal:2',
            'unemployment_insurance' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'work_days' => 'decimal:2',
            'paid_leave_days' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    // ============= Relationships =============

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }
}
