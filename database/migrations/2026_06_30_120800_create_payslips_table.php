<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Phiếu lương (Payslips).
     *
     * Mỗi Payslip = bảng lương của 1 nhân viên trong 1 kỳ lương cụ thể.
     * Bao gồm 2 khối:
     *  - Earnings: base_salary, allowances, overtime_pay, commission_amount → gross_salary
     *  - Deductions: personal_tax, social_insurance, advance_deduction → total_deduction
     *  - Net: gross_salary - total_deduction
     *
     * Mọi trường tiền tệ dùng DECIMAL(15,2).
     */
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();

            // Mã phiếu lương - duy nhất (VD: PS-2026-06-EMP00001)
            $table->string('payslip_number', 50)
                ->unique()
                ->comment('Mã phiếu lương (VD: PS-2026-06-EMP00001)');

            // FK tới kỳ lương
            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete()
                ->comment('Kỳ lương cha');

            // FK tới nhân viên
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete()
                ->comment('Nhân viên nhận lương');

            // Snapshot thông tin cơ bản của NV tại thời điểm tính lương
            // (tránh phụ thuộc vào employee sau này)
            $table->string('employee_code_snapshot', 32)
                ->comment('Mã NV tại thời điểm tính lương');

            $table->string('employee_name_snapshot')
                ->comment('Tên NV tại thời điểm tính lương');

            $table->string('department_name_snapshot')
                ->nullable()
                ->comment('Tên phòng ban tại thời điểm tính');

            $table->string('position_name_snapshot')
                ->nullable()
                ->comment('Tên chức vụ tại thời điểm tính');

            // ============= EARNINGS (THU NHẬP) =============
            // Lương cơ bản (snapshot từ employees.base_salary)
            $table->decimal('base_salary', 15, 2)
                ->default(0)
                ->comment('Lương cơ bản trong kỳ');

            // Phụ cấp (ăn trưa, xăng xe, điện thoại, nhà ở...)
            $table->decimal('allowances', 15, 2)
                ->default(0)
                ->comment('Tổng phụ cấp');

            // Lương tăng ca
            $table->decimal('overtime_pay', 15, 2)
                ->default(0)
                ->comment('Lương tăng ca');

            // Hoa hồng gộp vào lương (từ các commissions status=APPROVED chưa trả)
            $table->decimal('commission_amount', 15, 2)
                ->default(0)
                ->comment('Hoa hồng gộp vào lương');

            // Thu nhập khác (thưởng lễ/tết, 13th month...)
            $table->decimal('other_earnings', 15, 2)
                ->default(0)
                ->comment('Thu nhập khác (thưởng, 13th month)');

            // Tổng thu nhập (gross)
            $table->decimal('gross_salary', 15, 2)
                ->default(0)
                ->comment('Tổng thu nhập (gross)');

            // ============= DEDUCTIONS (KHẤU TRỪ) =============
            // Thuế TNCN (Personal Income Tax)
            $table->decimal('personal_tax', 15, 2)
                ->default(0)
                ->comment('Thuế thu nhập cá nhân');

            // Bảo hiểm xã hội (BHXH)
            $table->decimal('social_insurance', 15, 2)
                ->default(0)
                ->comment('BHXH (cả phần NV đóng)');

            // Bảo hiểm y tế (BHYT)
            $table->decimal('health_insurance', 15, 2)
                ->default(0)
                ->comment('BHYT');

            // Bảo hiểm thất nghiệp (BHTN)
            $table->decimal('unemployment_insurance', 15, 2)
                ->default(0)
                ->comment('BHTN');

            // Tạm ứng lương
            $table->decimal('advance_deduction', 15, 2)
                ->default(0)
                ->comment('Khấu trừ tạm ứng');

            // Khấu trừ khác (phạt, đoàn phí...)
            $table->decimal('other_deduction', 15, 2)
                ->default(0)
                ->comment('Khấu trừ khác');

            // Tổng khấu trừ
            $table->decimal('total_deduction', 15, 2)
                ->default(0)
                ->comment('Tổng khấu trừ');

            // ============= NET (THỰC NHẬN) =============
            // Net = Gross - Tổng khấu trừ
            $table->decimal('net_salary', 15, 2)
                ->default(0)
                ->comment('Lương thực nhận (Net = Gross - Deduction)');

            // ============= SỐ CÔNG (Dùng cho audit + báo cáo) =============
            $table->decimal('work_days', 5, 2)
                ->default(0)
                ->comment('Số ngày công thực tế');

            $table->decimal('paid_leave_days', 5, 2)
                ->default(0)
                ->comment('Số ngày nghỉ có lương');

            $table->decimal('unpaid_leave_days', 5, 2)
                ->default(0)
                ->comment('Số ngày nghỉ không lương');

            $table->decimal('overtime_hours', 5, 2)
                ->default(0)
                ->comment('Số giờ tăng ca');

            // ============= THANH TOÁN =============
            $table->date('payment_date')
                ->nullable()
                ->comment('Ngày đã trả lương');

            $table->string('status', 32)
                ->default('DRAFT')
                ->comment('DRAFT/PROCESSING/APPROVED/PAID/CANCELLED');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            $table->timestamps();

            // RÀO CHẮN NGHIỆP VỤ: 1 NV chỉ có 1 payslip / kỳ lương
            $table->unique(['payroll_run_id', 'employee_id'], 'payslips_run_employee_unique');

            $table->index('employee_id', 'payslips_employee_idx');
            $table->index('status', 'payslips_status_idx');
            $table->index('payment_date', 'payslips_payment_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
