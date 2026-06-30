<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Phân loại Nợ / Có trong bút toán kép (double-entry).
 */
enum EntryDC: string
{
    case DEBIT = 'DEBIT';    // Nợ
    case CREDIT = 'CREDIT';  // Có

    public function label(): string
    {
        return match ($this) {
            self::DEBIT => 'Nợ',
            self::CREDIT => 'Có',
        };
    }
}