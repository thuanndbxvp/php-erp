<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum loại tài liệu tham chiếu cho biến động tồn kho (polymorphic).
 *
 * Dùng cho cặp (ref_type, ref_id) trong inventory_movements
 * nhằm truy ngược biến động đến nguồn gốc nghiệp vụ.
 */
enum RefType: string
{
    case SALES_ORDER = 'SALES_ORDER';
    case PURCHASE_ORDER = 'PURCHASE_ORDER';
    case MANUAL = 'MANUAL';
    case TRANSFER_ORDER = 'TRANSFER_ORDER';
    case DAMAGE_REPORT = 'DAMAGE_REPORT';

    /**
     * Nhãn tiếng Việt dùng cho UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::SALES_ORDER => 'Đơn bán',
            self::PURCHASE_ORDER => 'Đơn mua',
            self::MANUAL => 'Thao tác thủ công',
            self::TRANSFER_ORDER => 'Phiếu chuyển kho',
            self::DAMAGE_REPORT => 'Báo cáo hàng hỏng',
        };
    }
}