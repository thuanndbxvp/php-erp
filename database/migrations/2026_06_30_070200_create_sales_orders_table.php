<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng đơn bán (Sales Order) - SO.
     *
     * Hỗ trợ 2 luồng:
     *  - WAREHOUSE: xuất từ kho (warehouse_id NOT NULL, linked_purchase_order_id NULL)
     *  - DROPSHIP:  bán giao thẳng (warehouse_id NULL, linked_purchase_order_id chứa PO tự sinh)
     *
     * Trường KHÓA CỨNG:
     *  - linked_purchase_order_id (BẮT BUỘC có, nullable) - phục vụ luồng dropship
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();

            // Mã đơn bán - duy nhất, dùng để tra cứu (VD: SO-2026-00001)
            $table->string('order_number', 50)
                ->unique()
                ->comment('Mã đơn bán duy nhất (VD: SO-2026-00001)');

            // Loại đơn: WAREHOUSE / DROPSHIP (snapshot dạng string, Model sẽ cast Enum)
            $table->string('type', 32)
                ->comment('Loại đơn: WAREHOUSE hoặc DROPSHIP');

            // Trạng thái đơn (snapshot dạng string, Model sẽ cast Enum)
            $table->string('status', 32)
                ->default('DRAFT')
                ->comment('Trạng thái đơn (DRAFT/PENDING/CONFIRMED/PROCESSING/SHIPPING/SHIPPED/COMPLETED/CANCELLED/REJECTED)');

            // KHÓA NGOẠI BẮT BUỘC: Khách hàng
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete()
                ->comment('Khách hàng mua hàng');

            // Kho xuất hàng - NULL cho DROPSHIP
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->restrictOnDelete()
                ->comment('Kho xuất hàng (NULL với đơn DROPSHIP)');

            // Ngày đặt hàng + ngày giao dự kiến
            $table->date('order_date')
                ->comment('Ngày đặt hàng');

            $table->date('ship_date')
                ->nullable()
                ->comment('Ngày giao hàng dự kiến');

            // Tổng hợp tài chính (tính từ lines)
            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->comment('Tổng trước thuế và chiết khấu');

            $table->decimal('discount_amount', 15, 2)
                ->default(0)
                ->comment('Tổng chiết khấu');

            $table->decimal('tax_amount', 15, 2)
                ->default(0)
                ->comment('Tổng thuế VAT');

            $table->decimal('total_amount', 15, 2)
                ->default(0)
                ->comment('Tổng cuối cùng khách phải trả');

            // Tổng giá vốn (COGS) = sum(baseCost × quantity) của các line - phục vụ P&L
            $table->decimal('total_cost', 15, 2)
                ->default(0)
                ->comment('Tổng giá vốn COGS để tính lãi/lỗ');

            // Tiền tệ + tỷ giá
            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ (ISO 4217)');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1)
                ->comment('Tỷ giá quy đổi về VND');

            // Ghi chú
            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú cho khách hàng');

            $table->text('internal_notes')
                ->nullable()
                ->comment('Ghi chú nội bộ (không hiển thị cho khách)');

            // LIÊN KẾT DROPSHIP: trỏ tới PO được tự động sinh
            // BẮT BUỘC có trường này (nullable) theo yêu cầu nghiệp vụ
            $table->foreignId('linked_purchase_order_id')
                ->nullable()
                ->constrained('purchase_orders')
                ->nullOnDelete()
                ->comment('PO được tự động sinh cho luồng dropship');

            // Tham chiếu hóa đơn bán (InvoiceOut) - sẽ được thêm ở Khối 2
            $table->unsignedBigInteger('invoice_out_id')
                ->nullable()
                ->comment('FK tới invoice_outs (sẽ tạo ở Khối 2)');

            // Audit: người tạo + người duyệt
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo đơn');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người duyệt đơn');

            $table->timestamps();

            // Index phục vụ truy vấn
            $table->index('customer_id', 'sales_orders_customer_idx');
            $table->index('status', 'sales_orders_status_idx');
            $table->index('order_date', 'sales_orders_order_date_idx');
            $table->index('type', 'sales_orders_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};