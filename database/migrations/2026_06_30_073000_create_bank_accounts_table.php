<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng tài khoản ngân hàng / tiền mặt / ví điện tử / tài khoản trung gian sàn.
     *
     * Phân biệt 4 loại qua cột account_type:
     *  - CHECKING          : tài khoản thanh toán ngân hàng
     *  - SAVINGS           : tài khoản tiết kiệm
     *  - PLATFORM_CLEARING : tài khoản trung gian sàn TMĐT (Shopee, Lazada, Tiki...)
     *  - WALLET            : ví tiền mặt nội bộ hoặc ví điện tử
     *
     * Mỗi bank_account có 1 số dư đầu kỳ (opening_balance) tại opening_date.
     * Số dư hiện tại = opening_balance + Σ(bank_transactions.amount)
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            // Mã tài khoản nội bộ - duy nhất, dùng để tra cứu (VD: NH-VCB-001, CASH-HCM-01, WALLET-MOMO)
            $table->string('code', 50)
                ->unique()
                ->comment('Mã tài khoản nội bộ duy nhất (VD: NH-VCB-001, WALLET-MOMO)');

            // Tên hiển thị
            $table->string('name')
                ->comment('Tên tài khoản hiển thị');

            // Số tài khoản ngân hàng (NULL với WALLET tiền mặt)
            $table->string('account_number', 50)
                ->nullable()
                ->comment('Số tài khoản ngân hàng thực tế');

            // Tên ngân hàng / Tổ chức tài chính
            $table->string('bank_name', 100)
                ->nullable()
                ->comment('Tên ngân hàng / Tổ chức tài chính (VCB, Momo, ShopeePay...)');

            // Chi nhánh mở tài khoản (null với ví)
            $table->string('bank_branch', 100)
                ->nullable()
                ->comment('Chi nhánh ngân hàng');

            // Loại tài khoản (Enum BankAccountType, snapshot string)
            $table->string('account_type', 32)
                ->comment('Loại: CHECKING/SAVINGS/PLATFORM_CLEARING/WALLET');

            // Tiền tệ mặc định
            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ (ISO 4217)');

            // Số dư đầu kỳ tại opening_date (DECIMAL 15,2 BẮT BUỘC cho tiền tệ)
            $table->decimal('opening_balance', 15, 2)
                ->default(0)
                ->comment('Số dư đầu kỳ');

            $table->date('opening_date')
                ->comment('Ngày mở tài khoản / ngày bắt đầu theo dõi');

            // Đang hoạt động hay đã đóng băng
            $table->boolean('is_active')
                ->default(true)
                ->comment('Đang sử dụng');

            // Tài khoản mặc định khi không chỉ định (chỉ 1 row true)
            $table->boolean('is_default')
                ->default(false)
                ->comment('Tài khoản mặc định');

            // Liên kết Platform (cho PLATFORM_CLEARING): SHOPEE/LAZADA/TIKI
            $table->string('platform_id', 50)
                ->nullable()
                ->comment('Mã sàn TMĐT cho PLATFORM_CLEARING (SHOPEE/LAZADA/TIKI)');

            // Metadata bổ sung (SWIFT code, người liên hệ...)
            $table->json('meta')
                ->nullable()
                ->comment('Metadata mở rộng');

            $table->text('notes')
                ->nullable()
                ->comment('Ghi chú nội bộ');

            // Audit: người tạo
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người tạo tài khoản');

            $table->timestamps();

            // Index truy vấn phổ biến
            $table->index('account_type', 'bank_accounts_type_idx');
            $table->index('is_active', 'bank_accounts_active_idx');
            $table->index('platform_id', 'bank_accounts_platform_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
