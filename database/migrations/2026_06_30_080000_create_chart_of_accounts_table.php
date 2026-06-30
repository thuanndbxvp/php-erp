<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Hệ thống tài khoản kế toán (Chart of Accounts).
     *
     * Theo TT 200/2014/TT-BTC (TT200) của Việt Nam:
     *  - Loại 1xx: Tài sản
     *  - Loại 2xx: Tài sản (tiếp) / chi phí / dở dang
     *  - Loại 3xx: Nợ phải trả
     *  - Loại 4xx: Vốn chủ sở hữu
     *  - Loại 5xx: Doanh thu
     *  - Loại 6xx: Chi phí (lương, vật liệu...)
     *  - Loại 7xx: Chi phí (khác)
     *  - Loại 8xx: Chi phí (quản lý, bán hàng)
     *  - Loại 9xx: Xác định KQKD
     *
     * Mỗi account có 1 `code` duy nhất (VD: 1111, 131, 5111).
     * Có thể tổ chức cây phân cấp qua `parent_id`.
     */
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();

            // Mã tài khoản - duy nhất theo TT200 (VD: 1111, 131, 5111)
            $table->string('code', 20)
                ->unique()
                ->comment('Mã TK kế toán (VD: 1111, 131, 5111)');

            // Tên tài khoản
            $table->string('name')
                ->comment('Tên tài khoản');

            // Loại (ASSET/LIABILITY/EQUITY/REVENUE/EXPENSE)
            $table->string('type', 32)
                ->comment('Loại tài khoản');

            // Tài khoản cha (cây phân cấp)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete()
                ->comment('Tài khoản cha (cây phân cấp)');

            // Tiền tệ mặc định
            $table->string('currency', 3)
                ->default('VND')
                ->comment('Mã tiền tệ');

            // Có ghi nhận sổ phụ / chi tiết theo đối tượng không (AR/AP)
            $table->boolean('is_detail')
                ->default(true)
                ->comment('True = TK chi tiết (ghi sổ), False = TK tổng hợp');

            // Cho phép ghi nợ / có trực tiếp
            $table->boolean('is_active')
                ->default(true)
                ->comment('Đang sử dụng');

            // Có hiển thị trên báo cáo (P&L / BS / TB)
            $table->boolean('show_in_reports')
                ->default(true)
                ->comment('Hiển thị trên báo cáo');

            // Mô tả chi tiết
            $table->text('description')
                ->nullable()
                ->comment('Mô tả chi tiết');

            $table->timestamps();

            $table->index('type', 'chart_accounts_type_idx');
            $table->index('parent_id', 'chart_accounts_parent_idx');
            $table->index('is_active', 'chart_accounts_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};