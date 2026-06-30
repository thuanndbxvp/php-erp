<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Payslip;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Phiếu lương (Payslip) - read-mostly.
 *
 * Payslip chủ yếu được tính tự động bởi PayrollService.
 * User có thể sửa một số trường allowances/other_earnings/other_deduction/advance_deduction
 * trước khi kỳ lương được APPROVED.
 *
 * Sau khi APPROVED → chỉ PayrollService::pay() mới thay đổi.
 */
class PayslipService
{
    /**
     * Cập nhật các trường cho phép sửa (khi payslip còn DRAFT - thuộc PayrollRun DRAFT).
     *
     * @param  array<string, string|float>  $data  Allowed: allowances, other_earnings,
     *                                             advance_deduction, other_deduction, notes
     */
    public function updateAdjustments(Payslip $payslip, array $data): Payslip
    {
        if ($payslip->status !== 'DRAFT' && $payslip->status !== 'APPROVED') {
            throw ValidationException::withMessages([
                'status' => "Payslip ở trạng thái [{$payslip->status}] không thể chỉnh sửa.",
            ]);
        }

        // Chỉ cho phép sửa một số trường
        $allowed = ['allowances', 'other_earnings', 'advance_deduction', 'other_deduction', 'notes'];
        $updates = array_intersect_key($data, array_flip($allowed));

        if (! empty($updates)) {
            // Cast numeric
            foreach (['allowances', 'other_earnings', 'advance_deduction', 'other_deduction'] as $k) {
                if (isset($updates[$k])) {
                    $updates[$k] = (string) $updates[$k];
                }
            }
            $payslip->fill($updates);

            // Recompute totals sau khi sửa
            $this->recomputeTotals($payslip);
            $payslip->save();
        }

        return $payslip->fresh();
    }

    private function recomputeTotals(Payslip $payslip): void
    {
        $gross = bcadd(
            $payslip->base_salary,
            bcadd(
                $payslip->allowances,
                bcadd(
                    $payslip->overtime_pay,
                    bcadd($payslip->commission_amount, $payslip->other_earnings, 2),
                    2,
                ),
                2,
            ),
            2,
        );
        $payslip->gross_salary = $gross;

        $deduction = bcadd(
            $payslip->personal_tax,
            bcadd(
                $payslip->social_insurance,
                bcadd(
                    $payslip->health_insurance,
                    bcadd(
                        $payslip->unemployment_insurance,
                        bcadd($payslip->advance_deduction, $payslip->other_deduction, 2),
                        2,
                    ),
                    2,
                ),
                2,
            ),
            2,
        );
        $payslip->total_deduction = $deduction;
        $payslip->net_salary = bcsub($gross, $deduction, 2);
    }
}
