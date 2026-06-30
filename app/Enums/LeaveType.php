<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại nghỉ phép của nhân viên.
 *
 * - ANNUAL       : Nghỉ phép năm (có lương)
 * - SICK         : Nghỉ ốm (có lương một phần / không lương tuỳ chính sách)
 * - MATERNITY    : Nghỉ thai sản
 * - PATERNITY    : Nghỉ chăm con nhỏ (bố/mẹ nuôi)
 * - UNPAID       : Nghỉ không lương
 * - BEREAVEMENT  : Nghỉ tang
 * - MARRIAGE     : Nghỉ cưới (bản thân / con)
 * - COMPENSATORY : Nghỉ bù (làm thêm ngày khác)
 */
enum LeaveType: string
{
    case ANNUAL = 'ANNUAL';
    case SICK = 'SICK';
    case MATERNITY = 'MATERNITY';
    case PATERNITY = 'PATERNITY';
    case UNPAID = 'UNPAID';
    case BEREAVEMENT = 'BEREAVEMENT';
    case MARRIAGE = 'MARRIAGE';
    case COMPENSATORY = 'COMPENSATORY';

    public function label(): string
    {
        return match ($this) {
            self::ANNUAL => 'Nghỉ phép năm',
            self::SICK => 'Nghỉ ốm',
            self::MATERNITY => 'Nghỉ thai sản',
            self::PATERNITY => 'Nghỉ chăm con',
            self::UNPAID => 'Nghỉ không lương',
            self::BEREAVEMENT => 'Nghỉ tang',
            self::MARRIAGE => 'Nghỉ cưới',
            self::COMPENSATORY => 'Nghỉ bù',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ANNUAL, self::COMPENSATORY => 'success',
            self::SICK, self::MATERNITY, self::PATERNITY => 'info',
            self::UNPAID => 'gray',
            self::BEREAVEMENT => 'danger',
            self::MARRIAGE => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ANNUAL => 'heroicon-m-sun',
            self::SICK => 'heroicon-m-heart',
            self::MATERNITY => 'heroicon-m-user-plus',
            self::PATERNITY => 'heroicon-m-user-group',
            self::UNPAID => 'heroicon-m-minus-circle',
            self::BEREAVEMENT => 'heroicon-m-flag',
            self::MARRIAGE => 'heroicon-m-heart',
            self::COMPENSATORY => 'heroicon-m-arrow-path',
        };
    }

    /**
     * Có trả lương trong thời gian nghỉ hay không (dùng để tính payslip).
     */
    public function isPaid(): bool
    {
        return in_array($this, [
            self::ANNUAL,
            self::SICK,
            self::MATERNITY,
            self::PATERNITY,
            self::BEREAVEMENT,
            self::MARRIAGE,
            self::COMPENSATORY,
        ], true);
    }
}
