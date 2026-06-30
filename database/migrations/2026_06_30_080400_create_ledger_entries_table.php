<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Dòng bút toán (Ledger Entry / Sổ cái).
     *
     * Mỗi LedgerEntry thuộc 1 JournalEntry, ghi NỢ hoặc CÓ cho 1 ChartOfAccount.
     *
     * Quy tắc double-entry: với mỗi JournalEntry POSTED,
     *   SUM(amount) của rows có dc='DEBIT'  =  SUM(amount) của rows có dc='CREDIT'
     *
     * Số dư tài khoản = SUM(debit) - SUM(credit) cho ASSET/EXPENSE,
     *                  = SUM(credit) - SUM(debit) cho LIABILITY/EQUITY/REVENUE.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            // FK header
            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->cascadeOnDelete()
                ->comment('Bút toán cha');

            // FK tài khoản kế toán
            $table->foreignId('chart_of_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete()
                ->comment('Tài khoản kế toán');

            // DEBIT / CREDIT
            $table->string('dc', 16)
                ->comment('DEBIT/CREDIT');

            // Số tiền (DECIMAL 15,2 BẮT BUỘC, luôn > 0)
            $table->decimal('amount', 15, 2)
                ->comment('Số tiền dòng (> 0)');

            // Tiền tệ + tỷ giá
            $table->string('currency', 3)
                ->default('VND');

            $table->decimal('exchange_rate', 15, 4)
                ->default(1);

            $table->decimal('amount_base', 15, 2)
                ->comment('Quy đổi về VND (amount × exchange_rate)');

            // Mô tả dòng (có thể khác với journal description)
            $table->string('description')
                ->nullable()
                ->comment('Mô tả dòng');

            // Sổ phụ: lưu thêm chi tiết KH/NCC/Sản phẩm (FK tuỳ biến)
            $table->string('party_type', 32)
                ->nullable()
                ->comment('CUSTOMER/SUPPLIER');

            $table->unsignedBigInteger('party_id')
                ->nullable()
                ->comment('ID KH/NCC');

            // Ngày hạch toán (thường = entry_date, nhưng có thể khác khi điều chỉnh)
            $table->date('posting_date')
                ->comment('Ngày hạch toán');

            // Trạng thái: ACTIVE / REVERSED
            $table->string('status', 32)
                ->default('ACTIVE')
                ->comment('ACTIVE/REVERSED');

            // Nếu là reversal: trỏ về dòng gốc
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->constrained('ledger_entries')
                ->nullOnDelete()
                ->comment('Dòng gốc (khi là reversal)');

            $table->timestamps();

            $table->index('journal_entry_id', 'ledger_journal_idx');
            $table->index('chart_of_account_id', 'ledger_account_idx');
            $table->index('posting_date', 'ledger_posting_date_idx');
            $table->index(['party_type', 'party_id'], 'ledger_party_idx');
            $table->index('dc', 'ledger_dc_idx');
            $table->index('status', 'ledger_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};