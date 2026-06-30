<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum trạng thái đơn hàng - State Machine khóa cứng luồng đi của đơn.
 *
 * Áp dụng đồng thời cho SalesOrder và PurchaseOrder.
 * Mọi chuyển trạng thái đều ghi log vào order_status_history.
 */
enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case PROCESSING = 'PROCESSING';
    case SHIPPING = 'SHIPPING';
    case SHIPPED = 'SHIPPED';
    case RECEIVED = 'RECEIVED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case REJECTED = 'REJECTED';

    /**
     * Nhãn tiếng Việt dùng cho UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Nháp',
            self::PENDING => 'Chờ duyệt',
            self::CONFIRMED => 'Đã duyệt',
            self::PROCESSING => 'Đang xử lý',
            self::SHIPPING => 'Đang giao hàng',
            self::SHIPPED => 'Đã giao hàng',
            self::RECEIVED => 'Đã nhận hàng',
            self::COMPLETED => 'Hoàn thành',
            self::CANCELLED => 'Đã hủy',
            self::REJECTED => 'Bị từ chối',
        };
    }

    /**
     * Trạng thái đầu cuối - không thể chuyển tiếp.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::REJECTED,
        ], true);
    }

    /**
     * Có làm phát sinh biến động tồn kho (inventory_movements) hay không.
     */
    public function triggersStockMovement(): bool
    {
        return in_array($this, [
            self::SHIPPED,    // SO warehouse: xuất kho
            self::RECEIVED,   // PO warehouse: nhập kho
        ], true);
    }
}