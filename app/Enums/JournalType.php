<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Phân loại bút toán sổ cái (Ledger Entry / Voucher).
 *
 * Tương ứng với các loại chứng từ kế toán phổ biến tại VN:
 *   - PC: Phiếu Chi
 *   - PT: Phiếu Thu
 *   - BK: Bút toán kết chuyển / Điều chỉnh
 */
enum JournalType: string
{
    case PAYMENT_IN = 'PAYMENT_IN';     // Phiếu thu (PT)
    case PAYMENT_OUT = 'PAYMENT_OUT';   // Phiếu chi (PC)
    case JOURNAL = 'JOURNAL';           // Bút toán điều chỉnh (BK)
    case OPENING = 'OPENING';           // Số dư đầu kỳ
    case CLOSING = 'CLOSING';           // Số dư cuối kỳ

    public function label(): string
    {
        return match ($this) {
            self::PAYMENT_IN => 'Phiếu thu (PT)',
            self::PAYMENT_OUT => 'Phiếu chi (PC)',
            self::JOURNAL => 'Bút toán điều chỉnh (BK)',
            self::OPENING => 'Số dư đầu kỳ',
            self::CLOSING => 'Số dư cuối kỳ',
        };
    }
}