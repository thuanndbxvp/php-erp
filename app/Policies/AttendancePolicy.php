<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

/**
 * Policy cho Attendance — kiểm soát chấm công.
 *
 * Phân quyền:
 *  - HR Manager / Staff: full CRUD tất cả records
 *  - Employee: chỉ xem/tạo/cập nhật record của chính mình
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('xem_danh_sach_cham_cong');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        // HR có thể xem tất cả
        if ($user->can('xem_danh_sach_cham_cong')) {
            return true;
        }

        // NV khác: chỉ xem record của mình
        return $this->isOwnAttendance($user, $attendance)
            && $user->can('xem_cham_cong_ca_nhan');
    }

    public function create(User $user): bool
    {
        return $user->can('tao_cham_cong') || $user->can('tao_cham_cong_ca_nhan');
    }

    /**
     * Ai được cập nhật chấm công.
     * HR: sửa bất kỳ record nào.
     * Employee: chỉ sửa record PENDING của mình.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->can('cap_nhat_cham_cong')) {
            return true;
        }

        // NV tự sửa record PENDING của mình
        return $this->isOwnAttendance($user, $attendance)
            && $attendance->status->value === 'PRESENT' // chưa locked
            && $user->can('tao_cham_cong_ca_nhan');
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('xoa_cham_cong');
    }

    /** @param User $user @param Attendance $attendance */
    protected function isOwnAttendance(User $user, Attendance $attendance): bool
    {
        return $user->employee && $user->employee->id === $attendance->employee_id;
    }
}
