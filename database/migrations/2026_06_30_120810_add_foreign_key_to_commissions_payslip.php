<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm FK từ commissions.payslip_id → payslips.id sau khi bảng payslips đã tồn tại.
     * Tách riêng để tránh circular dependency giữa commissions ↔ payslips.
     */
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->foreign('payslip_id')
                ->references('id')
                ->on('payslips')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['payslip_id']);
        });
    }
};
