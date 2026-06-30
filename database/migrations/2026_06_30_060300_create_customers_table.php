<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng khách hàng - Lưu trữ thông tin khách hàng cá nhân và doanh nghiệp.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Mã khách hàng duy nhất - VD: KH-001
            $table->string('code', 50)->unique()->comment('Mã khách hàng duy nhất');

            // Tên khách hàng / Tên công ty
            $table->string('name')->comment('Tên khách hàng');

            // Loại khách: INDIVIDUAL (cá nhân), COMPANY (doanh nghiệp)
            $table->enum('customer_type', ['INDIVIDUAL', 'COMPANY'])
                ->default('INDIVIDUAL')
                ->comment('Loại khách: cá nhân / doanh nghiệp');

            // Thông tin liên hệ
            $table->string('email')->nullable()->comment('Email');
            $table->string('phone', 30)->nullable()->comment('Số điện thoại');
            $table->string('website')->nullable()->comment('Website');

            // Thông tin doanh nghiệp
            $table->string('tax_code', 50)->nullable()->comment('Mã số thuế');
            $table->string('business_reg_num', 100)->nullable()->comment('Số ĐKKD');

            // Địa chỉ
            $table->text('billing_address')->nullable()->comment('Địa chỉ xuất hóa đơn');
            $table->text('shipping_address')->nullable()->comment('Địa chỉ giao hàng');

            // Hạn mức công nợ cho phép (DECIMAL 15,2)
            $table->decimal('credit_limit', 15, 2)->default(0)->comment('Hạn mức công nợ');

            // Công nợ hiện tại (snapshot, thực tế tính từ invoices)
            $table->decimal('current_ar', 15, 2)->default(0)->comment('Công nợ hiện tại');

            // Số ngày được phép nợ
            $table->unsignedSmallInteger('payment_term_days')->default(0)->comment('Số ngày thanh toán');

            // Trạng thái khách hàng: ACTIVE, INACTIVE, BLOCKED, PENDING
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING'])
                ->default('ACTIVE')
                ->comment('Trạng thái khách hàng');

            // Lý do khóa (nếu bị BLOCKED)
            $table->text('blocked_reason')->nullable()->comment('Lý do khóa khách hàng');

            // Ghi chú nội bộ
            $table->text('notes')->nullable()->comment('Ghi chú');

            // Tags phân loại (lưu JSON: ["VIP","DISTRIBUTOR"])
            $table->json('tags')->nullable()->comment('Tags phân loại khách hàng');

            $table->timestamps();

            // Index cho các truy vấn thường gặp
            $table->index('customer_type');
            $table->index('status');
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};