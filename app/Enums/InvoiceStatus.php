<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trạng thái hóa đơn (áp dụng cho cả InvoiceOut và InvoiceIn).
 *
 * Suy luận từ (balance_due, paid_amount, due_date):
 *   - paid_amount == 0 && chưa phát hành  → DRAFT
 *   - paid_amount == 0 && đã phát hành    → ISSUED
 *   - 0 < paid_amount < total              → PARTIAL
 *   - paid_amount >= total                 → PAID
 *   - due_date < today && balance_due > 0  → OVERDUE (cảnh báo)
 *   - hủy                                 → CANCELLED
 *   - đã lập hóa đơn bù trừ (credit note)  → CREDITED
 */
enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case ISSUED = 'ISSUED';
    case PARTIAL = 'PARTIAL';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';
    case CREDITED = 'CREDITED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Nháp',
            self::ISSUED => 'Đã phát hành',
            self::PARTIAL => 'Thanh toán một phần',
            self::PAID => 'Đã thanh toán',
            self::OVERDUE => 'Quá hạn',
            self::CANCELLED => 'Đã hủy',
            self::CREDITED => 'Đã bù trừ / Credit note',
        };
    }

    /**
     * Màu badge UI (Filament).
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ISSUED => 'info',
            self::PARTIAL => 'warning',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'gray',
            self::CREDITED => 'primary',
        };
    }

    /**
     * Suy luật trạng thái từ số liệu tài chính.
     *
     * @param numeric-string|float|int $total   Tổng hóa đơn
     * @param numeric-string|float|int $paid    Đã thanh toán
     * @param \DateTimeInterface|null $dueDate  Hạn thanh toán (null = không tính OVERDUE)
     * @param bool $isDraft                     true nếu hóa đơn còn ở trạng thái nháp
     */
    public static function resolve(string|float|int $total, string|float|int $paid, ?\DateTimeInterface $dueDate = null, bool $isDraft = false): self
    {
        $total = (float) $total;
        $paid = (float) $paid;

        if ($isDraft && $paid <= 0.0) {
            return self::DRAFT;
        }

        $balance = $total - $paid;
        // Dùng epsilon so sánh tiền tệ tránh sai số float.
        $eps = 0.005;

        if ($balance <= $eps) {
            return self::PAID;
        }

        if ($paid > $eps && $balance > $eps) {
            return self::PARTIAL;
        }

        // Chưa thanh toán gì → ISSUED hoặc OVERDUE.
        if ($dueDate !== null && $dueDate < new \DateTimeImmutable('today')) {
            return self::OVERDUE;
        }

        return self::ISSUED;
    }
}