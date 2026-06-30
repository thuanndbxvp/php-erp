<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng payments - Dòng tiền (thu hoặc chi).
     *
     * Một Payment có thể:
     *  - Là THU (party_type = CUSTOMER, tiền vào)
     *  - Là CHI (party_type = SUPPLIER, tiền ra)
     *
     * Một Payment có thể được ÁP DỤNG cho nhiều Invoice (qua PaymentApplication):
     *  - applied_amount   = SUM(amount_applied)
     *  - remaining_amount = amount - applied_amount
     *
     * Một Payment có thể được MATCH với 1 BankTransaction (qua FK từ BankTransaction).
     *
     * Một Payment có thể thuộc 1 BulkPayment (khi thanh toán gộp nhiều đơn).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Số phiếu thanh toán duy nhất (VD: PMT-2026-00001)
            $table->string('payment_number', 50)
                ->unique()
                ->comment('Số phiếu thanh toán duy nhất');

            // Loại đối tượng (Enum PartyType, snapshot string)
            $table->string('party_type', 32)
                ->comment('Loại đối tượng: CUSTOMER/SUPPLIER');

            // FK tới customer hoặc supplier (column được map qua Payment::customer() / supplier())
            // Polymorphic FK: lưu party_id + dùng party_type để quyết định bảng
            $table->foreignId('party_id')
                ->comment('ID khách hàng hoặc nhà cung cấp (theo party_type)');

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete()
                ->comment('FK trực tiếp tới customer khi party_type = CUSTOMER');

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete()
                ->comment('FK trực tiếp tới supplier khi party_type = SUPPLIER');

            // Phương thức thanh toán (Enum PaymentMethod, snapshot string)
            $table->string('payment_method', 32)
                ->comment('CASH/BANK_TRANSFER/QR_PAY/E_WALLET/CARD/PLATFORM');

            // Số tiền thanh toán (DECIMAL 15,2 BẮT BUỘC)
            $table->decimal('amount', 15, 2)
                ->comment('Tổng số tiền thanh toán');

            $table->decimal('applied_amount', 15, 2)
                ->default(0)
                ->comment('Tổng tiền đã áp dụng cho invoice (= SUM amount_applied)');

            $table->decimal('remaining_amount', 15, 2)
                ->default(0)
                ->comment('Còn dư (= amount - applied_amount)');

            // Tiền tệ + tỷ giá
            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1)
                ->comment('Tỷ giá quy đổi về VND');

            // Ngày thanh toán (Có thể khác ngày tạo - khách CK trước rồi nhập sau)
            $table->date('payment_date')
                ->comment('Ngày phát sinh thanh toán');

            // Nguồn tiền - FK tới tài khoản nhận/chi
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete()
                ->comment('Tài khoản nhận (thu) hoặc chi (trả)');

            // Mã tham chiếu từ NH / QR / ví
            $table->string('reference', 100)
                ->nullable()
                ->comment('Mã giao dịch NH, mã QR, mã ví...');

            // Trạng thái vòng đời (Enum PaymentStatus, snapshot string)
            $table->string('status', 32)
                ->default('PENDING')
                ->comment('PENDING/APPLIED/FAILED/REFUNDED/CANCELLED');

            // Liên kết BulkPayment (nếu là thanh toán gộp nhiều đơn - bulk)
            $table->foreignId('bulk_payment_id')
                ->nullable()
                ->constrained('bulk_payments')
                ->nullOnDelete()
                ->comment('Thuộc 1 BulkPayment');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            // Audit
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo phiếu');

            $table->timestamps();

            // Index truy vấn phổ biến
            $table->index(['party_type', 'party_id'], 'payments_party_idx');
            $table->index('payment_method', 'payments_method_idx');
            $table->index('payment_date', 'payments_date_idx');
            $table->index('status', 'payments_status_idx');
            $table->index('bank_account_id', 'payments_bank_account_idx');
            $table->index('bulk_payment_id', 'payments_bulk_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
