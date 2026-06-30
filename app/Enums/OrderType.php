<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum loại đơn hàng - Phân biệt luồng kho vật lý vs dropship.
 *
 * - WAREHOUSE: Đơn mua vào kho / đơn bán xuất từ kho
 * - DROPSHIP: Đơn bán giao thẳng, tự động sinh PO đối ứng
 * - DROPSHIP_LINKED: PO được tạo tự động từ SO dropship
 */
enum OrderType: string
{
    case WAREHOUSE = 'WAREHOUSE';
    case DROPSHIP = 'DROPSHIP';
    case DROPSHIP_LINKED = 'DROPSHIP_LINKED';

    /**
     * Nhãn tiếng Việt dùng cho UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::WAREHOUSE => 'Kho vật lý',
            self::DROPSHIP => 'Giao thẳng (Dropship)',
            self::DROPSHIP_LINKED => 'PO liên kết từ Dropship',
        };
    }

    /**
     * Có ảnh hưởng đến tồn kho vật lý hay không.
     * DROPSHIP: dòng tiền thay đổi, KHÔNG có stock movement.
     */
    public function affectsInventory(): bool
    {
        return $this === self::WAREHOUSE;
    }
}