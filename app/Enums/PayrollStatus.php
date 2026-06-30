<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái kỳ tính lương (Payroll Run).
 *
 * State Machine:
 *   DRAFT → PROCESSING → APPROVED → PAID
 *               ↓
 *          CANCELLED (huỷ kỳ)
 *   DRAFT → CANCELLED
 */
enum PayrollStatus: string
{
    case DRAFT = 'DRAFT';
    case PROCESSING = 'PROCESSING';
    case APPROVED = 'APPROVED';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Nháp',
            self::PROCESSING => 'Đang tính',
            self::APPROVED => 'Đã duyệt',
            self::PAID => 'Đã chi trả',
            self::CANCELLED => 'Đã huỷ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PROCESSING => 'info',
            self::APPROVED => 'warning',
            self::PAID => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-document',
            self::PROCESSING => 'heroicon-m-arrow-path',
            self::APPROVED => 'heroicon-m-check-badge',
            self::PAID => 'heroicon-m-banknotes',
            self::CANCELLED => 'heroicon-m-x-circle',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::PAID,
            self::CANCELLED,
        ], true);
    }
}
