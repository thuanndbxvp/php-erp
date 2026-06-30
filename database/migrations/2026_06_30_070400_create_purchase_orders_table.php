<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng đơn mua (Purchase Order) - PO.
     *
     * Hỗ trợ 2 luồng:
     *  - WAREHOUSE: mua nhập kho (warehouse_id NOT NULL)
     *  - DROPSHIP_LINKED: PO tự động sinh từ SO dropship (linked_sales_order_id NOT NULL)
     *
     * Trường KHÓA CỨNG:
     *  - linked_sales_order_id (BẮT BUỘC có, nullable) - phục vụ luồng dropship ngược
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // Mã đơn mua - duy nhất (VD: PO-2026-00001)
            $table->string('order_number', 50)
                ->unique()
                ->comment('Mã đơn mua duy nhất (VD: PO-2026-00001)');

            // Loại đơn: WAREHOUSE / DROPSHIP_LINKED
            $table->string('type', 32)
                ->comment('Loại đơn: WAREHOUSE hoặc DROPSHIP_LINKED');

            // Trạng thái đơn
            $table->string('status', 32)
                ->default('DRAFT')
                ->comment('Trạng thái đơn (DRAFT/PENDING/CONFIRMED/PROCESSING/RECEIVED/COMPLETED/CANCELLED/REJECTED)');

            // KHÓA NGOẠI BẮT BUỘC: Nhà cung cấp
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete()
                ->comment('Nhà cung cấp');

            // Kho nhập hàng - NULL cho dropship (hàng không qua kho)
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->restrictOnDelete()
                ->comment('Kho nhập hàng (NULL với đơn DROPSHIP_LINKED)');

            // Ngày đặt hàng + ngày nhận dự kiến
            $table->date('order_date')
                ->comment('Ngày đặt hàng');

            $table->date('receive_date')
                ->nullable()
                ->comment('Ngày nhận hàng dự kiến');

            // Tổng hợp tài chính
            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->comment('Tổng trước thuế và chiết khấu');

            $table->decimal('discount_amount', 15, 2)
                ->default(0)
                ->comment('Tổng chiết khấu');

            $table->decimal('tax_amount', 15, 2)
                ->default(0)
                ->comment('Tổng thuế VAT đầu vào');

            $table->decimal('total_amount', 15, 2)
                ->default(0)
                ->comment('Tổng phải thanh toán');

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
                ->comment('Ghi chú cho nhà cung cấp');

            // LIÊN KẾT DROPSHIP NGƯỢC: trỏ về SO đã sinh ra PO này
            // BẮT BUỘC có trường này (nullable) theo yêu cầu nghiệp vụ
            $table->foreignId('linked_sales_order_id')
                ->nullable()
                ->constrained('sales_orders')
                ->nullOnDelete()
                ->comment('SO đã tự động sinh PO này (luồng dropship)');

            // Tham chiếu hóa đơn mua (InvoiceIn) - sẽ thêm ở Khối 2
            $table->unsignedBigInteger('invoice_in_id')
                ->nullable()
                ->comment('FK tới invoice_ins (sẽ tạo ở Khối 2)');

            // Audit
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
            $table->index('supplier_id', 'po_supplier_idx');
            $table->index('status', 'po_status_idx');
            $table->index('order_date', 'po_order_date_idx');
            $table->index('type', 'po_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};