<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Hoa hồng phát sinh (Commissions).
     *
     * Mỗi record là 1 khoản hoa hồng được sinh ra từ 1 SO cụ thể
     * theo 1 commission_rule. Có thể có nhiều record / SO (nếu có nhiều rule
     * cùng áp dụng) hoặc 1 record / SO (1 rule phổ biến nhất).
     *
     * BẮT BUỘC: employee_id, sales_order_id, rule_id.
     */
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();

            // FK tới nhân viên được hưởng hoa hồng - BẮT BUỘC
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete()
                ->comment('Nhân viên hưởng hoa hồng (BẮT BUỘC)');

            // FK tới Đơn bán tạo ra hoa hồng - BẮT BUỘC
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->restrictOnDelete()
                ->comment('Đơn hàng tạo ra hoa hồng (BẮT BUỘC)');

            // FK tới luật hoa hồng đang áp dụng - BẮT BUỘC
            $table->foreignId('rule_id')
                ->constrained('commission_rules')
                ->restrictOnDelete()
                ->comment('Luật hoa hồng áp dụng (BẮT BUỘC)');

            // Giá trị đơn hàng tại thời điểm tính hoa hồng (snapshot)
            $table->decimal('order_amount', 15, 2)
                ->comment('Giá trị đơn hàng (snapshot tại thời điểm tính)');

            // Giá trị target để áp dụng rate (VD: doanh thu sau CK, số đơn...)
            $table->decimal('target_value', 15, 2)
                ->default(0)
                ->comment('Giá trị target (phụ thuộc target_type của rule)');

            // Số tiền hoa hồng được tính = target_value × rate_percent / 100
            $table->decimal('commission_amount', 15, 2)
                ->comment('Số tiền hoa hồng (= target_value × rate_percent / 100)');

            // Trạng thái - snapshot string, Model sẽ cast Enum
            $table->string('status', 32)
                ->default('PENDING')
                ->comment('PENDING/APPROVED/PAID/REVERSED/CANCELLED');

            // Ngày sinh hoa hồng (ngày SO được SHIPPED / COMPLETED)
            $table->date('earned_date')
                ->comment('Ngày phát sinh hoa hồng');

            // Ngày duyệt & ngày thanh toán (audit timeline)
            $table->timestamp('approved_at')
                ->nullable()
                ->comment('Thời điểm duyệt');

            $table->timestamp('paid_at')
                ->nullable()
                ->comment('Thời điểm thanh toán');

            // Phiếu lương đã gộp (nếu gộp vào payslip)
            $table->unsignedBigInteger('payslip_id')
                ->nullable()
                ->comment('Phiếu lương đã gộp (FK payslips, thêm constraint sau)');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            $table->timestamps();

            // Index phục vụ Payroll + reporting
            $table->index('employee_id', 'commissions_employee_idx');
            $table->index('sales_order_id', 'commissions_so_idx');
            $table->index('rule_id', 'commissions_rule_idx');
            $table->index('status', 'commissions_status_idx');
            $table->index('earned_date', 'commissions_earned_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
