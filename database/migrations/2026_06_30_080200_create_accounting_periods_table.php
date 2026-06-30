<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Kỳ kế toán (tháng) thuộc 1 Năm tài chính.
     *
     * Mặc định chia 12 kỳ/năm. Trạng thái CLOSED ngăn không cho ghi bút toán.
     */
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();

            // FK năm tài chính
            $table->foreignId('fiscal_year_id')
                ->constrained('fiscal_years')
                ->cascadeOnDelete()
                ->comment('Năm tài chính');

            // Số thứ tự kỳ (1..12 hoặc tuỳ chỉnh)
            $table->unsignedTinyInteger('period_number')
                ->comment('Số thứ tự kỳ trong năm (1-12)');

            // Tên hiển thị
            $table->string('name', 100)
                ->comment('Tên hiển thị (VD: T01/2026)');

            // Ngày bắt đầu / kết thúc
            $table->date('start_date')
                ->comment('Ngày đầu kỳ');

            $table->date('end_date')
                ->comment('Ngày cuối kỳ');

            // Trạng thái OPEN/CLOSED/LOCKED
            $table->string('status', 32)
                ->default('OPEN')
                ->comment('OPEN/CLOSED/LOCKED');

            // Người đóng kỳ
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('closed_at')
                ->nullable();

            $table->timestamps();

            // Unique: trong 1 năm tài chính không trùng số thứ tự kỳ
            $table->unique(['fiscal_year_id', 'period_number'], 'acc_period_unique');
            $table->index('status', 'acc_period_status_idx');
            $table->index(['start_date', 'end_date'], 'acc_period_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};