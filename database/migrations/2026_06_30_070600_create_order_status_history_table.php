<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng lịch sử thay đổi trạng thái đơn hàng (Audit Trail).
     *
     * Lưu MỌI lần chuyển trạng thái của SalesOrder lẫn PurchaseOrder.
     * Thiết kế polymorphic: order_type = SALES_ORDER | PURCHASE_ORDER,
     * order_id = ID tương ứng.
     *
     * BẢNG NÀY CHỈ INSERT, KHÔNG UPDATE / KHÔNG DELETE (immutable).
     */
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();

            // Polymorphic discriminator + ID
            $table->string('order_type', 32)
                ->comment('Loại đơn: SALES_ORDER hoặc PURCHASE_ORDER');

            $table->unsignedBigInteger('order_id')
                ->comment('ID đơn hàng (SalesOrder hoặc PurchaseOrder)');

            // Trạng thái cũ + mới (snapshot string, Model sẽ cast Enum)
            $table->string('from_status', 32)
                ->nullable()
                ->comment('Trạng thái cũ (NULL nếu là tạo mới)');

            $table->string('to_status', 32)
                ->comment('Trạng thái mới');

            // Người thực hiện thay đổi
            $table->foreignId('changed_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người thực hiện chuyển trạng thái');

            $table->timestamp('changed_at')
                ->useCurrent()
                ->comment('Thời điểm chuyển trạng thái');

            // Lý do + ghi chú (snapshot lại để audit)
            $table->string('reason', 255)
                ->nullable()
                ->comment('Lý do chuyển trạng thái');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú chi tiết');

            // Index chính cho polymorphic: tìm lịch sử của 1 đơn
            $table->index(['order_type', 'order_id'], 'order_status_history_order_idx');

            // Index phụ: truy vấn theo thời gian / theo người thao tác
            $table->index('changed_at', 'order_status_history_changed_at_idx');
            $table->index('changed_by', 'order_status_history_changed_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};