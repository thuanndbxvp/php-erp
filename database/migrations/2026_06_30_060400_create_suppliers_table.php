<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng nhà cung cấp - Lưu trữ thông tin NCC cá nhân và doanh nghiệp.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // Mã nhà cung cấp duy nhất - VD: NCC-001
            $table->string('code', 50)->unique()->comment('Mã nhà cung cấp duy nhất');

            // Tên nhà cung cấp
            $table->string('name')->comment('Tên nhà cung cấp');

            // Loại NCC: INDIVIDUAL (cá nhân), COMPANY (doanh nghiệp)
            $table->enum('supplier_type', ['INDIVIDUAL', 'COMPANY'])
                ->default('COMPANY')
                ->comment('Loại NCC: cá nhân / doanh nghiệp');

            // Thông tin liên hệ
            $table->string('email')->nullable()->comment('Email');
            $table->string('phone', 30)->nullable()->comment('Số điện thoại');
            $table->string('website')->nullable()->comment('Website');

            // Mã số thuế
            $table->string('tax_code', 50)->nullable()->comment('Mã số thuế');

            // Địa chỉ
            $table->text('billing_address')->nullable()->comment('Địa chỉ xuất hóa đơn');
            $table->text('shipping_address')->nullable()->comment('Địa chỉ giao hàng');

            // Điều khoản thanh toán
            $table->unsignedSmallInteger('payment_term_days')->default(0)->comment('Số ngày thanh toán');
            $table->decimal('credit_limit', 15, 2)->default(0)->comment('Hạn mức tín dụng');

            // Công nợ phải trả hiện tại (snapshot)
            $table->decimal('current_ap', 15, 2)->default(0)->comment('Công nợ phải trả hiện tại');

            // Thông tin đặt hàng
            $table->unsignedSmallInteger('lead_time_days')->default(0)->comment('Thời gian giao hàng (ngày)');
            $table->decimal('min_order_value', 15, 2)->nullable()->comment('Giá trị đơn hàng tối thiểu');

            // Trạng thái: ACTIVE, INACTIVE, BLOCKED, PENDING
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING'])
                ->default('ACTIVE')
                ->comment('Trạng thái nhà cung cấp');

            // Ghi chú nội bộ
            $table->text('notes')->nullable()->comment('Ghi chú');

            // Tags phân loại (lưu JSON: ["CHINH_HANG","UU_TIEN"])
            $table->json('tags')->nullable()->comment('Tags phân loại');

            $table->timestamps();

            // Index cho các truy vấn thường gặp
            $table->index('supplier_type');
            $table->index('status');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};