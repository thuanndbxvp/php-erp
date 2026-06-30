<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng dòng chi tiết đơn bán (Sales Order Line).
     *
     * Mỗi dòng BẮT BUỘC có baseCost - GIÁ VỐN tại thời điểm bán,
     * snapshot lại để không phụ thuộc vào average_cost thay đổi sau này.
     */
    public function up(): void
    {
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();

            // FK tới đơn bán - cascade vì line vô nghĩa khi đơn bị xóa
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete()
                ->comment('Đơn bán cha');

            // FK tới sản phẩm
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->comment('Sản phẩm trên dòng này');

            // Snapshot sản phẩm: lưu {sku, name, unit} tại thời điểm bán
            // để truy vấn/tính lại P&L không phụ thuộc product thay đổi
            $table->json('product_snapshot')
                ->comment('Snapshot sản phẩm: {sku, name, unit}');

            // Số lượng bán (DECIMAL 15,3)
            $table->decimal('quantity', 15, 3)
                ->comment('Số lượng bán');

            // Đơn giá bán (chưa thuế, chưa CK)
            $table->decimal('unit_price', 15, 2)
                ->comment('Đơn giá bán');

            // === RÀO CHẮN NGHIỆP VỤ BẮT BUỘC ===
            // baseCost: GIÁ VỐN đơn vị tại thời điểm bán
            // Field này BẮT BUỘC cho P&L theo nguyên lý số 4 (Cost tracking at line level)
            $table->decimal('base_cost', 15, 2)
                ->comment('GIÁ VỐN đơn vị tại thời điểm bán - BẮT BUỘC cho P&L');

            // Thành tiền giá vốn = base_cost × quantity (COGS của dòng)
            $table->decimal('line_cost', 15, 2)
                ->default(0)
                ->comment('COGS của dòng = base_cost × quantity');

            // Chiết khấu dòng (theo % và số tiền tuyệt đối)
            $table->decimal('discount_percent', 5, 2)
                ->default(0)
                ->comment('Chiết khấu theo phần trăm');

            $table->decimal('discount_amount', 15, 2)
                ->default(0)
                ->comment('Số tiền chiết khấu tuyệt đối');

            // Thuế dòng
            $table->decimal('tax_percent', 5, 2)
                ->default(0)
                ->comment('Thuế VAT (%)');

            // Thành tiền dòng (sau CK, sau thuế)
            $table->decimal('line_total', 15, 2)
                ->default(0)
                ->comment('Thành tiền dòng (sau chiết khấu, sau thuế)');

            // Direct costs gắn với dòng (phí bốc vác, ship riêng...)
            $table->decimal('direct_costs', 15, 2)
                ->default(0)
                ->comment('Chi phí trực tiếp gắn với dòng (phí bốc vác, ship...)');

            // Liên kết tới dòng PO (cho luồng dropship)
            $table->unsignedBigInteger('linked_purchase_order_line_id')
                ->nullable()
                ->comment('FK tới purchase_order_lines (sẽ thêm ràng buộc ở migration sau)');

            // Thứ tự sắp xếp hiển thị
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->comment('Thứ tự hiển thị dòng');

            // Bảng này không có updated_at - line là immutable snapshot
            $table->timestamp('created_at')->useCurrent();

            $table->index('sales_order_id', 'so_lines_order_idx');
            $table->index('product_id', 'so_lines_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
    }
};