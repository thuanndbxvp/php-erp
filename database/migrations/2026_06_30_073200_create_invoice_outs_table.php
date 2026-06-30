<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng hóa đơn bán ra (InvoiceOut / AR).
     *
     * Đại diện cho công nợ phải thu (Accounts Receivable) của khách hàng.
     * Mỗi InvoiceOut liên kết 1-1 với SalesOrder (KHỐI 1).
     *
     * Tài chính:
     *   total           = subtotal - discount + tax (BẮT BUỘC)
     *   paid_amount     = Σ(amount_applied từ PaymentApplication)
     *   balance_due     = total - paid_amount (computed ở Model)
     *   status tự động  = sư luật từ balance_due + due_date
     */
    public function up(): void
    {
        Schema::create('invoice_outs', function (Blueprint $table) {
            $table->id();

            // Số hóa đơn duy nhất (VD: INV-2026-00001)
            $table->string('invoice_number', 50)
                ->unique()
                ->comment('Số hóa đơn duy nhất');

            // KHÓA NGOẠI BẮT BUỘC theo yêu cầu nghiệp vụ:
            // Đơn bán phát sinh hóa đơn (1 SO → nhiều InvoiceOut nếu bán nhiều đợt)
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->restrictOnDelete()
                ->comment('Đơn bán phát sinh hóa đơn');

            // FK tới khách hàng
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete()
                ->comment('Khách hàng mua hàng');

            // Ngày hóa đơn + hạn thanh toán
            $table->date('invoice_date')
                ->comment('Ngày phát hành hóa đơn');

            $table->date('due_date')
                ->comment('Hạn thanh toán');

            // Tổng hợp tài chính (DECIMAL 15,2 BẮT BUỘC)
            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->comment('Tổng trước thuế');

            $table->decimal('discount_amount', 15, 2)
                ->default(0)
                ->comment('Tổng chiết khấu');

            $table->decimal('tax_amount', 15, 2)
                ->default(0)
                ->comment('Tổng thuế VAT đầu ra');

            // BẮT BUỘC - theo yêu cầu: total
            $table->decimal('total', 15, 2)
                ->default(0)
                ->comment('Tổng cuối cùng khách phải trả');

            // BẮT BUỘC - theo yêu cầu: paid_amount
            $table->decimal('paid_amount', 15, 2)
                ->default(0)
                ->comment('Tổng tiền đã thu (từ PaymentApplication)');

            // BẮT BUỘC - theo yêu cầu: balance_due (được lưu cứng để query nhanh,
            // Model cũng cung cấp accessor để tính lại on-demand nếu cần)
            $table->decimal('balance_due', 15, 2)
                ->default(0)
                ->comment('Còn phải thu (= total - paid_amount)');

            // Tiền tệ + tỷ giá
            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1)
                ->comment('Tỷ giá quy đổi về VND');

            $table->decimal('tax_rate', 5, 2)
                ->default(10.00)
                ->comment('Thuế suất (%)');

            // Loại hóa đơn (Enum InvoiceType, snapshot string)
            $table->string('invoice_type', 32)
                ->default('DOMESTIC')
                ->comment('Loại: FPT/DOMESTIC/EXPORT');

            // Trạng thái (Enum InvoiceStatus, snapshot string)
            $table->string('status', 32)
                ->default('DRAFT')
                ->comment('Trạng thái: DRAFT/ISSUED/PARTIAL/PAID/OVERDUE/CANCELLED/CREDITED');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            // Audit
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo hóa đơn');

            $table->timestamps();

            // Index truy vấn phổ biến
            $table->index('customer_id', 'invoice_outs_customer_idx');
            $table->index('status', 'invoice_outs_status_idx');
            $table->index('due_date', 'invoice_outs_due_date_idx');
            $table->index('invoice_date', 'invoice_outs_invoice_date_idx');
        });

        // FK ngược từ sales_orders.invoice_out_id (đã reserve sẵn ở Phase 1)
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreign('invoice_out_id', 'sales_orders_invoice_out_fk')
                ->references('id')
                ->on('invoice_outs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign('sales_orders_invoice_out_fk');
        });
        Schema::dropIfExists('invoice_outs');
    }
};
