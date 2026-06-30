<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Luật hoa hồng (Commission Rules).
     *
     * Mỗi rule định nghĩa cách tính hoa hồng cho 1 target_type cụ thể:
     *   commission_amount = target_value × rate_percent / 100
     *
     * VD: Rule "Doanh thu tháng 6/2026 - rate 5%" áp cho nhân viên Sales có REVENUE > 0.
     */
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();

            // Tên luật
            $table->string('name')
                ->comment('Tên luật hoa hồng (VD: Hoa hồng 5% doanh thu Sale Q2/2026)');

            // Mô tả
            $table->text('description')
                ->nullable()
                ->comment('Mô tả chi tiết luật');

            // Loại chỉ tiêu - snapshot string, Model sẽ cast Enum
            $table->string('target_type', 32)
                ->comment('REVENUE/ORDER_COUNT/PROFIT/COLLECTED_AMT/NEW_CUSTOMER');

            // % hoa hồng (BẮT BUỘC dùng DECIMAL để tính chính xác)
            $table->decimal('rate_percent', 5, 2)
                ->comment('Tỷ lệ % hoa hồng (VD: 5.50 = 5.5%)');

            // Ngưỡng doanh thu tối thiểu để được hưởng (optional)
            $table->decimal('min_target_amount', 15, 2)
                ->nullable()
                ->comment('Ngưỡng target tối thiểu mới được tính hoa hồng');

            // Hạn mức hoa hồng tối đa / kỳ (optional)
            $table->decimal('max_commission_amount', 15, 2)
                ->nullable()
                ->comment('Hạn mức hoa hồng tối đa / kỳ');

            // Áp dụng từ ngày → đến ngày (dùng cho rule theo mùa / đợt)
            $table->date('effective_from')
                ->nullable()
                ->comment('Hiệu lực từ ngày');

            $table->date('effective_to')
                ->nullable()
                ->comment('Hiệu lực đến ngày');

            // Cờ đang áp dụng
            $table->boolean('is_active')
                ->default(true)
                ->comment('Đang áp dụng');

            $table->timestamps();

            $table->index('target_type', 'commission_rules_target_idx');
            $table->index('is_active', 'commission_rules_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
