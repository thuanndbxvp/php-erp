<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Chấm công (Attendances).
     *
     * Lưu 1 record / nhân viên / ngày. Unique (employee_id, date).
     * `work_hours` được tính từ check_in/check_out (có thể do máy chấm công sync về).
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // FK tới nhân viên - cascade vì nếu xoá nhân viên thì chấm công của họ cũng mất
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete()
                ->comment('Nhân viên');

            // Ngày chấm công
            $table->date('date')
                ->comment('Ngày chấm công');

            // Giờ vào / ra (kiểu time, cho phép null cho ngày nghỉ)
            $table->time('check_in')
                ->nullable()
                ->comment('Giờ vào');

            $table->time('check_out')
                ->nullable()
                ->comment('Giờ ra');

            // Số giờ làm thực tế (tính sẵn, không recompute mỗi lần query)
            $table->decimal('work_hours', 5, 2)
                ->default(0)
                ->comment('Số giờ làm thực tế');

            // Số giờ tăng ca (ngoài work_hours)
            $table->decimal('overtime_hours', 5, 2)
                ->default(0)
                ->comment('Giờ tăng ca');

            // Trạng thái chấm công - snapshot string, Model sẽ cast Enum
            $table->string('status', 32)
                ->default('PRESENT')
                ->comment('PRESENT/LATE/EARLY_LEAVE/ABSENT/ON_LEAVE/HOLIDAY/WORK_FROM_HOME/OVERTIME');

            // Ghi chú
            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            $table->timestamps();

            // RÀO CHẮN NGHIỆP VỤ: 1 nhân viên chỉ có 1 record / ngày
            $table->unique(['employee_id', 'date'], 'attendances_employee_date_unique');

            $table->index('date', 'attendances_date_idx');
            $table->index('status', 'attendances_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
