<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Năm tài chính (Fiscal Year).
     */
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();

            // Năm (4 chữ số, duy nhất)
            $table->unsignedSmallInteger('year')
                ->unique()
                ->comment('Năm tài chính (YYYY)');

            // Ngày bắt đầu / kết thúc
            $table->date('start_date')
                ->comment('Ngày bắt đầu năm TC');

            $table->date('end_date')
                ->comment('Ngày kết thúc năm TC');

            // Trạng thái: OPEN (đang hoạt động), CLOSED (đã đóng - không ghi sổ được nữa), LOCKED (khóa vĩnh viễn)
            $table->string('status', 32)
                ->default('OPEN')
                ->comment('OPEN/CLOSED/LOCKED');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('status', 'fiscal_years_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};