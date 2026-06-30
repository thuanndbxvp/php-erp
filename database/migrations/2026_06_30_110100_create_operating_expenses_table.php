<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng chi phí vận hành (Operating Expenses / OPEX).
     *
     * Là các chi phí KHÔNG gắn với đơn hàng cụ thể - điện/nước/lương/thuê/MKT...
     * Mỗi dòng có 2 tài khoản: NỢ (TK chi phí) - CÓ (TK tiền/nguồn trả).
     *
     * Lưu ý nghiệp vụ: KHÔNG có sales_order_id - đây là điểm phân biệt cốt lõi
     * với direct_costs. OPEX phân bổ theo thời gian (expense_date), KHÔNG theo SO.
     */
    public function up(): void
    {
        Schema::create('operating_expenses', function (Blueprint $table) {
            $table->id();

            // FK tới danh mục OPEX
            $table->foreignId('category_id')
                ->constrained('opex_categories')
                ->restrictOnDelete()
                ->comment('Danh mục chi phí');

            // Mã phiếu chi nội bộ (tham khảo, không unique tuyệt đối)
            $table->string('expense_number', 50)
                ->nullable()
                ->comment('Mã phiếu chi nội bộ');

            // Tiêu đề / mô tả ngắn
            $table->string('title')
                ->comment('Tiêu đề chi phí');

            $table->text('description')
                ->nullable()
                ->comment('Mô tả chi tiết');

            // Số tiền - DECIMAL(15,2) BẮT BUỘC
            $table->decimal('amount', 15, 2)
                ->comment('Số tiền chi phí (> 0)');

            // TK Nợ (mặc định lấy từ category, nhưng cho phép override)
            $table->foreignId('debit_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete()
                ->comment('TK NỢ - chi phí (TK 6xx, 8xx, 9xx)');

            // TK Có - thường là tiền (111, 112), hoặc phải trả (331)
            $table->foreignId('credit_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete()
                ->comment('TK CÓ - nguồn trả (tiền, phải trả NCC, ...)');

            // Ngày phát sinh chi phí - dùng để query kỳ báo cáo
            $table->date('expense_date')
                ->comment('Ngày phát sinh chi phí');

            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1)
                ->comment('Tỷ giá quy đổi về VND');

            // Người tạo + duyệt chi phí (audit)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo phiếu chi');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người duyệt chi phí');

            // Trạng thái: DRAFT / APPROVED / POSTED / CANCELLED
            $table->string('status', 32)
                ->default('APPROVED')
                ->comment('DRAFT/APPROVED/POSTED/CANCELLED');

            $table->timestamps();

            // Index phục vụ báo cáo P&L theo kỳ
            $table->index('category_id', 'opex_category_idx');
            $table->index('expense_date', 'opex_date_idx');
            $table->index('debit_account_id', 'opex_debit_account_idx');
            $table->index('credit_account_id', 'opex_credit_account_idx');
            $table->index('status', 'opex_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expenses');
    }
};
