<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Hình thức trả lương cho nhân viên.
 *
 * - MONTHLY         : Lương tháng cố định (nhân viên chính thức)
 * - HOURLY          : Lương theo giờ công (partime / thử việc)
 * - PIECE_RATE      : Lương khoán sản phẩm
 * - COMMISSION_ONLY : Chỉ nhận hoa hồng, không có lương cứng
 */
enum SalaryType: string
{
    case MONTHLY = 'MONTHLY';
    case HOURLY = 'HOURLY';
    case PIECE_RATE = 'PIECE_RATE';
    case COMMISSION_ONLY = 'COMMISSION_ONLY';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Lương tháng',
            self::HOURLY => 'Lương theo giờ',
            self::PIECE_RATE => 'Lương khoán sản phẩm',
            self::COMMISSION_ONLY => 'Chỉ hoa hồng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MONTHLY => 'primary',
            self::HOURLY => 'info',
            self::PIECE_RATE => 'warning',
            self::COMMISSION_ONLY => 'success',
        };
    }
}
