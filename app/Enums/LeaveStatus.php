<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái duyệt đơn nghỉ phép.
 *
 * - DRAFT     : Nhân viên đang soạn, chưa gửi
 * - PENDING   : Chờ quản lý duyệt
 * - APPROVED  : Đã duyệt - ảnh hưởng tới chấm công
 * - REJECTED  : Bị từ chối
 * - CANCELLED : Nhân viên huỷ đơn
 */
enum LeaveStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Nháp',
            self::PENDING => 'Chờ duyệt',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Bị từ chối',
            self::CANCELLED => 'Đã huỷ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
        ], true);
    }
}
