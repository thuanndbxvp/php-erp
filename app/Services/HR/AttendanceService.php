<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Chấm công (Attendance).
 *
 * - Mỗi cặp (employee_id, date) là UNIQUE → dùng updateOrCreate.
 * - Tính tự động work_hours từ check_in/check_out (giả định giờ hành chính).
 * - Status mặc định PRESENT; LATE/EARLY_LEAVE/ABSENT tuỳ policy (đơn giản hoá ở đây).
 *
 * Không đụng vào payroll - chỉ ghi nhận. Tăng ca (OT) là input cho PayslipService.
 */
class AttendanceService
{
    /**
     * Ghi nhận / cập nhật chấm công cho (employee, date).
     *
     * @param  array{
     *     employee_id: int,
     *     date: string,
     *     check_in?: string|null,
     *     check_out?: string|null,
     *     overtime_hours?: float|string,
     *     status?: AttendanceStatus|string,
     *     notes?: string|null,
     * }  $data
     */
    public function record(array $data): Attendance
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            throw ValidationException::withMessages(['employee_id' => 'Thiếu nhân viên.']);
        }
        if (! Employee::where('id', $employeeId)->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Nhân viên không tồn tại.']);
        }

        $date = $data['date'] ?? null;
        if (! $date) {
            throw ValidationException::withMessages(['date' => 'Thiếu ngày chấm công.']);
        }

        $checkIn = $data['check_in'] ?? null;
        $checkOut = $data['check_out'] ?? null;
        $workHours = $this->computeWorkHours($checkIn, $checkOut);
        $otHours = (string) ($data['overtime_hours'] ?? '0');
        if ((float) $otHours < 0) {
            throw ValidationException::withMessages(['overtime_hours' => 'Giờ OT không được âm.']);
        }

        $status = $data['status'] ?? AttendanceStatus::PRESENT->value;
        if ($status instanceof AttendanceStatus) {
            $status = $status->value;
        }

        return DB::transaction(function () use ($data, $employeeId, $date, $checkIn, $checkOut, $workHours, $otHours, $status) {
            return Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $date],
                [
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'work_hours' => $workHours,
                    'overtime_hours' => $otHours,
                    'status' => $status,
                    'notes' => $data['notes'] ?? null,
                ],
            );
        });
    }

    /**
     * Tổng số giờ làm + giờ OT của 1 NV trong khoảng [from, to].
     *
     * @return array{work_hours: string, overtime_hours: string, work_days: int}
     */
    public function summary(Employee $employee, string $fromDate, string $toDate): array
    {
        $records = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get();

        $totalWork = '0';
        $totalOT = '0';
        $workDays = 0;
        foreach ($records as $r) {
            $totalWork = bcadd($totalWork, (string) $r->work_hours, 2);
            $totalOT = bcadd($totalOT, (string) $r->overtime_hours, 2);
            if ($r->status !== AttendanceStatus::ABSENT && (float) $r->work_hours > 0) {
                $workDays++;
            }
        }

        return [
            'work_hours' => $totalWork,
            'overtime_hours' => $totalOT,
            'work_days' => $workDays,
        ];
    }

    /**
     * Tính work_hours từ check_in - check_out (HH:MM 24h).
     * Trả về số giờ (1 chữ số thập phân). Nếu thiếu 1 trong 2 → '0'.
     */
    private function computeWorkHours(?string $in, ?string $out): string
    {
        if (! $in || ! $out) {
            return '0';
        }

        try {
            [$h1, $m1] = array_map('intval', explode(':', $in));
            [$h2, $m2] = array_map('intval', explode(':', $out));
            $start = $h1 * 60 + $m1;
            $end = $h2 * 60 + $m2;
            $diff = $end - $start;
            if ($diff <= 0) {
                return '0';
            }

            return number_format($diff / 60, 2, '.', '');
        } catch (\Throwable) {
            return '0';
        }
    }
}
