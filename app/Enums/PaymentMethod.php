<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Phương thức thanh toán được sử dụng để ghi nhận Payment.
 */
enum PaymentMethod: string
{
    case CASH = 'CASH';                   // Tiền mặt
    case BANK_TRANSFER = 'BANK_TRANSFER'; // Chuyển khoản ngân hàng
    case QR_PAY = 'QR_PAY';               // Quét QR (VietQR, VNPay QR...)
    case E_WALLET = 'E_WALLET';           // Ví điện tử (Momo, ZaloPay, Moca...)
    case CARD = 'CARD';                   // Quẹt thẻ POS
    case PLATFORM = 'PLATFORM';           // Qua sàn TMĐT (Shopee, Lazada, Tiki)

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tiền mặt',
            self::BANK_TRANSFER => 'Chuyển khoản NH',
            self::QR_PAY => 'QR / VietQR',
            self::E_WALLET => 'Ví điện tử',
            self::CARD => 'Quẹt thẻ',
            self::PLATFORM => 'Qua sàn TMĐT',
        };
    }
}