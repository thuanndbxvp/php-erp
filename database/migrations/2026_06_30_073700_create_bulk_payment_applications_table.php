<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng chi tiết BulkPayment → các Invoice được gom.
     *
     * Cho phép 1 phiếu BulkPayment gom nhiều invoice (cả AR và AP không trộn lẫn).
     */
    public function up(): void
    {
        Schema::create('bulk_payment_applications', function (Blueprint $table) {
            $table->id();

            // FK tới bulk_payments
            $table->foreignId('bulk_payment_id')
                ->constrained('bulk_payments')
                ->cascadeOnDelete()
                ->comment('Phiếu gom thanh toán');

            // Áp dụng cho InvoiceOut (AR)
            $table->foreignId('invoice_out_id')
                ->nullable()
                ->constrained('invoice_outs')
                ->nullOnDelete()
                ->comment('Hóa đơn bán');

            // Áp dụng cho InvoiceIn (AP)
            $table->foreignId('invoice_in_id')
                ->nullable()
                ->constrained('invoice_ins')
                ->nullOnDelete()
                ->comment('Hóa đơn mua');

            // Số tiền phân bổ (DECIMAL 15,2 BẮT BUỘC)
            $table->decimal('amount_applied', 15, 2)
                ->comment('Số tiền phân bổ cho invoice này');

            $table->text('notes')
                ->nullable();

            // Mỗi invoice chỉ xuất hiện 1 lần trong 1 BulkPayment
            $table->unique(['bulk_payment_id', 'invoice_out_id'], 'bulk_app_out_unique');
            $table->unique(['bulk_payment_id', 'invoice_in_id'], 'bulk_app_in_unique');

            $table->index('invoice_out_id', 'bulk_app_out_idx');
            $table->index('invoice_in_id', 'bulk_app_in_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_payment_applications');
    }
};
