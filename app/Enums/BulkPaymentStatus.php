<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái vòng đời BulkPayment (gom nhiều Payment / nhiều Invoice vào 1 phiếu).
 *
 *   PENDING     → vừa tạo, chờ duyệt
 *   PROCESSING  → đang phân bổ tiền vào các Invoice
 *   COMPLETED   → đã phân bổ xong
 *   FAILED      → xảy ra lỗi khi xử lý (lệch tiền, sai NCC...)
 */
enum BulkPaymentStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ xử lý',
            self::PROCESSING => 'Đang xử lý',
            self::COMPLETED => 'Hoàn tất',
            self::FAILED => 'Thất bại',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
        };
    }
}