<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái đối soát của giao dịch ngân hàng với Payment.
 */
enum ReconStatus: string
{
    case UNRECONCILED = 'UNRECONCILED'; // Chưa đối soát
    case MATCHED = 'MATCHED';           // Đã khớp Payment
    case DISPUTED = 'DISPUTED';         // Đang tranh chấp

    public function label(): string
    {
        return match ($this) {
            self::UNRECONCILED => 'Chưa đối soát',
            self::MATCHED => 'Đã khớp',
            self::DISPUTED => 'Tranh chấp',
        };
    }
}