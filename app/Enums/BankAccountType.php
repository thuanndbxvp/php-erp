<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại tài khoản ngân hàng / tiền mặt / ví điện tử / trung gian sàn.
 */
enum BankAccountType: string
{
    case CHECKING = 'CHECKING';            // Tài khoản thanh toán (current account)
    case SAVINGS = 'SAVINGS';              // Tài khoản tiết kiệm
    case PLATFORM_CLEARING = 'PLATFORM_CLEARING'; // Tài khoản trung gian sàn TMĐT
    case WALLET = 'WALLET';                // Ví tiền mặt / Ví điện tử nội bộ

    /**
     * Nhãn tiếng Việt phục vụ UI Filament.
     */
    public function label(): string
    {
        return match ($this) {
            self::CHECKING => 'Tài khoản thanh toán',
            self::SAVINGS => 'Tài khoản tiết kiệm',
            self::PLATFORM_CLEARING => 'Tài khoản trung gian sàn',
            self::WALLET => 'Ví tiền / Ví điện tử',
        };
    }
}