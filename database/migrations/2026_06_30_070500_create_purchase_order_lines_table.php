<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng dòng chi tiết đơn mua (Purchase Order Line).
     *
     * Theo dõi 3 mốc số lượng:
     *  - quantity:          số lượng đặt ban đầu
     *  - ordered_quantity:  số lượng đã đặt lại với NCC (alias = quantity, dùng cho partial flow)
     *  - received_quantity: số lượng thực tế đã nhận (cập nhật khi status = RECEIVED)
     */
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();

            // FK tới đơn mua cha
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete()
                ->comment('Đơn mua cha');

            // FK tới sản phẩm
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->comment('Sản phẩm trên dòng này');

            // Snapshot sản phẩm tại thời điểm đặt
            $table->json('product_snapshot')
                ->comment('Snapshot sản phẩm: {sku, name, unit}');

            // Số lượng đặt ban đầu
            $table->decimal('quantity', 15, 3)
                ->comment('Số lượng đặt ban đầu');

            // Số lượng đã được NCC xác nhận
            $table->decimal('ordered_quantity', 15, 3)
                ->default(0)
                ->comment('Số lượng NCC xác nhận');

            // Số lượng thực tế đã nhập kho
            $table->decimal('received_quantity', 15, 3)
                ->default(0)
                ->comment('Số lượng thực tế đã nhập kho');

            // Đơn giá mua (GIÁ VỐN đầu vào)
            $table->decimal('unit_cost', 15, 2)
                ->comment('Đơn giá mua (= baseCost khi map sang SO line cho dropship)');

            // Chiết khấu dòng
            $table->decimal('discount_percent', 5, 2)
                ->default(0)
                ->comment('Chiết khấu theo phần trăm');

            $table->decimal('discount_amount', 15, 2)
                ->default(0)
                ->comment('Số tiền chiết khấu tuyệt đối');

            // Thuế VAT đầu vào
            $table->decimal('tax_percent', 5, 2)
                ->default(0)
                ->comment('Thuế VAT đầu vào (%)');

            // Thành tiền dòng (sau CK, sau thuế)
            $table->decimal('line_total', 15, 2)
                ->default(0)
                ->comment('Thành tiền dòng (sau chiết khấu, sau thuế)');

            // Direct costs (phí vận chuyển cho dòng này...)
            $table->decimal('direct_costs', 15, 2)
                ->default(0)
                ->comment('Chi phí trực tiếp (phí vận chuyển cho dòng)');

            // Thứ tự sắp xếp
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->comment('Thứ tự hiển thị dòng');

            // Bảng này không có updated_at - line là immutable snapshot
            $table->timestamp('created_at')->useCurrent();

            $table->index('purchase_order_id', 'po_lines_order_idx');
            $table->index('product_id', 'po_lines_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};