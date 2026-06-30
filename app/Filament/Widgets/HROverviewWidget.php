<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\CommissionStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Dashboard Widget: HR Overview.
 *
 * Hiển thị:
 *  - Headcount: tổng NV active (probation + active + on_leave)
 *  - Đang nghỉ hôm nay: count từ Leave (APPROVED overlapping today)
 *  - Chi phí lương tháng này: total_net của PayrollRun tháng hiện tại
 *  - Hoa hồng chờ duyệt: số + tổng tiền commission PENDING
 *  - Đơn nghỉ phép chờ duyệt
 *  - Trạng thái kỳ lương hiện tại
 *
 * Mỗi Stat có URL → tới trang chi tiết tương ứng.
 */
class HROverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2; // Sau Finance widget (sort=0)

    protected int|string|array $columnSpan = 'full';

    private function vnd(float|int|string $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' ₫';
    }

    private function headcount(): array
    {
        $count = Employee::whereIn('status', [
            EmployeeStatus::PROBATION->value,
            EmployeeStatus::ACTIVE->value,
            EmployeeStatus::ON_LEAVE->value,
        ])->count();

        $byStatus = Employee::query()
            ->whereIn('status', [
                EmployeeStatus::PROBATION->value,
                EmployeeStatus::ACTIVE->value,
                EmployeeStatus::ON_LEAVE->value,
            ])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return [
            'total' => $count,
            'active' => $byStatus[EmployeeStatus::ACTIVE->value] ?? 0,
            'probation' => $byStatus[EmployeeStatus::PROBATION->value] ?? 0,
            'on_leave' => $byStatus[EmployeeStatus::ON_LEAVE->value] ?? 0,
        ];
    }

    private function onLeaveToday(): array
    {
        $today = now()->toDateString();
        // Leave APPROVED có start_date <= today <= end_date
        $count = Leave::where('status', LeaveStatus::APPROVED->value)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        return ['count' => $count];
    }

    private function pendingLeaves(): int
    {
        return Leave::where('status', LeaveStatus::PENDING->value)->count();
    }

    private function monthlyPayrollCost(): array
    {
        $run = PayrollRun::where('period_year', now()->year)
            ->where('period_month', now()->month)
            ->first();

        return [
            'status' => $run?->status,
            'total_net' => (float) ($run->total_net ?? 0),
            'total_gross' => (float) ($run->total_gross ?? 0),
            'employee_count' => $run ? Payslip::where('payroll_run_id', $run->id)->count() : 0,
            'run_number' => $run?->run_number,
        ];
    }

    private function pendingCommissions(): array
    {
        $pending = Commission::where('status', CommissionStatus::PENDING->value);
        $count = $pending->count();
        $total = (float) $pending->sum('commission_amount');

        return ['count' => $count, 'total' => $total];
    }

    private function attendanceToday(): array
    {
        $today = now()->toDateString();
        $present = Attendance::where('date', $today)
            ->whereNotIn('status', [\App\Enums\AttendanceStatus::ABSENT->value])
            ->count();
        $absent = Attendance::where('date', $today)
            ->where('status', \App\Enums\AttendanceStatus::ABSENT->value)
            ->count();

        return ['present' => $present, 'absent' => $absent];
    }

    public function getStats(): array
    {
        $hc = $this->headcount();
        $today = $this->onLeaveToday();
        $pendingL = $this->pendingLeaves();
        $payroll = $this->monthlyPayrollCost();
        $comm = $this->pendingCommissions();
        $att = $this->attendanceToday();

        $runNumberLabel = $payroll['run_number'] ?? 'Chưa có';
        $payrollStatusLabel = $payroll['status'] ? $payroll['status']->label() : 'Chưa tạo';
        $payrollStatusColor = match (true) {
            $payroll['status'] === PayrollStatus::PAID => 'success',
            $payroll['status'] === PayrollStatus::APPROVED => 'warning',
            $payroll['status'] === PayrollStatus::PROCESSING => 'info',
            $payroll['status'] === PayrollStatus::CANCELLED => 'danger',
            default => 'gray',
        };

        return [
            Stat::make('👥 Tổng nhân sự', number_format($hc['total']))
                ->description(sprintf(
                    'Active %d · Thử việc %d · Nghỉ phép %d',
                    $hc['active'],
                    $hc['probation'],
                    $hc['on_leave'],
                ))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->url('/admin/employees'),

            Stat::make('🌴 Đang nghỉ hôm nay', number_format($today['count']))
                ->description('Đơn nghỉ APPROVED overlapping today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($today['count'] > 0 ? 'warning' : 'gray')
                ->url('/admin/leaves'),

            Stat::make('⏰ Chấm công hôm nay', sprintf('%d có mặt', $att['present']))
                ->description(sprintf('%d vắng mặt', $att['absent']))
                ->descriptionIcon('heroicon-m-clock')
                ->color($att['absent'] > 0 ? 'warning' : 'success')
                ->url('/admin/attendances'),

            Stat::make('📝 Đơn nghỉ chờ duyệt', number_format($pendingL))
                ->description('Yêu cầu APPROVED / REJECTED')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($pendingL > 0 ? 'warning' : 'gray')
                ->url('/admin/leaves'),

            Stat::make('💰 Quỹ lương T.' . sprintf('%02d', now()->month), $this->vnd($payroll['total_net']))
                ->description(sprintf(
                    '%s · Gross %s · %d NV',
                    $payrollStatusLabel,
                    $this->vnd($payroll['total_gross']),
                    $payroll['employee_count'],
                ))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($payrollStatusColor)
                ->url('/admin/payroll-runs'),

            Stat::make('💵 Hoa hồng chờ duyệt', $this->vnd($comm['total']))
                ->description(sprintf('%d khoản PENDING', $comm['count']))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($comm['count'] > 0 ? 'warning' : 'gray')
                ->url('/admin/commissions'),
        ];
    }
}
