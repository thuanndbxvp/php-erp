<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng pivot payment_applications - Một Payment map với một InvoiceOut/InvoiceIn.
     *
     * Hỗ trợ cả 2 case:
     *  - Thanh toán 1 phần: 1 Payment áp dụng cho 1 Invoice (amount_applied < invoice.balance_due)
     *  - Thanh toán nhiều HD: 1 Payment áp dụng cho nhiều Invoice (SUM amount_applied ≤ payment.amount)
     *
     * Mỗi payment_id chỉ áp dụng được cho MỘT phiếu InvoiceOut / MỘT phiếu InvoiceIn
     * → unique trên (payment_id, invoice_out_id) và (payment_id, invoice_in_id).
     */
    public function up(): void
    {
        Schema::create('payment_applications', function (Blueprint $table) {
            $table->id();

            // FK tới payment
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete()
                ->comment('Payment gốc');

            // Áp dụng cho InvoiceOut (AR) - nullable
            $table->foreignId('invoice_out_id')
                ->nullable()
                ->constrained('invoice_outs')
                ->nullOnDelete()
                ->comment('Hóa đơn bán (AR)');

            // Áp dụng cho InvoiceIn (AP) - nullable
            $table->foreignId('invoice_in_id')
                ->nullable()
                ->constrained('invoice_ins')
                ->nullOnDelete()
                ->comment('Hóa đơn mua (AP)');

            // Số tiền áp dụng cho lần match này (DECIMAL 15,2 BẮT BUỘC)
            $table->decimal('amount_applied', 15, 2)
                ->comment('Số tiền thanh toán cho invoice trong lần match này');

            $table->timestamp('applied_at')
                ->useCurrent()
                ->comment('Thời điểm match');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            // Đảm bảo mỗi Payment chỉ match với 1 InvoiceOut/InvoiceIn
            $table->unique(['payment_id', 'invoice_out_id'], 'pay_app_out_unique');
            $table->unique(['payment_id', 'invoice_in_id'], 'pay_app_in_unique');

            // Index truy vấn
            $table->index('invoice_out_id', 'pay_app_out_idx');
            $table->index('invoice_in_id', 'pay_app_in_idx');
            $table->index('applied_at', 'pay_app_applied_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_applications');
    }
};
