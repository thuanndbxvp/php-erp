<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Services\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Phòng ban (Department).
 *
 * - CRUD cơ bản (create, update, delete).
 * - Validation: code/name unique, parent != self (chống vòng lặp),
 *   khi xoá phòng ban phải không còn Employee / Position trực thuộc (FK sẽ chặn).
 * - Sinh mã code tự động theo OrderNumberGenerator.
 */
class DepartmentService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     parent_id?: int|null,
     *     manager_id?: int|null,
     *     description?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(array $data): Department
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Tên phòng ban không được trống.']);
        }

        $code = isset($data['code']) && $data['code'] !== ''
            ? trim((string) $data['code'])
            : $this->orderNumber->nextDepartmentCode();

        $this->assertCodeUnique($code);
        $this->assertNameUnique($name);

        return DB::transaction(function () use ($data, $code, $name) {
            return Department::create([
                'code' => $code,
                'name' => $name,
                'parent_id' => $data['parent_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        if (array_key_exists('name', $data)) {
            $newName = trim((string) $data['name']);
            if ($newName === '') {
                throw ValidationException::withMessages(['name' => 'Tên phòng ban không được trống.']);
            }
            if ($newName !== $department->name) {
                $this->assertNameUnique($newName, $department->id);
            }
            $data['name'] = $newName;
        }

        if (array_key_exists('code', $data) && $data['code'] !== $department->code) {
            $newCode = trim((string) $data['code']);
            if ($newCode === '') {
                throw ValidationException::withMessages(['code' => 'Mã phòng ban không được trống.']);
            }
            $this->assertCodeUnique($newCode, $department->id);
            $data['code'] = $newCode;
        }

        // Chống vòng lặp: parent không được là chính nó (hoặc descendants)
        if (! empty($data['parent_id'])) {
            $parentId = (int) $data['parent_id'];
            if ($parentId === $department->id || $this->isDescendant($parentId, $department->id)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Phòng ban cha không hợp lệ (gây vòng lặp cây phân cấp).',
                ]);
            }
        }

        $department->fill($data);
        $department->save();

        return $department->fresh();
    }

    /**
     * Soft delete: đổi is_active = false. Xoá cứng (delete) chỉ khi chưa có
     * positions/employees tham chiếu — DB sẽ chặn qua restrictOnDelete().
     */
    public function deactivate(Department $department): Department
    {
        $department->is_active = false;
        $department->save();

        return $department->fresh();
    }

    /**
     * Xoá cứng. Caller phải chắc chắn rằng không còn employee/position.
     *
     * @throws ValidationException
     */
    public function delete(Department $department): void
    {
        $positionCount = Position::where('department_id', $department->id)->count();
        $employeeCount = Employee::where('department_id', $department->id)->count();

        if ($positionCount > 0 || $employeeCount > 0) {
            throw ValidationException::withMessages([
                'department' => "Không thể xoá: còn {$positionCount} chức vụ và {$employeeCount} nhân viên trực thuộc.",
            ]);
        }

        $department->delete();
    }

    private function assertCodeUnique(string $code, ?int $ignoreId = null): void
    {
        $exists = Department::where('code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => "Mã phòng ban [{$code}] đã tồn tại."]);
        }
    }

    private function assertNameUnique(string $name, ?int $ignoreId = null): void
    {
        $exists = Department::where('name', $name)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['name' => "Tên phòng ban [{$name}] đã tồn tại."]);
        }
    }

    /**
     * Kiểm tra candidate có phải là cháu/chít của $ancestorId hay không.
     */
    private function isDescendant(int $candidateId, int $ancestorId): bool
    {
        $cursor = Department::find($candidateId);
        while ($cursor && $cursor->parent_id) {
            if ((int) $cursor->parent_id === (int) $ancestorId) {
                return true;
            }
            $cursor = Department::find($cursor->parent_id);
        }

        return false;
    }
}
