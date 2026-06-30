<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại giao dịch ngân hàng thực tế trên sao kê.
 *
 * Quy ước dấu (defaultSign):
 *   - DƯƠNG (+): tiền VÀO tài khoản
 *   - ÂM (-):    tiền RA khỏi tài khoản
 */
enum TxType: string
{
    case DEPOSIT = 'DEPOSIT';         // Nạp tiền mặt vào tài khoản
    case WITHDRAWAL = 'WITHDRAWAL';   // Rút tiền ra
    case TRANSFER_IN = 'TRANSFER_IN'; // Nhận chuyển khoản vào
    case TRANSFER_OUT = 'TRANSFER_OUT'; // Chuyển khoản đi
    case FEE = 'FEE';                 // Phí ngân hàng
    case INTEREST = 'INTEREST';       // Lãi tiết kiệm
    case ADJUSTMENT = 'ADJUSTMENT';   // Điều chỉnh số dư

    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT => 'Nạp tiền',
            self::WITHDRAWAL => 'Rút tiền',
            self::TRANSFER_IN => 'Nhận CK',
            self::TRANSFER_OUT => 'Chuyển CK đi',
            self::FEE => 'Phí ngân hàng',
            self::INTEREST => 'Lãi tiết kiệm',
            self::ADJUSTMENT => 'Điều chỉnh',
        };
    }

    /**
     * Dấu mặc định: +1 = tiền vào, -1 = tiền ra.
     */
    public function defaultSign(): int
    {
        return match ($this) {
            self::DEPOSIT, self::TRANSFER_IN, self::INTEREST => 1,
            self::WITHDRAWAL, self::TRANSFER_OUT, self::FEE => -1,
            self::ADJUSTMENT => 0,
        };
    }
}