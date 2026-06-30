<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sổ cái biến động tồn kho - BẤT BIẾN (immutable).
     *
     * KHÔNG được UPDATE trực tiếp - chỉ INSERT + reversal.
     * Mọi thay đổi tồn kho đều phải ghi 1 dòng vào đây.
     *
     * Cặp (ref_type, ref_id) là polymorphic reference trỏ về
     * SalesOrder / PurchaseOrder / Manual / TransferOrder.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            // FK sản phẩm + kho (phục vụ tra cứu nhanh)
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->comment('Sản phẩm biến động');

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete()
                ->comment('Kho phát sinh biến động');

            // Loại biến động: PURCHASE/SALE/ADJUSTMENT/TRANSFER/RETURN_IN/RETURN_OUT/DAMAGE
            $table->string('type', 32)
                ->comment('Loại biến động: PURCHASE / SALE / ADJUSTMENT / TRANSFER / RETURN_IN / RETURN_OUT / DAMAGE');

            // Số lượng: dương = nhập, âm = xuất
            $table->decimal('quantity', 15, 3)
                ->comment('Số lượng biến động (dương=nhập, âm=xuất)');

            // Giá vốn tại thời điểm phát sinh biến động
            $table->decimal('unit_cost', 15, 2)
                ->default(0)
                ->comment('Giá vốn đơn vị tại thời điểm');

            // Thành tiền = quantity × unit_cost (lưu sẵn để không phải tính lại)
            $table->decimal('total_value', 15, 2)
                ->default(0)
                ->comment('Thành tiền = quantity × unit_cost');

            // Tham chiếu polymorphic: SalesOrder / PurchaseOrder / Manual / ...
            $table->string('ref_type', 32)
                ->nullable()
                ->comment('Loại chứng từ gốc (SALES_ORDER / PURCHASE_ORDER / MANUAL / ...)');

            $table->unsignedBigInteger('ref_id')
                ->nullable()
                ->comment('ID chứng từ gốc (polymorphic FK)');

            // Lý do phát sinh + ghi chú
            $table->string('reason', 255)
                ->nullable()
                ->comment('Lý do phát sinh biến động');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú chi tiết');

            // FK tới user thao tác
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người thao tác');

            // Không có updated_at vì bảng này là BẤT BIẾN
            $table->timestamp('created_at')->useCurrent();

            // Index để tra cứu theo kho + sản phẩm
            $table->index(['product_id', 'warehouse_id', 'type'], 'inv_movements_product_wh_type_idx');

            // Index cho polymorphic (ref_type, ref_id)
            $table->index(['ref_type', 'ref_id'], 'inv_movements_ref_idx');

            // Index theo thời gian để dựng sổ cái
            $table->index('created_at', 'inv_movements_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};