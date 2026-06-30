<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Phòng ban (Departments).
     *
     * Có thể tổ chức cây phân cấp qua `parent_id`.
     * Mỗi phòng ban có 1 manager chính (FK tới employees), nhưng FK này
     * được TẠO SAU ở bảng employees để tránh circular dependency.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            // Mã phòng ban - duy nhất (VD: DEP-SALES, DEP-IT, DEP-HR)
            $table->string('code', 32)
                ->unique()
                ->comment('Mã phòng ban (VD: DEP-SALES, DEP-IT)');

            // Tên phòng ban
            $table->string('name')
                ->comment('Tên phòng ban');

            // Phòng ban cha (cây phân cấp)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete()
                ->comment('Phòng ban cha (cây phân cấp)');

            // Trưởng phòng - FK tới employees, nhưng constraint được
            // thêm ở migration sau để tránh vòng FK vòng (employees.department_id ↔ departments.manager_id)
            $table->unsignedBigInteger('manager_id')
                ->nullable()
                ->comment('Trưởng phòng (FK tới employees, thêm constraint sau)');

            $table->text('description')
                ->nullable()
                ->comment('Mô tả phòng ban');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Đang hoạt động');

            $table->timestamps();

            $table->index('parent_id', 'departments_parent_idx');
            $table->index('manager_id', 'departments_manager_idx');
            $table->index('is_active', 'departments_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
