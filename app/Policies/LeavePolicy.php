<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;

/**
 * Policy cho Leave — kiểm soát đơn nghỉ phép.
 *
 * Phân quyền:
 *  - HR Manager / Staff: full CRUD + duyệt/từ chối tất cả
 *  - Employee: tạo đơn, sửa/xóa đơn PENDING của mình
 *  - Trưởng phòng (manager): duyệt đơn của nhân viên cấp dưới
 */
class LeavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('xem_danh_sach_nghi_phep');
    }

    public function view(User $user, Leave $leave): bool
    {
        if ($user->can('xem_danh_sach_nghi_phep')) {
            return true;
        }

        // NV xem đơn của mình
        return $this->isOwnLeave($user, $leave)
            && $user->can('xem_don_nghi_phep_ca_nhan');
    }

    public function create(User $user): bool
    {
        return $user->can('tao_don_nghi_phep');
    }

    /**
     * Ai được cập nhật đơn nghỉ phép.
     * Chỉ sửa được khi đang PENDING.
     */
    public function update(User $user, Leave $leave): bool
    {
        if (! $leave->status->isEditable()) {
            return false;
        }

        // HR: sửa bất kỳ đơn nào
        if ($user->can('cap_nhat_don_nghi_phep')) {
            return true;
        }

        // NV: chỉ sửa đơn PENDING của mình
        return $this->isOwnLeave($user, $leave)
            && $leave->status->value === 'PENDING'
            && $user->can('cap_nhat_don_nghi_phep');
    }

    /**
     * Ai được xóa đơn nghỉ phép.
     * Chỉ HR hoặc NV xóa đơn PENDING của mình.
     */
    public function delete(User $user, Leave $leave): bool
    {
        if ($user->can('xoa_don_nghi_phep')) {
            return true;
        }

        // NV xóa đơn PENDING của mình
        return $this->isOwnLeave($user, $leave)
            && $leave->status->value === 'PENDING';
    }

    /**
     * Ai được duyệt đơn nghỉ phép.
     * Chỉ HR Manager/Staff hoặc Trưởng phòng (quản lý trực tiếp).
     */
    public function approve(User $user, Leave $leave): bool
    {
        if (! $user->can('duyet_don_nghi_phep')) {
            return false;
        }

        // Chỉ duyệt đơn đang PENDING
        return $leave->status->value === 'PENDING';
    }

    /**
     * Ai được từ chối đơn nghỉ phép.
     */
    public function reject(User $user, Leave $leave): bool
    {
        if (! $user->can('tu_choi_don_nghi_phep')) {
            return false;
        }

        return $leave->status->value === 'PENDING';
    }

    /**
     * Ai được hủy đơn nghỉ phép.
     * HR: hủy bất kỳ đơn nào.
     * NV: hủy đơn PENDING hoặc APPROVED của mình.
     */
    public function cancel(User $user, Leave $leave): bool
    {
        if (! $user->can('huy_don_nghi_phep')) {
            return false;
        }

        // HR: hủy bất kỳ đơn nào (trừ CANCELLED)
        if ($user->can('duyet_don_nghi_phep')) {
            return $leave->status->value !== 'CANCELLED';
        }

        // NV: chỉ hủy đơn PENDING/APPROVED của mình
        return $this->isOwnLeave($user, $leave)
            && in_array($leave->status->value, ['PENDING', 'APPROVED'], true);
    }

    /** @param User $user @param Leave $leave */
    protected function isOwnLeave(User $user, Leave $leave): bool
    {
        return $user->employee && $user->employee->id === $leave->employee_id;
    }
}
