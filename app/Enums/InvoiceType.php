<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại hình hóa đơn bán ra (InvoiceOut).
 */
enum InvoiceType: string
{
    case FPT = 'FPT';          // Hóa đơn điện tử FPT
    case DOMESTIC = 'DOMESTIC'; // Hóa đơn trong nước (giấy)
    case EXPORT = 'EXPORT';     // Hóa đơn xuất khẩu

    public function label(): string
    {
        return match ($this) {
            self::FPT => 'Hóa đơn điện tử FPT',
            self::DOMESTIC => 'Hóa đơn trong nước',
            self::EXPORT => 'Hóa đơn xuất khẩu',
        };
    }
}