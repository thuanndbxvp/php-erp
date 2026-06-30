<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

/**
 * Policy cho Payslip — kiểm soát phiếu lương.
 *
 * Phân quyền:
 *  - HR Manager / HR Staff: xem + điều chỉnh tất cả phiếu lương
 *  - Kế toán lương: xem tất cả
 *  - Employee: chỉ xem phiếu lương của chính mình
 */
class PayslipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('xem_danh_sach_tinh_luong');
    }

    public function view(User $user, Payslip $payslip): bool
    {
        // HR Manager / Staff: xem tất cả
        if ($user->can('xem_chi_tiet_phieu_luong')) {
            return true;
        }

        // NV: chỉ xem phiếu lương của mình
        return $this->isOwnPayslip($user, $payslip);
    }

    /**
     * Điều chỉnh phiếu lương — chỉ HR Manager / Kế toán lương,
     * khi phiếu chưa PAID.
     */
    public function adjust(User $user, Payslip $payslip): bool
    {
        if (! $user->can('dieu_chinh_phieu_luong')) {
            return false;
        }

        // Không điều chỉnh phiếu đã chi trả
        return $payslip->status !== 'PAID';
    }

    /**
     * Xem chi tiết lương (gross, deduction, net).
     * Chỉ HR Manager hoặc chính nhân viên.
     */
    public function viewSalaryDetails(User $user, Payslip $payslip): bool
    {
        if ($user->can('xem_chi_tiet_phieu_luong')) {
            return true;
        }

        return $this->isOwnPayslip($user, $payslip);
    }

    /** @param User $user @param Payslip $payslip */
    protected function isOwnPayslip(User $user, Payslip $payslip): bool
    {
        return $user->employee && $user->employee->id === $payslip->employee_id;
    }
}
