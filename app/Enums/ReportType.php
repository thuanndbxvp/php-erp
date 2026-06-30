<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại báo cáo quản trị (Management Report).
 *
 * Phân biệt với nhóm báo cáo tài chính (TT200) ở chỗ:
 *  - Báo cáo TÀI CHÍNH (theo TT200): đã có sẵn trong ReportService (P&L, Trial Balance, Balance Sheet).
 *  - Báo cáo QUẢN TRỊ: phân tích chi phí theo biến phí / định phí, contribution margin, cash flow...
 */
enum ReportType: string
{
    case PROFIT_LOSS = 'PROFIT_LOSS';           // Báo cáo KQKD (TT200)
    case TRIAL_BALANCE = 'TRIAL_BALANCE';       // Bảng cân đối thử (TT200)
    case BALANCE_SHEET = 'BALANCE_SHEET';       // Bảng CĐKT (TT200)
    case CONTRIBUTION_MARGIN = 'CONTRIBUTION_MARGIN'; // Báo cáo Contribution Margin
    case CASH_FLOW = 'CASH_FLOW';               // Báo cáo dòng tiền
    case OPEX_BREAKDOWN = 'OPEX_BREAKDOWN';     // Phân tích OPEX theo danh mục
    case DIRECT_COST_ANALYSIS = 'DIRECT_COST_ANALYSIS'; // Phân tích chi phí trực tiếp

    public function label(): string
    {
        return match ($this) {
            self::PROFIT_LOSS => 'Báo cáo Lãi/Lỗ (P&L)',
            self::TRIAL_BALANCE => 'Bảng Cân đối thử',
            self::BALANCE_SHEET => 'Bảng Cân đối Kế toán',
            self::CONTRIBUTION_MARGIN => 'Báo cáo Contribution Margin',
            self::CASH_FLOW => 'Báo cáo Dòng tiền',
            self::OPEX_BREAKDOWN => 'Phân tích Chi phí Vận hành (OPEX)',
            self::DIRECT_COST_ANALYSIS => 'Phân tích Chi phí Trực tiếp',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PROFIT_LOSS => 'Doanh thu - Chi phí theo TT200/2014/TT-BTC',
            self::TRIAL_BALANCE => 'Tổng Nợ/Có cho từng tài khoản trong kỳ',
            self::BALANCE_SHEET => 'Tài sản = Nợ phải trả + Vốn CSH tại 1 thời điểm',
            self::CONTRIBUTION_MARGIN => 'DT - Biến phí (COGS + Direct Cost) - phục vụ ra quyết định giá',
            self::CASH_FLOW => 'Dòng tiền vào/ra theo tài khoản tiền',
            self::OPEX_BREAKDOWN => 'Chi phí điện, nước, lương... theo danh mục',
            self::DIRECT_COST_ANALYSIS => 'Phí ship/đóng gói/hoa hồng theo từng đơn hàng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PROFIT_LOSS, self::CONTRIBUTION_MARGIN => 'success',
            self::TRIAL_BALANCE, self::BALANCE_SHEET => 'info',
            self::CASH_FLOW => 'primary',
            self::OPEX_BREAKDOWN, self::DIRECT_COST_ANALYSIS => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PROFIT_LOSS => 'heroicon-m-chart-bar',
            self::TRIAL_BALANCE => 'heroicon-m-scale',
            self::BALANCE_SHEET => 'heroicon-m-document-chart-bar',
            self::CONTRIBUTION_MARGIN => 'heroicon-m-presentation-chart-line',
            self::CASH_FLOW => 'heroicon-m-currency-dollar',
            self::OPEX_BREAKDOWN => 'heroicon-m-receipt-percent',
            self::DIRECT_COST_ANALYSIS => 'heroicon-m-calculator',
        };
    }
}