<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum loại biến động tồn kho.
 *
 * Quy ước số lượng:
 *  - Số dương: NHẬP kho
 *  - Số âm: XUẤT kho
 */
enum MovementType: string
{
    case PURCHASE = 'PURCHASE';
    case SALE = 'SALE';
    case ADJUSTMENT = 'ADJUSTMENT';
    case TRANSFER = 'TRANSFER';
    case RETURN_IN = 'RETURN_IN';
    case RETURN_OUT = 'RETURN_OUT';
    case DAMAGE = 'DAMAGE';

    /**
     * Nhãn tiếng Việt dùng cho UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Nhập kho từ mua hàng',
            self::SALE => 'Xuất kho bán hàng',
            self::ADJUSTMENT => 'Điều chỉnh kiểm kê',
            self::TRANSFER => 'Chuyển kho',
            self::RETURN_IN => 'Trả lại từ khách',
            self::RETURN_OUT => 'Trả lại nhà cung cấp',
            self::DAMAGE => 'Hàng hỏng/hao hụt',
        };
    }

    /**
     * Dấu mặc định của quantity: +1 (nhập) hay -1 (xuất).
     */
    public function defaultSign(): int
    {
        return match ($this) {
            self::PURCHASE,
            self::RETURN_IN => 1,
            self::SALE,
            self::RETURN_OUT,
            self::DAMAGE => -1,
            self::ADJUSTMENT, self::TRANSFER => 0, // Tuỳ theo chiều
        };
    }
}