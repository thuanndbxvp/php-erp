<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Enum nhóm menu điều hướng (Navigation Group) - Filament 5 yêu cầu UnitEnum.
 */
enum NavigationGroup: string implements HasLabel
{
    case QUAN_LY_KHO = 'quan_ly_kho';
    case DOI_TAC = 'doi_tac';
    case BAN_HANG = 'ban_hang';
    case MUA_HANG = 'mua_hang';
    case TAI_CHINH = 'tai_chinh';
    case NHAN_SU = 'nhan_su';
    case QUAN_TRI = 'quan_tri';

    public function getLabel(): string
    {
        return match ($this) {
            self::QUAN_LY_KHO => 'Quản lý kho',
            self::DOI_TAC => 'Đối tác',
            self::BAN_HANG => 'Bán hàng',
            self::MUA_HANG => 'Mua hàng',
            self::TAI_CHINH => 'Tài chính',
            self::NHAN_SU => 'Nhân sự',
            self::QUAN_TRI => 'Quản trị',
        };
    }

    /**
     * Thứ tự hiển thị (số nhỏ = trên).
     */
    public function getSort(): int
    {
        return match ($this) {
            self::BAN_HANG => 10,
            self::MUA_HANG => 20,
            self::QUAN_LY_KHO => 30,
            self::DOI_TAC => 40,
            self::TAI_CHINH => 50,
            self::NHAN_SU => 70,
            self::QUAN_TRI => 90,
        };
    }
}