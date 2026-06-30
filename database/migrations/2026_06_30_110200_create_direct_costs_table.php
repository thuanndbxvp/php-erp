<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng chi phí trực tiếp (Direct Costs) - chi phí gắn với Đơn bán (SO).
     *
     * Theo nguyên lý số 4 (Cost tracking at line level), mỗi SO có thể phát sinh
     * thêm chi phí trực tiếp NGOÀI giá vốn sản phẩm: phí ship, phí đóng gói,
     * hoa hồng sale, bảo hiểm... Mỗi direct_cost BẮT BUỘC trỏ về 1 SO.
     *
     * Phân biệt với operating_expenses:
     *  - direct_costs: GẮN VỚI SO (sales_order_id NOT NULL), tính theo từng đơn.
     *  - operating_expenses: KHÔNG gắn SO, phân bổ theo thời gian.
     */
    public function up(): void
    {
        Schema::create('direct_costs', function (Blueprint $table) {
            $table->id();

            // FK tới SO - BẮT BUỘC (đặc trưng cốt lõi của direct cost)
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->restrictOnDelete()
                ->comment('Đơn bán phát sinh chi phí (BẮT BUỘC)');

            // Loại chi phí trực tiếp: SHIPPING/HANDLING/COMMISSION/INSURANCE/OTHER
            $table->string('cost_type', 32)
                ->comment('Loại chi phí: SHIPPING/HANDLING/COMMISSION/INSURANCE/OTHER');

            // Tiêu đề + mô tả
            $table->string('title')
                ->comment('Tiêu đề chi phí');

            $table->text('description')
                ->nullable()
                ->comment('Mô tả chi tiết');

            // Số tiền - DECIMAL(15,2) BẮT BUỘC
            $table->decimal('amount', 15, 2)
                ->comment('Số tiền chi phí trực tiếp (> 0)');

            // TK Nợ (thường là TK chi phí bán hàng - 6411 theo TT200)
            $table->foreignId('debit_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete()
                ->comment('TK NỢ - chi phí bán hàng / chi phí trực tiếp');

            // TK Có (thường là tiền mặt 1111 hoặc NH 1121)
            $table->foreignId('credit_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete()
                ->comment('TK CÓ - nguồn chi (tiền, NH, phải trả)');

            // Ngày phát sinh
            $table->date('expense_date')
                ->comment('Ngày phát sinh chi phí');

            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1)
                ->comment('Tỷ giá quy đổi về VND');

            // Tham chiếu phiếu chi nếu đã trả tiền
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete()
                ->comment('Phiếu chi liên quan (nếu đã thanh toán)');

            // Audit
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo');

            // Trạng thái
            $table->string('status', 32)
                ->default('APPROVED')
                ->comment('DRAFT/APPROVED/POSTED/CANCELLED');

            $table->timestamps();

            // Index phục vụ truy vấn P&L
            $table->index('sales_order_id', 'direct_costs_so_idx');
            $table->index('cost_type', 'direct_costs_type_idx');
            $table->index('expense_date', 'direct_costs_date_idx');
            $table->index('debit_account_id', 'direct_costs_debit_account_idx');
            $table->index('status', 'direct_costs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_costs');
    }
};
