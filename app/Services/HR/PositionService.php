<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Department;
use App\Models\Position;
use App\Services\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Chức vụ (Position).
 *
 * - Validate level 1..10, min_salary <= max_salary (nếu cả 2 đều có).
 * - Sinh code tự động khi không nhập.
 * - Khi xoá phải không còn Employee tham chiếu.
 */
class PositionService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     department_id: int,
     *     level?: int,
     *     code?: string|null,
     *     min_salary?: float|string|null,
     *     max_salary?: float|string|null,
     *     description?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(array $data): Position
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Tên chức vụ không được trống.']);
        }

        $level = (int) ($data['level'] ?? 5);
        if ($level < 1 || $level > 10) {
            throw ValidationException::withMessages(['level' => 'Cấp bậc (level) phải nằm trong khoảng 1-10.']);
        }

        $departmentId = (int) ($data['department_id'] ?? 0);
        if ($departmentId <= 0 || ! Department::where('id', $departmentId)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Phòng ban không tồn tại.']);
        }

        $min = $data['min_salary'] ?? null;
        $max = $data['max_salary'] ?? null;
        if ($min !== null && $max !== null && (float) $min > (float) $max) {
            throw ValidationException::withMessages([
                'min_salary' => 'Lương tối thiểu không được lớn hơn lương tối đa.',
            ]);
        }

        $code = isset($data['code']) && $data['code'] !== ''
            ? trim((string) $data['code'])
            : $this->orderNumber->nextPositionCode();

        $this->assertCodeUnique($code);

        return DB::transaction(function () use ($data, $code, $title, $level, $departmentId, $min, $max) {
            return Position::create([
                'code' => $code,
                'title' => $title,
                'department_id' => $departmentId,
                'level' => $level,
                'min_salary' => $min,
                'max_salary' => $max,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Position $position, array $data): Position
    {
        if (array_key_exists('title', $data)) {
            $newTitle = trim((string) $data['title']);
            if ($newTitle === '') {
                throw ValidationException::withMessages(['title' => 'Tên chức vụ không được trống.']);
            }
            $data['title'] = $newTitle;
        }

        if (array_key_exists('level', $data)) {
            $lvl = (int) $data['level'];
            if ($lvl < 1 || $lvl > 10) {
                throw ValidationException::withMessages(['level' => 'Cấp bậc (level) phải nằm trong khoảng 1-10.']);
            }
            $data['level'] = $lvl;
        }

        $position->fill($data);
        $position->save();

        return $position->fresh();
    }

    public function deactivate(Position $position): Position
    {
        $position->is_active = false;
        $position->save();

        return $position->fresh();
    }

    /**
     * @throws ValidationException
     */
    public function delete(Position $position): void
    {
        $empCount = $position->employees()->count();
        if ($empCount > 0) {
            throw ValidationException::withMessages([
                'position' => "Không thể xoá chức vụ: còn {$empCount} nhân viên đang giữ chức vụ này.",
            ]);
        }
        $position->delete();
    }

    private function assertCodeUnique(string $code, ?int $ignoreId = null): void
    {
        $exists = Position::where('code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => "Mã chức vụ [{$code}] đã tồn tại."]);
        }
    }
}
