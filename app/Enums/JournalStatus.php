<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái bút toán (Journal Entry).
 *
 *   DRAFT     : mới tạo, chưa ghi sổ - có thể sửa / xóa
 *   POSTED    : đã ghi sổ cái - không thể sửa, chỉ reverse
 *   REVERSED  : đã bị đảo ngược (bằng 1 JournalEntry khác với type = OPENING/REVERSAL)
 */
enum JournalStatus: string
{
    case DRAFT = 'DRAFT';
    case POSTED = 'POSTED';
    case REVERSED = 'REVERSED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Nháp',
            self::POSTED => 'Đã ghi sổ',
            self::REVERSED => 'Đã đảo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::POSTED => 'success',
            self::REVERSED => 'warning',
        };
    }
}