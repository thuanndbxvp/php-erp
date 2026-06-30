<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Đơn nghỉ phép (Leaves).
     *
     * Mỗi đơn có thể kéo dài nhiều ngày (start_date → end_date).
     * `total_days` được tính sẵn (snapshot) để không phụ thuộc lịch sau này.
     */
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();

            // Mã đơn nghỉ phép (tham khảo) - VD: LV-2026-00001
            $table->string('leave_number', 50)
                ->unique()
                ->comment('Mã đơn nghỉ phép (VD: LV-2026-00001)');

            // FK tới nhân viên xin nghỉ
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete()
                ->comment('Nhân viên xin nghỉ');

            // Loại nghỉ phép - snapshot string, Model sẽ cast Enum
            $table->string('leave_type', 32)
                ->comment('ANNUAL/SICK/MATERNITY/PATERNITY/UNPAID/BEREAVEMENT/MARRIAGE/COMPENSATORY');

            // Lý do / ghi chú
            $table->text('reason')
                ->nullable()
                ->comment('Lý do nghỉ');

            // Thời gian nghỉ
            $table->date('start_date')
                ->comment('Ngày bắt đầu nghỉ');

            $table->date('end_date')
                ->comment('Ngày kết thúc nghỉ');

            // Số ngày nghỉ (tính sẵn, snapshot)
            $table->decimal('total_days', 5, 2)
                ->default(1)
                ->comment('Tổng số ngày nghỉ (snapshot)');

            // Trạng thái duyệt - snapshot string, Model sẽ cast Enum
            $table->string('status', 32)
                ->default('PENDING')
                ->comment('DRAFT/PENDING/APPROVED/REJECTED/CANCELLED');

            // Người duyệt + thời điểm duyệt
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người duyệt');

            $table->timestamp('approved_at')
                ->nullable()
                ->comment('Thời điểm duyệt');

            // Ghi chú của người duyệt (VD: lý do từ chối)
            $table->text('approver_notes')
                ->nullable()
                ->comment('Ghi chú của người duyệt');

            $table->timestamps();

            $table->index('employee_id', 'leaves_employee_idx');
            $table->index('status', 'leaves_status_idx');
            $table->index(['start_date', 'end_date'], 'leaves_period_idx');
            $table->index('leave_type', 'leaves_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
