<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái chấm công hàng ngày của nhân viên.
 *
 * - PRESENT        : Đi làm đủ
 * - LATE           : Đi muộn
 * - EARLY_LEAVE    : Về sớm
 * - ABSENT         : Vắng mặt không phép
 * - ON_LEAVE       : Nghỉ phép (có đơn được duyệt)
 * - HOLIDAY        : Ngày lễ / nghỉ tuần theo lịch
 * - WORK_FROM_HOME : Làm việc tại nhà
 * - OVERTIME       : Có tăng ca (kèm đi làm bình thường)
 */
enum AttendanceStatus: string
{
    case PRESENT = 'PRESENT';
    case LATE = 'LATE';
    case EARLY_LEAVE = 'EARLY_LEAVE';
    case ABSENT = 'ABSENT';
    case ON_LEAVE = 'ON_LEAVE';
    case HOLIDAY = 'HOLIDAY';
    case WORK_FROM_HOME = 'WORK_FROM_HOME';
    case OVERTIME = 'OVERTIME';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Đi làm',
            self::LATE => 'Đi muộn',
            self::EARLY_LEAVE => 'Về sớm',
            self::ABSENT => 'Vắng mặt',
            self::ON_LEAVE => 'Nghỉ phép',
            self::HOLIDAY => 'Ngày lễ',
            self::WORK_FROM_HOME => 'Làm việc tại nhà',
            self::OVERTIME => 'Có tăng ca',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRESENT, self::OVERTIME => 'success',
            self::LATE, self::EARLY_LEAVE => 'warning',
            self::ABSENT => 'danger',
            self::ON_LEAVE, self::HOLIDAY => 'info',
            self::WORK_FROM_HOME => 'primary',
        };
    }

    /**
     * Có tính công hay không.
     * PRESENT / LATE / OVERTIME / WORK_FROM_HOME / EARLY_LEAVE → 1 công.
     * ON_LEAVE có lương (LOAN/ANNUAL) → 1 công; không lương → 0.
     * ABSENT, HOLIDAY → 0 công.
     */
    public function countsAsWorkday(): bool
    {
        return in_array($this, [
            self::PRESENT,
            self::LATE,
            self::EARLY_LEAVE,
            self::WORK_FROM_HOME,
            self::OVERTIME,
        ], true);
    }
}
