<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Policy cho Employee — kiểm soát quyền CRUD + xem lương.
 *
 * Phân quyền:
 *  - Super Admin: toàn quyền
 *  - HR Manager / HR Staff: full CRUD + xem lương
 *  - Employee: chỉ xem thông tin cơ bản (không lương)
 */
class EmployeePolicy
{
    /** Ai được xem danh sách nhân viên */
    public function viewAny(User $user): bool
    {
        return $user->can('xem_danh_sach_nhan_vien')
            || $user->can('xem_danh_sach_phong_ban');
    }

    /** Ai được xem chi tiết 1 nhân viên */
    public function view(User $user, Employee $employee): bool
    {
        // Chính mình: xem thông tin cơ bản
        if ($this->isOwnEmployee($user, $employee)) {
            return true;
        }

        // HR Manager / Staff: xem toàn bộ thông tin
        return $user->can('xem_nhan_vien') || $user->can('xem_danh_sach_nhan_vien');
    }

    /** Ai được tạo nhân viên mới */
    public function create(User $user): bool
    {
        return $user->can('tao_nhan_vien');
    }

    /** Ai được cập nhật thông tin nhân viên */
    public function update(User $user, Employee $employee): bool
    {
        return $user->can('cap_nhat_nhan_vien');
    }

    /** Ai được xóa nhân viên (chỉ HR Manager + không active) */
    public function delete(User $user, Employee $employee): bool
    {
        if (! $user->can('xoa_nhan_vien')) {
            return false;
        }

        // Không xóa nhân viên đang ACTIVE — nên chuyển sang TERMINATED
        return $employee->status->value !== 'ACTIVE';
    }

    /**
     * Ai được xem lương của nhân viên.
     * Chỉ HR Manager/Staff hoặc chính nhân viên đó.
     */
    public function viewSalary(User $user, Employee $employee): bool
    {
        if ($this->isOwnEmployee($user, $employee)) {
            return true;
        }

        return $user->can('xem_luong_nhan_vien');
    }

    /**
     * Ai được cập nhật lương nhân viên.
     */
    public function updateSalary(User $user, Employee $employee): bool
    {
        return $user->can('cap_nhat_luong_nhan_vien');
    }

    /** @param User $user @param Employee $employee */
    protected function isOwnEmployee(User $user, Employee $employee): bool
    {
        return $user->employee && $user->employee->id === $employee->id;
    }
}
