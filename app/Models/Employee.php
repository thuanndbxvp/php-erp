<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nhân viên (Employee).
 *
 * @property int $id
 * @property string $employee_code
 * @property string $full_name
 * @property string|null $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $id_card_number
 * @property string|null $address
 * @property int|null $user_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $manager_id
 * @property EmployeeType $employee_type
 * @property EmployeeStatus $status
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property \Illuminate\Support\Carbon|null $probation_end_date
 * @property string $base_salary
 * @property SalaryType $salary_type
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $bank_account_holder
 * @property string|null $tax_code
 * @property int $dependents_count
 * @property string|null $avatar_path
 */
class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'employee_code',
        'full_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'id_card_number',
        'address',
        'user_id',
        'department_id',
        'position_id',
        'manager_id',
        'employee_type',
        'status',
        'start_date',
        'end_date',
        'probation_end_date',
        'base_salary',
        'salary_type',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'tax_code',
        'dependents_count',
        'avatar_path',
    ];

    protected function casts(): array
    {
        return [
            'employee_type' => EmployeeType::class,
            'status' => EmployeeStatus::class,
            'salary_type' => SalaryType::class,
            'date_of_birth' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_end_date' => 'date',
            'base_salary' => 'decimal:2',
            'dependents_count' => 'integer',
        ];
    }

    // ============= Relationships =============

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Quản lý trực tiếp (self-reference). NULL = giám đốc / không có cấp trên.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * Nhân viên cấp dưới (subordinates) - ngược lại của manager().
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
