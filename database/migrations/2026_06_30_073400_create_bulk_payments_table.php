<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng gom đơn thanh toán (BulkPayment) + Bảng chi tiết gom.
     *
     * BulkPayment là 1 phiếu "cha" gom nhiều invoice vào 1 lần thanh toán duy nhất
     * (ví dụ KH "Công ty A" trả 1 lần cho 5 đơn hàng).
     *
     * Luồng:
     *   1. Tạo BulkPayment (status = PENDING)
     *   2. Tạo BulkPaymentApplication cho từng Invoice (chưa trừ tiền)
     *   3. Tạo Payment (cha) gắn với BulkPayment
     *   4. Tạo PaymentApplication để match Payment với Invoice
     *   5. BulkPayment chuyển sang COMPLETED
     */
    public function up(): void
    {
        Schema::create('bulk_payments', function (Blueprint $table) {
            $table->id();

            // Số phiếu gom thanh toán (VD: BP-2026-00001)
            $table->string('bulk_number', 50)
                ->unique()
                ->comment('Số phiếu gom thanh toán');

            // Đối tượng thanh toán: khách hàng hay NCC
            $table->string('party_type', 32)
                ->comment('CUSTOMER/SUPPLIER');

            $table->foreignId('party_id')
                ->comment('ID customer hoặc supplier (theo party_type)');

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            // Tổng tiền gom (DECIMAL 15,2 BẮT BUỘC)
            $table->decimal('total_amount', 15, 2)
                ->comment('Tổng tiền gom (= SUM amount_applied)');

            // Phương thức thanh toán
            $table->string('payment_method', 32)
                ->comment('CASH/BANK_TRANSFER/QR_PAY/E_WALLET/CARD/PLATFORM');

            // Tài khoản nhận/chi
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();

            // Ngày thanh toán + mã tham chiếu
            $table->date('payment_date')
                ->comment('Ngày thanh toán');

            $table->string('reference', 100)
                ->nullable()
                ->comment('Mã tham chiếu (mã lệnh CK, mã phiếu thu chi...)');

            $table->text('description')
                ->nullable()
                ->comment('Mô tả (VD: "TT đơn hàng 01-05/01")');

            // Trạng thái (Enum BulkPaymentStatus, snapshot string)
            $table->string('status', 32)
                ->default('PENDING')
                ->comment('PENDING/PROCESSING/COMPLETED/FAILED');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            // Audit
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo phiếu gom');

            $table->timestamps();

            $table->index(['party_type', 'party_id'], 'bulk_payments_party_idx');
            $table->index('status', 'bulk_payments_status_idx');
            $table->index('payment_date', 'bulk_payments_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_payments');
    }
};
