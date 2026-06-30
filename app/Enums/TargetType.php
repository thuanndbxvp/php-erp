<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại chỉ tiêu (target) trong luật hoa hồng.
 *
 * Xác định CHỈ TIÊU nào mà rule sẽ đo lường để tính hoa hồng:
 * - REVENUE       : Doanh thu các SO đạt được
 * - ORDER_COUNT   : Số lượng đơn hàng
 * - PROFIT        : Lợi nhuận gộp từ SO
 * - COLLECTED_AMT : Tiền đã thu về (loại trừ đơn chưa thanh toán)
 * - NEW_CUSTOMER  : Số khách hàng mới
 */
enum TargetType: string
{
    case REVENUE = 'REVENUE';
    case ORDER_COUNT = 'ORDER_COUNT';
    case PROFIT = 'PROFIT';
    case COLLECTED_AMT = 'COLLECTED_AMT';
    case NEW_CUSTOMER = 'NEW_CUSTOMER';

    public function label(): string
    {
        return match ($this) {
            self::REVENUE => 'Doanh thu',
            self::ORDER_COUNT => 'Số lượng đơn',
            self::PROFIT => 'Lợi nhuận gộp',
            self::COLLECTED_AMT => 'Tiền đã thu',
            self::NEW_CUSTOMER => 'Khách hàng mới',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::REVENUE, self::COLLECTED_AMT => 'success',
            self::ORDER_COUNT, self::NEW_CUSTOMER => 'info',
            self::PROFIT => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::REVENUE => 'heroicon-m-banknotes',
            self::ORDER_COUNT => 'heroicon-m-shopping-bag',
            self::PROFIT => 'heroicon-m-chart-bar',
            self::COLLECTED_AMT => 'heroicon-m-currency-dollar',
            self::NEW_CUSTOMER => 'heroicon-m-user-plus',
        };
    }
}
