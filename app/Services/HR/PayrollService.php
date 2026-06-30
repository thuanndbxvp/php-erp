<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\AttendanceStatus;
use App\Enums\CommissionStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveType;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payslip;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Engine Tính lương (Payroll Service).
 *
 * Quy trình:
 *  1. Tạo PayrollRun (DRAFT) cho 1 (year, month).
 *  2. compute(): duyệt từng Employee payable, snapshot info, tính toán:
 *       - base_salary / allowances / overtime_pay / commission_amount / other_earnings
 *       - gross_salary = tổng trên
 *       - personal_tax (PIT - tính theo biểu lũy tiến VN, giản lược)
 *       - BHXH/BHYT/BHTN (10.5% tổng = 8% BHXH + 1.5% BHYT + 1% BHTN, NV đóng)
 *       - advance_deduction / other_deduction
 *       - total_deduction = tổng
 *       - net_salary = gross - deduction
 *  3. approve() / pay() / cancel(): state machine.
 *  4. Khi pay() → mark tất cả Commission liên quan thành PAID.
 *
 * Lưu ý: thuế PIT dùng biểu lũy tiến VN (5 / 10 / 15 / 20 / 25 / 30 / 35%) với
 * giảm trừ gia cảnh bản thân 11 triệu + 4.4 triệu / người phụ thuộc.
 * Các hằng số có thể kéo ra config sau này.
 */
class PayrollService
{
    /** Giảm trừ gia cảnh bản thân (VN hiện hành). */
    private const PERSONAL_DEDUCTION = '11000000';

    /** Giảm trừ mỗi người phụ thuộc. */
    private const DEPENDENT_DEDUCTION = '4400000';

    /** Tỷ lệ BHXH NV đóng (8%). */
    private const SI_RATE = '0.08';

    /** Tỷ lệ BHYT NV đóng (1.5%). */
    private const HI_RATE = '0.015';

    /** Tỷ lệ BHTN NV đóng (1%). */
    private const UI_RATE = '0.01';

    /** Số ngày công chuẩn / tháng (dùng quy đổi công). */
    private const STANDARD_WORK_DAYS = 26;

    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * Tạo mới PayrollRun ở trạng thái DRAFT (chưa tính).
     *
     * @param  array{
     *     period_year: int,
     *     period_month: int,
     *     payment_date?: string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function createDraft(array $data, User $actor): PayrollRun
    {
        $year = (int) ($data['period_year'] ?? now()->year);
        $month = (int) ($data['period_month'] ?? now()->month);

        if ($month < 1 || $month > 12) {
            throw ValidationException::withMessages(['period_month' => 'Tháng không hợp lệ (1-12).']);
        }

        // Unique (year, month) - đã có index
        $exists = PayrollRun::where('period_year', $year)
            ->where('period_month', $month)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'period' => "Kỳ lương {$month}/{$year} đã tồn tại.",
            ]);
        }

        $runNumber = $this->orderNumber->nextPayrollRunNumber($year, $month);
        $label = sprintf('Tháng %02d/%04d', $month, $year);
        [$start, $end] = $this->periodBounds($year, $month);

        return DB::transaction(function () use ($year, $month, $data, $actor, $runNumber, $label, $start, $end) {
            return PayrollRun::create([
                'run_number' => $runNumber,
                'period_month' => $month,
                'period_year' => $year,
                'period_label' => $label,
                'period_start_date' => $start,
                'period_end_date' => $end,
                'payment_date' => $data['payment_date'] ?? null,
                'status' => PayrollStatus::DRAFT->value,
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Tính toán payslips cho toàn bộ kỳ (ghi đè nếu đã có).
     * Trả về Support\Collection các Payslip đã sinh.
     *
     * @return \Illuminate\Support\Collection<int, Payslip>
     */
    public function compute(PayrollRun $run): \Illuminate\Support\Collection
    {
        if ($run->status === PayrollStatus::PAID) {
            throw ValidationException::withMessages([
                'status' => 'Kỳ lương đã chi trả, không thể tính lại.',
            ]);
        }

        $run->status = PayrollStatus::PROCESSING;
        $run->save();

        // Lấy danh sách NV có thể trả lương (không tính TERMINATED / SUSPENDED)
        $employees = Employee::whereIn('status', [
            EmployeeStatus::PROBATION->value,
            EmployeeStatus::ACTIVE->value,
            EmployeeStatus::ON_LEAVE->value,
        ])
            ->orderBy('id')
            ->get();

        $payslips = DB::transaction(function () use ($run, $employees) {
            // Xoá payslip cũ (nếu recompute)
            Payslip::where('payroll_run_id', $run->id)->delete();

            $created = collect();
            foreach ($employees as $employee) {
                $payslip = $this->computeOneEmployee($run, $employee);
                if ($payslip) {
                    $created->push($payslip);
                }
            }

            // Cập nhật tổng cuối kỳ
            $run->total_gross = (string) $created->sum('gross_salary');
            $run->total_deduction = (string) $created->sum('total_deduction');
            $run->total_net = (string) $created->sum('net_salary');
            // Trở về DRAFT để user review trước khi approve
            $run->status = PayrollStatus::DRAFT;
            $run->save();

            return $created;
        });

        return $payslips;
    }

    /**
     * Tính payslip cho 1 NV cụ thể.
     */
    public function computeOneEmployee(PayrollRun $run, Employee $employee): ?Payslip
    {
        $from = $run->period_start_date->toDateString();
        $to = $run->period_end_date->toDateString();

        // ----- Thu nhập (EARNINGS) -----
        $baseSalary = $this->resolveBaseSalary($employee, $from, $to);
        if ((float) $baseSalary <= 0) {
            // NV có salary_type=COMMISSION_ONLY không có base → vẫn có thể có commission
            // Nhưng nếu cũng không có commission → bỏ qua
        }

        // OT pay: tổng overtime_hours × (base_salary / STANDARD_WORK_DAYS / 8 × 1.5)
        $otHours = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->sum('overtime_hours');
        $hourlyRate = bcdiv(
            bcdiv((string) $baseSalary, (string) self::STANDARD_WORK_DAYS, 4),
            '8',
            4,
        );
        $overtimePay = bcmul((string) $otHours, bcmul($hourlyRate, '1.5', 4), 2);

        // Leaves trong kỳ
        $leaves = Leave::where('employee_id', $employee->id)
            ->where('status', \App\Enums\LeaveStatus::APPROVED->value)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                    ->orWhereBetween('end_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('start_date', '<=', $from)->where('end_date', '>=', $to);
                    });
            })
            ->get();

        $paidLeaveDays = '0';
        $unpaidLeaveDays = '0';
        foreach ($leaves as $lv) {
            $days = $this->overlapDays($lv->start_date->toDateString(), $lv->end_date->toDateString(), $from, $to);
            if (in_array($lv->leave_type, [LeaveType::UNPAID->value], true)) {
                $unpaidLeaveDays = bcadd($unpaidLeaveDays, (string) $days, 2);
            } else {
                $paidLeaveDays = bcadd($paidLeaveDays, (string) $days, 2);
            }
        }

        // Commission: gộp APPROVED trong kỳ
        $commissionAmount = Commission::where('employee_id', $employee->id)
            ->whereBetween('earned_date', [$from, $to])
            ->where('status', CommissionStatus::APPROVED->value)
            ->sum('commission_amount');

        // Allowances + other_earnings: để user nhập tay ở form, mặc định 0
        $allowances = '0';
        $otherEarnings = '0';

        $gross = bcadd(
            bcadd($baseSalary, $allowances, 2),
            bcadd($overtimePay, bcadd((string) $commissionAmount, $otherEarnings, 2), 2),
            2,
        );

        // ----- Khấu trừ (DEDUCTIONS) -----
        // BHXH/BHYT/BHTN chỉ áp cho MONTHLY + full-time
        $si = '0';
        $hi = '0';
        $ui = '0';
        if ($employee->salary_type === \App\Enums\SalaryType::MONTHLY
            && $employee->employee_type === \App\Enums\EmployeeType::FULLTIME) {
            $si = bcmul($baseSalary, self::SI_RATE, 2);
            $hi = bcmul($baseSalary, self::HI_RATE, 2);
            $ui = bcmul($baseSalary, self::UI_RATE, 2);
        }

        // PIT (Personal Income Tax): biểu lũy tiến VN
        $taxableIncome = $this->computeTaxableIncome($gross, $employee);
        $personalTax = $this->computePit($taxableIncome);

        $advanceDeduction = '0';
        $otherDeduction = '0';

        $totalDeduction = bcadd(
            bcadd($personalTax, bcadd($si, bcadd($hi, $ui, 2), 2), 2),
            bcadd($advanceDeduction, $otherDeduction, 2),
            2,
        );

        $netSalary = bcsub($gross, $totalDeduction, 2);

        // Số ngày công thực tế
        $workDays = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->whereNotIn('status', [AttendanceStatus::ABSENT->value])
            ->count();

        return Payslip::create([
            'payslip_number' => $this->orderNumber->nextPayslipNumber($run->period_year, $run->period_month),
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_code_snapshot' => $employee->employee_code,
            'employee_name_snapshot' => $employee->full_name,
            'department_name_snapshot' => $employee->department?->name,
            'position_name_snapshot' => $employee->position?->title,
            'base_salary' => $baseSalary,
            'allowances' => $allowances,
            'overtime_pay' => $overtimePay,
            'commission_amount' => (string) $commissionAmount,
            'other_earnings' => $otherEarnings,
            'gross_salary' => $gross,
            'personal_tax' => $personalTax,
            'social_insurance' => $si,
            'health_insurance' => $hi,
            'unemployment_insurance' => $ui,
            'advance_deduction' => $advanceDeduction,
            'other_deduction' => $otherDeduction,
            'total_deduction' => $totalDeduction,
            'net_salary' => $netSalary,
            'work_days' => (string) $workDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'overtime_hours' => (string) $otHours,
            'status' => 'DRAFT',
        ]);
    }

    /**
     * Approve kỳ lương (DRAFT → APPROVED). Toàn bộ payslip chuyển sang APPROVED.
     */
    public function approve(PayrollRun $run, User $actor): PayrollRun
    {
        if ($run->status === PayrollStatus::PAID) {
            throw ValidationException::withMessages(['status' => 'Kỳ lương đã chi trả.']);
        }
        $run->status = PayrollStatus::APPROVED;
        $run->approved_by = $actor->id;
        $run->approved_at = now();
        $run->save();

        Payslip::where('payroll_run_id', $run->id)->update(['status' => 'APPROVED']);

        return $run->fresh();
    }

    /**
     * Pay (chi trả): APPROVED → PAID. Mark hết commission liên quan → PAID.
     */
    public function pay(PayrollRun $run, User $actor, ?string $paymentDate = null): PayrollRun
    {
        if ($run->status !== PayrollStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Kỳ lương phải ở trạng thái APPROVED trước khi chi trả.',
            ]);
        }
        $payDate = $paymentDate ?? now()->toDateString();

        return DB::transaction(function () use ($run, $actor, $payDate) {
            $run->status = PayrollStatus::PAID;
            $run->paid_by = $actor->id;
            $run->paid_at = now();
            $run->payment_date = $payDate;
            $run->save();

            // Update payslips
            Payslip::where('payroll_run_id', $run->id)->update([
                'status' => 'PAID',
                'payment_date' => $payDate,
            ]);

            // Mark commissions PAID
            $payslips = Payslip::where('payroll_run_id', $run->id)->get();
            foreach ($payslips as $ps) {
                $commissions = Commission::where('employee_id', $ps->employee_id)
                    ->where('status', CommissionStatus::APPROVED->value)
                    ->whereNull('payslip_id')
                    ->get();
                foreach ($commissions as $c) {
                    $this->commissionService->markPaid($c, $ps->id);
                }
            }

            return $run->fresh();
        });
    }

    public function cancel(PayrollRun $run, ?string $reason = null): PayrollRun
    {
        if ($run->status === PayrollStatus::PAID) {
            throw ValidationException::withMessages(['status' => 'Kỳ lương đã chi trả, không thể huỷ.']);
        }
        $run->status = PayrollStatus::CANCELLED;
        if ($reason) {
            $run->notes = ($run->notes ?? '') . "\n[CANCELLED] " . $reason;
        }
        $run->save();

        // Cập nhật payslip
        Payslip::where('payroll_run_id', $run->id)->update(['status' => 'CANCELLED']);

        // Free lại commission (chuyển về APPROVED, gỡ payslip_id)
        Commission::whereIn('payslip_id', Payslip::where('payroll_run_id', $run->id)->pluck('id'))
            ->update(['status' => CommissionStatus::APPROVED->value, 'payslip_id' => null, 'paid_at' => null]);

        return $run->fresh();
    }

    /**
     * Tính base_salary cho kỳ — nếu NV vào/nghỉ giữa kỳ thì prorate theo ngày.
     */
    private function resolveBaseSalary(Employee $employee, string $from, string $to): string
    {
        $start = max($employee->start_date?->toDateString() ?? $from, $from);
        $end = min($employee->end_date?->toDateString() ?? $to, $to);
        if ($start > $end) {
            return '0';
        }

        $monthlyBase = (string) $employee->base_salary;
        // Prorate nếu vào/nghỉ giữa kỳ
        $daysInMonth = (int) date('t', strtotime($from));
        $workedDays = (int) ((new \DateTimeImmutable($end))->diff(new \DateTimeImmutable($start))->days + 1);
        if ($workedDays >= $daysInMonth) {
            return $monthlyBase;
        }

        return bcmul(
            $monthlyBase,
            bcdiv((string) $workedDays, (string) $daysInMonth, 4),
            2,
        );
    }

    /**
     * Thu nhập chịu thuế = gross - (BHXH + BHYT + BHTN) - giảm trừ gia cảnh.
     */
    private function computeTaxableIncome(string $gross, Employee $employee): string
    {
        $si = '0';
        $hi = '0';
        $ui = '0';
        if ($employee->salary_type === \App\Enums\SalaryType::MONTHLY
            && $employee->employee_type === \App\Enums\EmployeeType::FULLTIME) {
            $si = bcmul($gross, self::SI_RATE, 2);
            $hi = bcmul($gross, self::HI_RATE, 2);
            $ui = bcmul($gross, self::UI_RATE, 2);
        }

        $insurances = bcadd($si, bcadd($hi, $ui, 2), 2);
        $deduction = bcadd(
            self::PERSONAL_DEDUCTION,
            bcmul(self::DEPENDENT_DEDUCTION, (string) max(0, (int) $employee->dependents_count), 0),
            2,
        );

        $taxable = bcsub(bcsub($gross, $insurances, 2), $deduction, 2);

        return bccomp($taxable, '0', 2) < 0 ? '0' : $taxable;
    }

    /**
     * Tính PIT theo biểu lũy tiến VN (áp dụng cho thu nhập thường).
     * Mỗi phần thu nhập chịu thuế vượt bậc trước được tính theo rate của bậc đó.
     *
     * Bậc   | Thu nhập chịu thuế / tháng | Rate
     * 1     | đến 5tr                     | 5%
     * 2     | >5tr - 10tr                 | 10%
     * 3     | >10tr - 18tr                | 15%
     * 4     | >18tr - 32tr                | 20%
     * 5     | >32tr - 52tr                | 25%
     * 6     | >52tr - 80tr                | 30%
     * 7     | >80tr                       | 35%
     */
    private function computePit(string $taxableIncome): string
    {
        $brackets = [
            ['threshold' => 5000000, 'rate' => '0.05'],
            ['threshold' => 10000000, 'rate' => '0.10'],
            ['threshold' => 18000000, 'rate' => '0.15'],
            ['threshold' => 32000000, 'rate' => '0.20'],
            ['threshold' => 52000000, 'rate' => '0.25'],
            ['threshold' => 80000000, 'rate' => '0.30'],
            ['threshold' => PHP_INT_MAX, 'rate' => '0.35'],
        ];

        $tax = '0';
        $prev = '0';
        $remaining = $taxableIncome;
        foreach ($brackets as $b) {
            $band = bcsub((string) $b['threshold'], $prev, 2);
            if ((float) $remaining <= 0) {
                break;
            }
            $portion = bccomp($remaining, $band, 2) >= 0 ? $band : $remaining;
            $tax = bcadd($tax, bcmul($portion, (string) $b['rate'], 2), 2);
            $remaining = bcsub($remaining, $portion, 2);
            $prev = (string) $b['threshold'];
        }

        return $tax;
    }

    /**
     * @return array{0: string, 1: string} [start, end] (Y-m-d)
     */
    private function periodBounds(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        return [$start, $end];
    }

    /**
     * Số ngày overlap giữa [leaveStart..leaveEnd] và [periodStart..periodEnd].
     */
    private function overlapDays(string $ls, string $le, string $ps, string $pe): int
    {
        $start = max($ls, $ps);
        $end = min($le, $pe);
        if ($start > $end) {
            return 0;
        }

        return (int) ((new \DateTimeImmutable($end))->diff(new \DateTimeImmutable($start))->days + 1);
    }
}
