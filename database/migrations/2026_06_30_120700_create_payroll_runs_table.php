<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Kỳ tính lương (Payroll Runs).
     *
     * Mỗi PayrollRun là 1 lần chạy lương cho 1 tháng cụ thể.
     * Sinh ra nhiều Payslip (1 Payslip / nhân viên).
     *
     * State Machine: DRAFT → PROCESSING → APPROVED → PAID (hoặc CANCELLED).
     */
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();

            // Mã kỳ lương - duy nhất (VD: PR-2026-06)
            $table->string('run_number', 50)
                ->unique()
                ->comment('Mã kỳ lương (VD: PR-2026-06)');

            // Kỳ lương: tháng + năm
            $table->unsignedTinyInteger('period_month')
                ->comment('Tháng tính lương (1-12)');

            $table->unsignedSmallInteger('period_year')
                ->comment('Năm tính lương');

            // Snapshot thông tin kỳ (nhãn hiển thị)
            $table->string('period_label')
                ->comment('Nhãn kỳ (VD: "Tháng 6/2026")');

            // Ngày bắt đầu / kết thúc kỳ lương
            $table->date('period_start_date')
                ->comment('Ngày đầu kỳ');

            $table->date('period_end_date')
                ->comment('Ngày cuối kỳ');

            $table->date('payment_date')
                ->nullable()
                ->comment('Ngày dự kiến chi trả');

            // Tổng hợp lương kỳ này (snapshot, set khi đóng kỳ)
            $table->decimal('total_gross', 15, 2)
                ->default(0)
                ->comment('Tổng Gross toàn kỳ');

            $table->decimal('total_deduction', 15, 2)
                ->default(0)
                ->comment('Tổng khấu trừ toàn kỳ');

            $table->decimal('total_net', 15, 2)
                ->default(0)
                ->comment('Tổng Net toàn kỳ');

            // Trạng thái - snapshot string, Model sẽ cast Enum
            $table->string('status', 32)
                ->default('DRAFT')
                ->comment('DRAFT/PROCESSING/APPROVED/PAID/CANCELLED');

            // Audit: người tạo, người duyệt, người chi trả
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo kỳ lương');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người duyệt kỳ lương');

            $table->timestamp('approved_at')
                ->nullable()
                ->comment('Thời điểm duyệt');

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người xác nhận chi trả');

            $table->timestamp('paid_at')
                ->nullable()
                ->comment('Thời điểm chi trả');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú kỳ lương');

            $table->timestamps();

            // RÀO CHẮN NGHIỆP VỤ: 1 tháng + 1 năm chỉ có 1 kỳ lương
            $table->unique(['period_year', 'period_month'], 'payroll_runs_period_unique');

            $table->index('status', 'payroll_runs_status_idx');
            $table->index('payment_date', 'payroll_runs_payment_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
