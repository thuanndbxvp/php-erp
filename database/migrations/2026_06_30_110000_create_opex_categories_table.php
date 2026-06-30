<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng danh mục chi phí vận hành (OPEX Categories).
     *
     * Dùng để phân loại OperatingExpense theo nhóm: điện/nước/internet,
     * lương nhân viên, thuê văn phòng, marketing, khác...
     *
     * Mỗi danh mục trỏ về 1 tài khoản kế toán chi phí (REVENUE/EXPENSE) trên
     * Chart of Accounts - đây là TK mặc định sẽ ghi NỢ khi phát sinh chi phí.
     */
    public function up(): void
    {
        Schema::create('opex_categories', function (Blueprint $table) {
            $table->id();

            // Mã danh mục - duy nhất (VD: OPEX-ELEC, OPEX-SAL, OPEX-RENT)
            $table->string('code', 32)
                ->unique()
                ->comment('Mã danh mục OPEX (VD: OPEX-ELEC, OPEX-SAL, OPEX-RENT)');

            // Tên danh mục hiển thị
            $table->string('name')
                ->comment('Tên danh mục chi phí vận hành');

            // Tài khoản kế toán mặc định khi ghi nhận chi phí (TK chi phí EXPENSE)
            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete()
                ->comment('TK kế toán chi phí ghi Nợ mặc định');

            $table->text('description')
                ->nullable()
                ->comment('Mô tả chi tiết danh mục');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Đang sử dụng');

            $table->timestamps();

            $table->index('account_id', 'opex_categories_account_idx');
            $table->index('is_active', 'opex_categories_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opex_categories');
    }
};
