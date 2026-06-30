<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng tồn kho hiện tại - Snapshot theo từng (sản phẩm, kho).
     *
     * Mỗi cặp (product_id, warehouse_id) chỉ có duy nhất 1 dòng.
     * quantity_available là cột GENERATED = quantity_on_hand - quantity_reserved,
     * đảm bảo tính nhất quán ở tầng DB, không cần tính lại ở app.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();

            // FK tới sản phẩm
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->comment('Sản phẩm');

            // FK tới kho vật lý
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete()
                ->comment('Kho chứa hàng');

            // Tồn kho thực tế đang nằm trong kho (đếm được bằng cách kiểm kê)
            $table->decimal('quantity_on_hand', 15, 3)
                ->default(0)
                ->comment('Tồn kho thực tế đang nằm trong kho');

            // Số lượng đã được đặt bởi Sales Order nhưng chưa xuất kho (CONFIRMED/PROCESSING)
            $table->decimal('quantity_reserved', 15, 3)
                ->default(0)
                ->comment('Đã được SO giữ chỗ, chưa xuất kho');

            // Số lượng đang vận chuyển giữa các kho
            $table->decimal('quantity_in_transit', 15, 3)
                ->default(0)
                ->comment('Đang vận chuyển giữa các kho');

            // Giá vốn bình quân gia quyền - cập nhật qua inventory_movements
            $table->decimal('average_cost', 15, 2)
                ->default(0)
                ->comment('Giá vốn bình quân gia quyền');

            $table->timestamps();

            // Khóa duy nhất: 1 sản phẩm chỉ có 1 dòng tồn trên 1 kho
            $table->unique(['product_id', 'warehouse_id'], 'inventories_product_warehouse_unique');

            // Index phục vụ truy vấn tồn kho âm / cảnh báo dưới định mức
            $table->index(['warehouse_id', 'quantity_on_hand'], 'inventories_warehouse_qty_index');
        });

        // Cột tính toán (GENERATED) - đảm bảo quantity_available luôn đúng.
        // MySQL 8 hỗ trợ VIRTUAL generated columns với cú pháp này.
        \DB::statement('
            ALTER TABLE inventories
            ADD COLUMN quantity_available DECIMAL(15,3)
            GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) VIRTUAL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};