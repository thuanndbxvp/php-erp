<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng hóa đơn mua vào (InvoiceIn / AP).
     *
     * Đại diện cho công nợ phải trả (Accounts Payable) nhà cung cấp.
     * Mỗi InvoiceIn liên kết 1-1 với PurchaseOrder (KHỐI 1).
     *
     * Sơ đồ tiền: total - paid_amount = balance_due (còn nợ NCC)
     */
    public function up(): void
    {
        Schema::create('invoice_ins', function (Blueprint $table) {
            $table->id();

            // Số hóa đơn duy nhất (VD: INV-IN-2026-00001)
            $table->string('invoice_number', 50)
                ->unique()
                ->comment('Số hóa đơn duy nhất');

            // KHÓA NGOẠI BẮT BUỘC theo yêu cầu nghiệp vụ:
            // Đơn mua phát sinh hóa đơn
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->restrictOnDelete()
                ->comment('Đơn mua phát sinh hóa đơn');

            // FK tới nhà cung cấp
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete()
                ->comment('Nhà cung cấp');

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
                ->comment('Tổng thuế VAT đầu vào');

            // BẮT BUỘC - theo yêu cầu: total
            $table->decimal('total', 15, 2)
                ->default(0)
                ->comment('Tổng phải thanh toán');

            // BẮT BUỘC - theo yêu cầu: paid_amount
            $table->decimal('paid_amount', 15, 2)
                ->default(0)
                ->comment('Tổng đã trả cho NCC');

            // BẮT BUỘC - theo yêu cầu: balance_due
            $table->decimal('balance_due', 15, 2)
                ->default(0)
                ->comment('Còn phải trả (= total - paid_amount)');

            // Tiền tệ + tỷ giá + thuế suất
            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1)
                ->comment('Tỷ giá quy đổi về VND');

            $table->decimal('tax_rate', 5, 2)
                ->default(10.00)
                ->comment('Thuế suất VAT đầu vào (%)');

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

            // Index truy vấn
            $table->index('supplier_id', 'invoice_ins_supplier_idx');
            $table->index('status', 'invoice_ins_status_idx');
            $table->index('due_date', 'invoice_ins_due_date_idx');
            $table->index('invoice_date', 'invoice_ins_invoice_date_idx');
        });

        // FK ngược từ purchase_orders.invoice_in_id (đã reserve sẵn ở Phase 1)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign('invoice_in_id', 'purchase_orders_invoice_in_fk')
                ->references('id')
                ->on('invoice_ins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign('purchase_orders_invoice_in_fk');
        });
        Schema::dropIfExists('invoice_ins');
    }
};
