<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái làm việc của nhân viên (State Machine).
 *
 * - PROBATION  : Đang thử việc
 * - ACTIVE     : Đang làm việc
 * - ON_LEAVE   : Đang nghỉ phép dài hạn (thai sản, nghỉ ốm...)
 * - SUSPENDED  : Tạm đình chỉ
 * - TERMINATED : Đã nghỉ việc
 */
enum EmployeeStatus: string
{
    case PROBATION = 'PROBATION';
    case ACTIVE = 'ACTIVE';
    case ON_LEAVE = 'ON_LEAVE';
    case SUSPENDED = 'SUSPENDED';
    case TERMINATED = 'TERMINATED';

    public function label(): string
    {
        return match ($this) {
            self::PROBATION => 'Đang thử việc',
            self::ACTIVE => 'Đang làm việc',
            self::ON_LEAVE => 'Đang nghỉ phép',
            self::SUSPENDED => 'Tạm đình chỉ',
            self::TERMINATED => 'Đã nghỉ việc',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PROBATION => 'warning',
            self::ACTIVE => 'success',
            self::ON_LEAVE => 'info',
            self::SUSPENDED => 'danger',
            self::TERMINATED => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PROBATION => 'heroicon-m-shield-exclamation',
            self::ACTIVE => 'heroicon-m-check-circle',
            self::ON_LEAVE => 'heroicon-m-calendar-days',
            self::SUSPENDED => 'heroicon-m-pause-circle',
            self::TERMINATED => 'heroicon-m-x-circle',
        };
    }

    /**
     * Có tính vào bảng lương hay không. Chỉ những trạng thái "đang làm"
     * (kể cả thử việc / nghỉ phép có lương) mới có payslip.
     */
    public function isPayable(): bool
    {
        return in_array($this, [
            self::PROBATION,
            self::ACTIVE,
            self::ON_LEAVE,
        ], true);
    }

    public function isTerminal(): bool
    {
        return $this === self::TERMINATED;
    }
}
