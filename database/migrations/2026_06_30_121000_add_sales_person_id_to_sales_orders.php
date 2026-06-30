<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung cột sales_person_id cho sales_orders (Phase 5 — Bước 2).
     *
     * Lý do:
     *  - Bảng `commissions` (đã có từ Bước 1) yêu cầu employee_id BẮT BUỘC.
     *  - Để tính hoa hồng tự động, hệ thống cần biết ĐƠN NÀY do NV sales nào phụ trách.
     *  - Một SO có thể có 1 sale chính (sales_person_id nullable).
     *
     * Tách riêng migration này để không phải rollback cả `2026_06_30_070200`.
     * Cột được thêm SAU khi bảng `employees` đã tồn tại nên có thể FK trực tiếp.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // NV sale phụ trách đơn - dùng để tính hoa hồng.
            // NULL = đơn nội bộ / không gán sale.
            $table->foreignId('sales_person_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('employees')
                ->nullOnDelete()
                ->comment('NV sale phụ trách đơn (NULL = không gán / không tính hoa hồng)');

            $table->index('sales_person_id', 'sales_orders_sales_person_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['sales_person_id']);
            $table->dropIndex('sales_orders_sales_person_idx');
            $table->dropColumn('sales_person_id');
        });
    }
};
