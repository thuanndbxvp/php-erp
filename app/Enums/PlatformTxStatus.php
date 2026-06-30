<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái một giao dịch tiền từ sàn TMĐT (Shopee, Lazada, Tiki...).
 *
 *   PENDING   → khách đã thanh toán trên sàn, sàn CHƯA chuyển cho shop
 *               (đang "nằm" trên Clearing Account)
 *   SETTLED   → sàn đã chuyển tiền thực nhận (sau khi trừ phí)
 *   DISPUTED  → đang tranh chấp (khiếu nại, chargeback, refund treo...)
 */
enum PlatformTxStatus: string
{
    case PENDING = 'PENDING';
    case SETTLED = 'SETTLED';
    case DISPUTED = 'DISPUTED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ sàn thanh toán',
            self::SETTLED => 'Đã quyết toán',
            self::DISPUTED => 'Tranh chấp',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SETTLED => 'success',
            self::DISPUTED => 'danger',
        };
    }
}