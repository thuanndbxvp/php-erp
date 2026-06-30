<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái vòng đời của một Payment.
 *
 *   PENDING    → vừa ghi nhận, chưa áp dụng cho invoice nào
 *   APPLIED    → đã match với ít nhất một invoice (có PaymentApplication)
 *   FAILED     → giao dịch thất bại (bank reject, ví hết hạn mức...)
 *   REFUNDED   → đã hoàn tiền (đối với Payment CUSTOMER hoặc refund NCC)
 *   CANCELLED  → hủy bỏ trước khi xử lý
 */
enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case APPLIED = 'APPLIED';
    case FAILED = 'FAILED';
    case REFUNDED = 'REFUNDED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ xử lý',
            self::APPLIED => 'Đã áp dụng',
            self::FAILED => 'Thất bại',
            self::REFUNDED => 'Đã hoàn tiền',
            self::CANCELLED => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPLIED => 'success',
            self::FAILED => 'danger',
            self::REFUNDED => 'info',
            self::CANCELLED => 'gray',
        };
    }
}