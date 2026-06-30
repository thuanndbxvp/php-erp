<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service sinh mã giao dịch tài chính tự động theo format {PREFIX}-{YYYY}-{000001}.
 *
 * Prefix mapping:
 *   SO   - sales_orders     (SalesOrder)
 *   PO   - purchase_orders  (PurchaseOrder)
 *   INV  - invoice_outs     (InvoiceOut - hóa đơn bán)
 *   BIL  - invoice_ins      (InvoiceIn - hóa đơn mua)
 *   PMT  - payments         (Payment)
 *   BP   - bulk_payments    (BulkPayment)
 *   BNT  - bank_transactions (BankTransaction - import sao kê, dùng cho batch ref)
 *
 * Đảm bảo duy nhất thông qua DB transaction + lockForUpdate sequence.
 */
class OrderNumberGenerator
{
    public const SO_PREFIX = 'SO';
    public const PO_PREFIX = 'PO';
    public const INV_OUT_PREFIX = 'INV';
    public const INV_IN_PREFIX = 'BIL';
    public const PAYMENT_PREFIX = 'PMT';
    public const BULK_PAYMENT_PREFIX = 'BP';
    public const JOURNAL_PREFIX = 'JE';
    public const EMPLOYEE_PREFIX = 'EMP';
    public const DEPT_PREFIX = 'DEP';
    public const POSITION_PREFIX = 'POS';
    public const ATTENDANCE_PREFIX = 'ATT';
    public const LEAVE_PREFIX = 'LV';
    public const COMMISSION_RULE_PREFIX = 'CR';
    public const PAYROLL_RUN_PREFIX = 'PR';
    public const PAYSLIP_PREFIX = 'PS';

    public function nextSalesOrderNumber(): string
    {
        return $this->next(self::SO_PREFIX, 'sales_orders', 'order_number');
    }

    public function nextPurchaseOrderNumber(): string
    {
        return $this->next(self::PO_PREFIX, 'purchase_orders', 'order_number');
    }

    /**
     * Sinh mã hóa đơn bán (InvoiceOut).
     */
    public function nextInvoiceOutNumber(): string
    {
        return $this->next(self::INV_OUT_PREFIX, 'invoice_outs', 'invoice_number');
    }

    /**
     * Sinh mã hóa đơn mua (InvoiceIn).
     */
    public function nextInvoiceInNumber(): string
    {
        return $this->next(self::INV_IN_PREFIX, 'invoice_ins', 'invoice_number');
    }

    /**
     * Sinh mã phiếu thanh toán (Payment).
     */
    public function nextPaymentNumber(): string
    {
        return $this->next(self::PAYMENT_PREFIX, 'payments', 'payment_number');
    }

    /**
     * Sinh mã phiếu gom thanh toán (BulkPayment).
     */
    public function nextBulkPaymentNumber(): string
    {
        return $this->next(self::BULK_PAYMENT_PREFIX, 'bulk_payments', 'bulk_number');
    }

    /**
     * Sinh mã bút toán (Journal Entry).
     */
    public function nextJournalEntryNumber(): string
    {
        return $this->next(self::JOURNAL_PREFIX, 'journal_entries', 'journal_number');
    }

    /**
     * Sinh mã batch import sao kê ngân hàng.
     */
    public function nextBankImportBatchId(): string
    {
        $stamp = now()->format('Ymd-His');

        return sprintf('IMP-%s-%04d', $stamp, random_int(0, 9999));
    }

    /**
     * Sinh mã nhân viên (Employee code).
     * Format: EMP-{YYYY}-{000001}.
     */
    public function nextEmployeeCode(): string
    {
        return $this->next(self::EMPLOYEE_PREFIX, 'employees', 'employee_code');
    }

    /**
     * Sinh mã phòng ban (Department code).
     * Format: DEP-{YYYY}-{000001}.
     */
    public function nextDepartmentCode(): string
    {
        return $this->next(self::DEPT_PREFIX, 'departments', 'code');
    }

    /**
     * Sinh mã chức vụ (Position code).
     * Format: POS-{YYYY}-{000001}.
     */
    public function nextPositionCode(): string
    {
        return $this->next(self::POSITION_PREFIX, 'positions', 'code');
    }

    /**
     * Sinh mã chấm công (Attendance).
     * Format: ATT-{YYYY}-{000001}. Ít dùng vì unique(employee_id,date),
     * nhưng cần cho trường hợp import hàng loạt.
     */
    public function nextAttendanceCode(): string
    {
        return $this->next(self::ATTENDANCE_PREFIX, 'attendances', 'id');
    }

    /**
     * Sinh mã đơn nghỉ phép (Leave).
     * Format: LV-{YYYY}-{000001}.
     */
    public function nextLeaveNumber(): string
    {
        return $this->next(self::LEAVE_PREFIX, 'leaves', 'leave_number');
    }

    /**
     * Sinh mã luật hoa hồng (Commission Rule).
     * Format: CR-{YYYY}-{000001}.
     */
    public function nextCommissionRuleCode(): string
    {
        return $this->next(self::COMMISSION_RULE_PREFIX, 'commission_rules', 'name');
    }

    /**
     * Sinh mã kỳ lương (Payroll Run).
     * Format: PR-{YYYY}-{MM} - dùng period_month/year, không dùng sequence.
     * Đảm bảo unique per (year, month) (đã có unique index).
     */
    public function nextPayrollRunNumber(int $year, int $month): string
    {
        return sprintf('%s-%04d-%02d', self::PAYROLL_RUN_PREFIX, $year, $month);
    }

    /**
     * Sinh mã phiếu lương (Payslip).
     * Format: PS-{YYYY}-{MM}-{000001} - sequence reset mỗi tháng.
     */
    public function nextPayslipNumber(int $year, int $month): string
    {
        $prefix = self::PAYSLIP_PREFIX;
        $period = sprintf('%04d-%02d', $year, $month);
        $yearMonth = "{$prefix}-{$period}-";

        return DB::transaction(function () use ($yearMonth) {
            $latest = DB::table('payslips')
                ->where('payslip_number', 'like', "{$yearMonth}%")
                ->lockForUpdate()
                ->orderByDesc('payslip_number')
                ->value('payslip_number');

            $next = 1;
            if ($latest) {
                $tail = (int) substr($latest, strlen($yearMonth));
                $next = $tail + 1;
            }

            return sprintf('%s%06d', $yearMonth, $next);
        });
    }

    /**
     * Hàm chung: sinh mã tiếp theo theo prefix cho (table, column).
     */
    private function next(string $prefix, string $table, string $column): string
    {
        $year = now()->format('Y');

        return DB::transaction(function () use ($prefix, $table, $column, $year) {
            $latest = DB::table($table)
                ->where($column, 'like', "{$prefix}-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc($column)
                ->value($column);

            $nextSequence = 1;

            if ($latest) {
                $parts = explode('-', $latest);
                $lastSeq = (int) end($parts);
                $nextSequence = $lastSeq + 1;
            }

            return sprintf('%s-%s-%06d', $prefix, $year, $nextSequence);
        });
    }
}