<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Đơn nghỉ phép (Leave).
 *
 * - Tính total_days (inclusive cả 2 đầu, trừ T7/CN: tuỳ policy đơn giản hoá = calendar day).
 * - State machine: DRAFT → PENDING → APPROVED/REJECTED, PENDING/APPROVED → CANCELLED.
 * - Duyệt (approve) / Từ chối (reject): ghi approved_by + approved_at + approver_notes.
 * - Khi APPROVED + leave_type UNPAID → flag để payroll trừ ngày công.
 */
class LeaveService
{
    /**
     * Map state transitions hợp lệ (LeaveStatus string).
     */
    private const ALLOWED_TRANSITIONS = [
        'DRAFT' => ['PENDING', 'CANCELLED'],
        'PENDING' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['CANCELLED'],
        // REJECTED / CANCELLED = terminal
    ];

    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data): Leave
    {
        return $this->persist(null, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Leave $leave, array $data): Leave
    {
        if ($leave->status === LeaveStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Đơn đã duyệt không thể sửa. Huỷ trước (CANCELLED) rồi tạo lại.',
            ]);
        }
        if (in_array($leave->status, [LeaveStatus::REJECTED, LeaveStatus::CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => "Đơn ở trạng thái terminal [{$leave->status->label()}] không thể sửa.",
            ]);
        }

        return $this->persist($leave, $data);
    }

    public function approve(Leave $leave, User $approver, ?string $notes = null): Leave
    {
        $this->assertTransition($leave->status, LeaveStatus::APPROVED);
        $leave->status = LeaveStatus::APPROVED;
        $leave->approved_by = $approver->id;
        $leave->approved_at = now();
        if ($notes !== null) {
            $leave->approver_notes = $notes;
        }
        $leave->save();

        return $leave->fresh();
    }

    public function reject(Leave $leave, User $approver, string $notes): Leave
    {
        $this->assertTransition($leave->status, LeaveStatus::REJECTED);
        $leave->status = LeaveStatus::REJECTED;
        $leave->approved_by = $approver->id;
        $leave->approved_at = now();
        $leave->approver_notes = $notes;
        $leave->save();

        return $leave->fresh();
    }

    public function cancel(Leave $leave, ?string $notes = null): Leave
    {
        $this->assertTransition($leave->status, LeaveStatus::CANCELLED);
        $leave->status = LeaveStatus::CANCELLED;
        if ($notes !== null) {
            $leave->approver_notes = ($leave->approver_notes ?? '') . "\n[CANCELLED] " . $notes;
        }
        $leave->save();

        return $leave->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(?Leave $leave, array $data): Leave
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        if ($employeeId <= 0 || ! Employee::where('id', $employeeId)->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Nhân viên không hợp lệ.']);
        }

        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        if (! $startDate || ! $endDate) {
            throw ValidationException::withMessages([
                'start_date' => 'Thiếu ngày bắt đầu / kết thúc.',
            ]);
        }
        if ($startDate > $endDate) {
            throw ValidationException::withMessages([
                'end_date' => 'Ngày kết thúc phải >= ngày bắt đầu.',
            ]);
        }

        $leaveType = $data['leave_type'] ?? LeaveType::ANNUAL->value;
        if ($leaveType instanceof LeaveType) {
            $leaveType = $leaveType->value;
        }

        $totalDays = $this->computeTotalDays($startDate, $endDate);

        $leaveNumber = $leave?->leave_number ?? $this->orderNumber->nextLeaveNumber();

        return DB::transaction(function () use ($leave, $data, $leaveNumber, $employeeId, $leaveType, $startDate, $endDate, $totalDays) {
            $payload = [
                'leave_number' => $leaveNumber,
                'employee_id' => $employeeId,
                'leave_type' => $leaveType,
                'reason' => $data['reason'] ?? null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => (string) $totalDays,
                'status' => $data['status'] ?? LeaveStatus::PENDING->value,
                'approver_notes' => $data['approver_notes'] ?? null,
            ];

            if ($leave) {
                $leave->fill($payload);
                $leave->save();

                return $leave->fresh();
            }

            return Leave::create($payload);
        });
    }

    private function computeTotalDays(string $startDate, string $endDate): int
    {
        try {
            $start = new \DateTimeImmutable($startDate);
            $end = new \DateTimeImmutable($endDate);
            $diff = $start->diff($end);
            // Inclusive (cả 2 đầu) - giản lược, không trừ T7/CN.
            return (int) $diff->days + 1;
        } catch (\Throwable) {
            return 1;
        }
    }

    private function assertTransition(LeaveStatus $from, LeaveStatus $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from->value] ?? [];
        if (! in_array($to->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển đơn nghỉ phép từ [{$from->label()}] sang [{$to->label()}].",
            ]);
        }
    }
}
