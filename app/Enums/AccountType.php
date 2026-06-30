<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Phân loại tài khoản kế toán theo 5 nhóm chuẩn:
 *  - ASSET      : Tài sản (Tiền, NH, Phải thu, Tồn kho...)
 *  - LIABILITY  : Nợ phải trả (Phải trả NCC, Thuế, Vay...)
 *  - EQUITY     : Vốn chủ sở hữu
 *  - REVENUE    : Doanh thu
 *  - EXPENSE    : Chi phí
 *
 * Số dư bình thường:
 *  - ASSET/EXPENSE     : DEBIT (dương khi Nợ)
 *  - LIABILITY/EQUITY/REVENUE : CREDIT (dương khi Có)
 */
enum AccountType: string
{
    case ASSET = 'ASSET';
    case LIABILITY = 'LIABILITY';
    case EQUITY = 'EQUITY';
    case REVENUE = 'REVENUE';
    case EXPENSE = 'EXPENSE';

    public function label(): string
    {
        return match ($this) {
            self::ASSET => 'Tài sản',
            self::LIABILITY => 'Nợ phải trả',
            self::EQUITY => 'Vốn chủ sở hữu',
            self::REVENUE => 'Doanh thu',
            self::EXPENSE => 'Chi phí',
        };
    }

    /**
     * Số dư bình thường (normal balance side).
     * ASSET/EXPENSE → DEBIT, còn lại → CREDIT.
     */
    public function normalBalance(): EntryDC
    {
        return match ($this) {
            self::ASSET, self::EXPENSE => EntryDC::DEBIT,
            self::LIABILITY, self::EQUITY, self::REVENUE => EntryDC::CREDIT,
        };
    }

    /**
     * Tăng/giảm tài khoản sinh bút toán bên nào?
     *  - ASSET tăng → DEBIT
     *  - REVENUE tăng → CREDIT
     */
    public function increaseSide(): EntryDC
    {
        return $this->normalBalance();
    }

    public function decreaseSide(): EntryDC
    {
        return $this->normalBalance() === EntryDC::DEBIT ? EntryDC::CREDIT : EntryDC::DEBIT;
    }
}