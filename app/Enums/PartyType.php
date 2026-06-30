<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Đối tượng tham gia dòng tiền (Payment / BulkPayment).
 *
 * - CUSTOMER: Khách hàng - bên nhận tiền (Payment hướng AR - thu tiền).
 * - SUPPLIER: Nhà cung cấp - bên được trả tiền (Payment hướng AP - chi tiền).
 */
enum PartyType: string
{
    case CUSTOMER = 'CUSTOMER';
    case SUPPLIER = 'SUPPLIER';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Khách hàng',
            self::SUPPLIER => 'Nhà cung cấp',
        };
    }

    /**
     * Field trên Payment / BulkPayment dùng để map party_id:
     *   - CUSTOMER → customer_id
     *   - SUPPLIER → supplier_id
     */
    public function foreignKey(): string
    {
        return match ($this) {
            self::CUSTOMER => 'customer_id',
            self::SUPPLIER => 'supplier_id',
        };
    }

    /**
     * Khi CUSTOMER → Payment ghi nhận tiền VÀO (thu).
     * Khi SUPPLIER → Payment ghi nhận tiền RA (chi).
     */
    public function cashDirection(): string
    {
        return match ($this) {
            self::CUSTOMER => 'IN',
            self::SUPPLIER => 'OUT',
        };
    }
}