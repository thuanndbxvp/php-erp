<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng giao dịch SÀN TMĐT (Shopee, Lazada, Tiki, TiktokShop...).
     *
     * Quy trình:
     *   1. Khách trả 100,000₫ trên sàn → sàn giữ tiền, record vào bảng này (gross = 100,000)
     *   2. gross_amount = 100,000; platform_fee = 5,000; net_amount = 95,000
     *   3. Sau 3-7 ngày sàn "quyết toán" → actual_received = 95,000, status → SETTLED
     *   4. Liên kết BankTransaction sẽ xử lý tiền về tài khoản NH thật.
     *
     * Công thức:
     *   net_amount = gross_amount - platform_fee
     *   balance = actual_received - gross_amount (sau khi trừ platform_fee)
     *             dùng tính phí sàn ghi nhận vào Bank Account Clearing.
     */
    public function up(): void
    {
        Schema::create('platform_transactions', function (Blueprint $table) {
            $table->id();

            // Mã sàn (SHOPEE / LAZADA / TIKI / TIKTOKSHOP)
            $table->string('platform_id', 50)
                ->comment('Mã sàn: SHOPEE/LAZADA/TIKI/TIKTOKSHOP...');

            // Mã đơn trên sàn (unique theo platform)
            $table->string('platform_order_id', 100)
                ->comment('Mã đơn trên sàn');

            // FK tới đơn bán nội bộ (nullable - có thể sàn tạo đơn không qua ERP)
            $table->foreignId('sales_order_id')
                ->nullable()
                ->constrained('sales_orders')
                ->nullOnDelete()
                ->comment('Đơn bán nội bộ (SalesOrder)');

            // Tài khoản trung gian (FK tới bank_accounts WHERE account_type = PLATFORM_CLEARING)
            $table->foreignId('clearing_bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete()
                ->comment('Tài khoản trung gian clearing của sàn');

            // Tài chính - tất cả DECIMAL 15,2 BẮT BUỘC
            // Số tiền khách trả trên sàn (= doanh thu đơn hàng)
            $table->decimal('gross_amount', 15, 2)
                ->comment('Số tiền khách trả trên sàn (BẮT BUỘC)');

            // Phí sàn
            $table->decimal('platform_fee', 15, 2)
                ->default(0)
                ->comment('Phí sàn (BẮT BUỘC - theo yêu cầu)');

            // Số tiền thực nhận = gross - fee
            $table->decimal('net_amount', 15, 2)
                ->comment('Số tiền thực nhận (= gross - fee) (BẮT BUỘC)');

            // Tiền thực sự về tài khoản NH (set khi SETTLED)
            $table->decimal('actual_received', 15, 2)
                ->nullable()
                ->comment('Số tiền thực tế về TK NH');

            // Ngày quyết toán (sàn chuyển tiền)
            $table->date('settlement_date')
                ->nullable()
                ->comment('Ngày sàn chuyển tiền về TK NH');

            // FK tới payment khi sàn chuyển tiền đã được ghi nhận vào ERP
            $table->foreignId('matched_payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete()
                ->comment('Payment ghi nhận tiền sàn chuyển về');

            // FK tới bank_transaction khi match sao kê NH
            $table->foreignId('matched_bank_transaction_id')
                ->nullable()
                ->constrained('bank_transactions')
                ->nullOnDelete()
                ->comment('BankTransaction trên sao kê NH');

            // Trạng thái (Enum PlatformTxStatus, snapshot string)
            $table->string('status', 32)
                ->default('PENDING')
                ->comment('PENDING/SETTLED/DISPUTED');

            // Metadata thô từ sàn (JSON)
            $table->json('raw_data')
                ->nullable()
                ->comment('Dữ liệu gốc từ API sàn');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            $table->timestamps();

            // Mỗi (platform, order_id) duy nhất
            $table->unique(['platform_id', 'platform_order_id'], 'platform_tx_unique');
            $table->index('status', 'platform_tx_status_idx');
            $table->index('sales_order_id', 'platform_tx_so_idx');
            $table->index('settlement_date', 'platform_tx_settlement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_transactions');
    }
};
