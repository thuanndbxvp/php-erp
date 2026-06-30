<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm FK từ departments.manager_id → employees.id sau khi bảng employees đã tồn tại.
     * Tách riêng để tránh circular dependency giữa departments ↔ employees.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
    }
};
