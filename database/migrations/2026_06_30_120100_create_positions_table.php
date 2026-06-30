<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Chức vụ (Positions).
     *
     * Mỗi chức vụ thuộc 1 phòng ban (department_id).
     * Chuẩn hoá từ employees.position (string) sang bảng riêng để quản lý
     * hệ thống cấp bậc + lương theo vị trí.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            // Mã chức vụ - duy nhất (VD: POS-IT-DEV, POS-SALE-EXEC)
            $table->string('code', 32)
                ->unique()
                ->comment('Mã chức vụ (VD: POS-IT-DEV, POS-SALE-EXEC)');

            // Tên chức vụ hiển thị (VD: Trưởng phòng IT, Nhân viên kinh doanh)
            $table->string('title')
                ->comment('Tên chức vụ');

            // FK tới phòng ban sở hữu
            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete()
                ->comment('Phòng ban sở hữu chức vụ');

            // Cấp bậc (1=Staff, 2=Senior, 3=Lead, 4=Manager, 5=Director...)
            $table->unsignedTinyInteger('level')
                ->default(1)
                ->comment('Cấp bậc (1-10, càng cao càng cấp cao)');

            // Khoảng lương gợi ý (chỉ tham khảo, lương thực lưu trên employee)
            $table->decimal('min_salary', 15, 2)
                ->nullable()
                ->comment('Mức lương tối thiểu gợi ý');

            $table->decimal('max_salary', 15, 2)
                ->nullable()
                ->comment('Mức lương tối đa gợi ý');

            $table->text('description')
                ->nullable()
                ->comment('Mô tả công việc / yêu cầu chức vụ');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Đang sử dụng');

            $table->timestamps();

            $table->index('department_id', 'positions_department_idx');
            $table->index('is_active', 'positions_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
