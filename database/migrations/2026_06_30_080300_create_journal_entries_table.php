<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Bút toán (Journal Entry - header).
     *
     * Mỗi JournalEntry gồm ≥2 LedgerEntry (chứng từ ghi sổ chi tiết).
     * Quy tắc: SUM(debit) = SUM(credit) cho 1 JournalEntry.
     *
     * Polymorphic ref (ref_type, ref_id): liên kết ngược về nguồn phát sinh
     *   - INVOICE_OUT  -> InvoiceOut
     *   - INVOICE_IN   -> InvoiceIn
     *   - PAYMENT      -> Payment
     *   - BANK_TX      -> BankTransaction
     *   - PLATFORM_TX  -> PlatformTransaction
     *   - MANUAL       -> bút toán thủ công
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            // Số bút toán (VD: JE-2026-000001)
            $table->string('journal_number', 50)
                ->unique()
                ->comment('Số bút toán duy nhất');

            // FK kỳ kế toán (để khóa theo kỳ)
            $table->foreignId('accounting_period_id')
                ->constrained('accounting_periods')
                ->restrictOnDelete()
                ->comment('Kỳ kế toán');

            // Ngày bút toán
            $table->date('entry_date')
                ->comment('Ngày bút toán');

            // Loại bút toán (Enum JournalType - đã có)
            $table->string('type', 32)
                ->comment('PAYMENT_IN/PAYMENT_OUT/JOURNAL/OPENING/CLOSING');

            // Mô tả ngắn
            $table->string('description')
                ->comment('Mô tả ngắn gọn');

            // Trạng thái (Enum JournalStatus, snapshot string)
            $table->string('status', 32)
                ->default('DRAFT')
                ->comment('DRAFT/POSTED/REVERSED');

            // Tổng Nợ / Có (snapshot khi POSTED - phục vụ truy vấn nhanh)
            $table->decimal('total_debit', 15, 2)
                ->default(0)
                ->comment('Tổng Nợ (= tổng Có khi POSTED)');

            $table->decimal('total_credit', 15, 2)
                ->default(0)
                ->comment('Tổng Có (= tổng Nợ khi POSTED)');

            // Tiền tệ
            $table->string('currency', 3)
                ->default('VND');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1);

            // Người tạo + người post
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Người tạo');

            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người ghi sổ');

            $table->timestamp('posted_at')
                ->nullable()
                ->comment('Thời điểm ghi sổ');

            // Bút toán đảo ngược (nếu REVERSED)
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete()
                ->comment('Bút toán gốc (khi là reversal)');

            $table->foreignId('reversed_by_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete()
                ->comment('Bút toán đảo ngược (nếu đã bị reverse)');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            $table->timestamps();

            // Polymorphic ref - lưu type + id để truy ngược nguồn
            $table->string('ref_type', 32)
                ->nullable()
                ->comment('Loại nguồn: INVOICE_OUT/INVOICE_IN/PAYMENT/BANK_TX/PLATFORM_TX/MANUAL');

            $table->unsignedBigInteger('ref_id')
                ->nullable()
                ->comment('ID nguồn');

            $table->index(['ref_type', 'ref_id'], 'journal_entries_ref_idx');
            $table->index('status', 'journal_entries_status_idx');
            $table->index('entry_date', 'journal_entries_date_idx');
            $table->index('type', 'journal_entries_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};