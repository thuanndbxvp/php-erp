<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại hình làm việc của nhân viên.
 *
 * - FULLTIME    : Nhân viên chính thức, lương tháng
 * - PARTTIME    : Nhân viên thời vụ / bán thời gian
 * - CONTRACTOR  : Cộng tác viên / hợp đồng theo dự án
 * - INTERN      : Thực tập sinh
 * - COMMISSION_ONLY : Chỉ nhận hoa hồng (sales không lương cứng)
 */
enum EmployeeType: string
{
    case FULLTIME = 'FULLTIME';
    case PARTTIME = 'PARTTIME';
    case CONTRACTOR = 'CONTRACTOR';
    case INTERN = 'INTERN';
    case COMMISSION_ONLY = 'COMMISSION_ONLY';

    public function label(): string
    {
        return match ($this) {
            self::FULLTIME => 'Nhân viên chính thức',
            self::PARTTIME => 'Bán thời gian',
            self::CONTRACTOR => 'Cộng tác viên / Hợp đồng',
            self::INTERN => 'Thực tập sinh',
            self::COMMISSION_ONLY => 'Chỉ nhận hoa hồng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FULLTIME, self::COMMISSION_ONLY => 'success',
            self::PARTTIME => 'info',
            self::CONTRACTOR => 'warning',
            self::INTERN => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FULLTIME => 'heroicon-m-briefcase',
            self::PARTTIME => 'heroicon-m-clock',
            self::CONTRACTOR => 'heroicon-m-document-text',
            self::INTERN => 'heroicon-m-academic-cap',
            self::COMMISSION_ONLY => 'heroicon-m-banknotes',
        };
    }
}
