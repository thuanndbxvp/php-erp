<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

/**
 * Policy cho PayrollRun — kiểm soát kỳ tính lương.
 *
 * Phân quyền:
 *  - HR Manager: full lifecycle (tạo → tính → duyệt → chi trả → hủy)
 *  - HR Staff: tạo, tính lương
 *  - Kế toán lương: duyệt, chi trả
 */
class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('xem_danh_sach_tinh_luong');
    }

    public function view(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('xem_danh_sach_tinh_luong');
    }

    public function create(User $user): bool
    {
        return $user->can('tao_dot_tinh_luong');
    }

    /**
     * Tính lương — chỉ HR Manager / HR Staff.
     * Chỉ chạy khi đang ở trạng thái DRAFT.
     */
    public function compute(User $user, PayrollRun $payrollRun): bool
    {
        if (! $user->can('tinh_luong')) {
            return false;
        }

        return $payrollRun->status->value === 'DRAFT';
    }

    /**
     * Duyệt kỳ lương — chỉ HR Manager / Kế toán lương.
     * Chỉ duyệt khi đang ở trạng thái COMPUTED.
     */
    public function approve(User $user, PayrollRun $payrollRun): bool
    {
        if (! $user->can('duyet_tinh_luong')) {
            return false;
        }

        return $payrollRun->status->value === 'COMPUTED';
    }

    /**
     * Chi trả lương — chỉ HR Manager / Kế toán lương.
     * Chỉ chi trả khi đang APPROVED.
     */
    public function pay(User $user, PayrollRun $payrollRun): bool
    {
        if (! $user->can('chi_tra_luong')) {
            return false;
        }

        return $payrollRun->status->value === 'APPROVED';
    }

    /**
     * Hủy kỳ lương — chỉ HR Manager.
     * Không hủy kỳ đã PAID.
     */
    public function cancel(User $user, PayrollRun $payrollRun): bool
    {
        if (! $user->can('huy_dot_tinh_luong')) {
            return false;
        }

        return $payrollRun->status->isCancellable();
    }

    /**
     * Chỉnh sửa kỳ lương — chỉ HR Manager khi đang DRAFT.
     */
    public function update(User $user, PayrollRun $payrollRun): bool
    {
        if (! $user->can('tao_dot_tinh_luong')) {
            return false;
        }

        return $payrollRun->status->value === 'DRAFT';
    }

    /**
     * Xóa kỳ lương — chỉ HR Manager khi đang DRAFT.
     */
    public function delete(User $user, PayrollRun $payrollRun): bool
    {
        if (! $user->can('huy_dot_tinh_luong')) {
            return false;
        }

        return $payrollRun->status->value === 'DRAFT';
    }
}
