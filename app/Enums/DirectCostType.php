<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại chi phí trực tiếp (Direct Cost) gắn với Đơn bán.
 *
 * Direct Cost = chi phí phát sinh riêng cho từng SO (vận chuyển, đóng gói, hoa hồng sale...).
 * Khác với OPEX (chi phí vận hành chung).
 */
enum DirectCostType: string
{
    case HANDLING = 'HANDLING';       // Phí đóng gói, xử lý đơn
    case SHIPPING = 'SHIPPING';       // Phí vận chuyển
    case COMMISSION = 'COMMISSION';   // Hoa hồng nhân viên sale/CTV
    case INSURANCE = 'INSURANCE';     // Phí bảo hiểm hàng hóa
    case OTHER = 'OTHER';             // Khác

    public function label(): string
    {
        return match ($this) {
            self::HANDLING => 'Phí đóng gói',
            self::SHIPPING => 'Phí vận chuyển',
            self::COMMISSION => 'Hoa hồng',
            self::INSURANCE => 'Phí bảo hiểm',
            self::OTHER => 'Chi phí khác',
        };
    }

    /**
     * Màu hiển thị (Filament Badge).
     */
    public function color(): string
    {
        return match ($this) {
            self::HANDLING => 'info',
            self::SHIPPING => 'warning',
            self::COMMISSION => 'success',
            self::INSURANCE => 'primary',
            self::OTHER => 'gray',
        };
    }

    /**
     * Icon (Filament Heroicon).
     */
    public function icon(): string
    {
        return match ($this) {
            self::HANDLING => 'heroicon-m-cube',
            self::SHIPPING => 'heroicon-m-truck',
            self::COMMISSION => 'heroicon-m-banknotes',
            self::INSURANCE => 'heroicon-m-shield-check',
            self::OTHER => 'heroicon-m-question-mark-circle',
        };
    }
}