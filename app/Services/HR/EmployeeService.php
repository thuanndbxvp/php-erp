<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\SalaryType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Nhân viên (Employee).
 *
 * - Sinh employee_code tự động (EMP-{YYYY}-{000001}).
 * - user_id UNIQUE → nếu đã gán user cho NV khác thì không gán lại được.
 * - manager_id chống vòng lặp (NV không quản lý chính nó / cháu của nó).
 * - Khi update manager thì tự động re-evaluate manager's department nếu phù hợp.
 * - terminate(): đóng trạng thái TERMINATED + set end_date.
 *   KHÔNG xoá bản ghi (giữ lịch sử tính lương).
 */
class EmployeeService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        $fullName = trim($data['full_name'] ?? '');
        if ($fullName === '') {
            throw ValidationException::withMessages(['full_name' => 'Họ tên nhân viên không được trống.']);
        }

        // employee_code tự sinh nếu không nhập
        $code = isset($data['employee_code']) && $data['employee_code'] !== ''
            ? trim((string) $data['employee_code'])
            : $this->orderNumber->nextEmployeeCode();

        $this->assertCodeUnique($code);

        // user_id unique
        if (! empty($data['user_id'])) {
            $this->assertUserAvailable((int) $data['user_id']);
        }

        // manager_id chống self-loop
        if (! empty($data['manager_id'])) {
            $mgrId = (int) $data['manager_id'];
            if ($mgrId <= 0) {
                throw ValidationException::withMessages(['manager_id' => 'Quản lý không hợp lệ.']);
            }
        }

        return DB::transaction(function () use ($data, $code, $fullName) {
            return Employee::create([
                'employee_code' => $code,
                'full_name' => $fullName,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'id_card_number' => $data['id_card_number'] ?? null,
                'address' => $data['address'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'employee_type' => $data['employee_type'] ?? EmployeeType::FULLTIME->value,
                'status' => $data['status'] ?? EmployeeStatus::PROBATION->value,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'end_date' => $data['end_date'] ?? null,
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'base_salary' => (string) ($data['base_salary'] ?? '0'),
                'salary_type' => $data['salary_type'] ?? SalaryType::MONTHLY->value,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_account_holder' => $data['bank_account_holder'] ?? null,
                'tax_code' => $data['tax_code'] ?? null,
                'dependents_count' => (int) ($data['dependents_count'] ?? 0),
                'avatar_path' => $data['avatar_path'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        if (array_key_exists('employee_code', $data) && $data['employee_code'] !== $employee->employee_code) {
            $newCode = trim((string) $data['employee_code']);
            if ($newCode === '') {
                throw ValidationException::withMessages(['employee_code' => 'Mã NV không được trống.']);
            }
            $this->assertCodeUnique($newCode, $employee->id);
            $data['employee_code'] = $newCode;
        }

        if (array_key_exists('user_id', $data) && (int) $data['user_id'] !== (int) $employee->user_id) {
            if (! empty($data['user_id'])) {
                $this->assertUserAvailable((int) $data['user_id'], $employee->id);
            }
        }

        if (array_key_exists('manager_id', $data) && ! empty($data['manager_id'])) {
            $newMgr = (int) $data['manager_id'];
            if ($newMgr === (int) $employee->id) {
                throw ValidationException::withMessages([
                    'manager_id' => 'Nhân viên không thể tự làm quản lý chính mình.',
                ]);
            }
            if ($this->isManagerDescendant($employee->id, $newMgr)) {
                throw ValidationException::withMessages([
                    'manager_id' => 'Quản lý mới đang là cấp dưới của nhân viên này (gây vòng lặp).',
                ]);
            }
        }

        $employee->fill($data);
        $employee->save();

        return $employee->fresh();
    }

    /**
     * Đóng hợp đồng: TERMINATED + end_date hôm nay.
     */
    public function terminate(Employee $employee, ?string $endDate = null): Employee
    {
        if ($employee->status === EmployeeStatus::TERMINATED) {
            return $employee;
        }

        $employee->status = EmployeeStatus::TERMINATED;
        $employee->end_date = $endDate ?? now()->toDateString();
        $employee->save();

        return $employee->fresh();
    }

    /**
     * Kích hoạt lại (PROBATION / ACTIVE) sau khi đã TERMINATED.
     */
    public function reactivate(Employee $employee, EmployeeStatus $to = EmployeeStatus::ACTIVE): Employee
    {
        $allowed = [EmployeeStatus::PROBATION, EmployeeStatus::ACTIVE, EmployeeStatus::ON_LEAVE];
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Trạng thái kích hoạt lại không hợp lệ (chỉ PROBATION / ACTIVE / ON_LEAVE).',
            ]);
        }
        $employee->status = $to;
        // Không tự động xoá end_date — tuỳ case thực tế.
        $employee->save();

        return $employee->fresh();
    }

    private function assertCodeUnique(string $code, ?int $ignoreId = null): void
    {
        $exists = Employee::where('employee_code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['employee_code' => "Mã NV [{$code}] đã tồn tại."]);
        }
    }

    private function assertUserAvailable(int $userId, ?int $ignoreEmployeeId = null): void
    {
        $exists = Employee::where('user_id', $userId)
            ->when($ignoreEmployeeId, fn ($q) => $q->where('id', '!=', $ignoreEmployeeId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => 'User này đã được gán cho nhân viên khác (user_id phải UNIQUE trên bảng employees).',
            ]);
        }
        // Đảm bảo user tồn tại
        if (! User::where('id', $userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'User không tồn tại.']);
        }
    }

    /**
     * Có phải $candidateId là cấp dưới (mọi cấp) của $rootId hay không.
     * True → $rootId không được làm manager của $candidateId (ngược lại).
     */
    private function isManagerDescendant(int $rootId, int $candidateId): bool
    {
        $cursor = Employee::find($candidateId);
        while ($cursor && $cursor->manager_id) {
            if ((int) $cursor->manager_id === (int) $rootId) {
                return true;
            }
            $cursor = Employee::find($cursor->manager_id);
        }

        return false;
    }
}
