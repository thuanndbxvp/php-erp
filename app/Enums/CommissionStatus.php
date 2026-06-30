<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái hoa hồng phát sinh.
 *
 * - PENDING   : Mới sinh, chưa chốt kỳ
 * - APPROVED  : Đã duyệt, sẵn sàng chi trả
 * - PAID      : Đã thanh toán (gộp qua payroll hoặc chi riêng)
 * - REVERSED  : Bị huỷ (do đơn bị huỷ / điều chỉnh giảm)
 * - CANCELLED : Bị huỷ bỏ
 */
enum CommissionStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case PAID = 'PAID';
    case REVERSED = 'REVERSED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ chốt',
            self::APPROVED => 'Đã duyệt',
            self::PAID => 'Đã thanh toán',
            self::REVERSED => 'Đã đảo ngược',
            self::CANCELLED => 'Đã huỷ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::PAID => 'success',
            self::REVERSED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::PAID,
            self::REVERSED,
            self::CANCELLED,
        ], true);
    }
}
