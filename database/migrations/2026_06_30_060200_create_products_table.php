<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng sản phẩm - Master data hàng hóa trong hệ thống ERP.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Mã SKU duy nhất
            $table->string('sku', 100)->unique()->comment('Mã SKU duy nhất');

            // Tên sản phẩm
            $table->string('name')->comment('Tên sản phẩm');

            // Mô tả sản phẩm
            $table->text('description')->nullable()->comment('Mô tả sản phẩm');

            // Đơn vị tính - VD: cái, kg, lít, thùng
            $table->string('unit', 30)->default('piece')->comment('Đơn vị tính');

            // FK danh mục sản phẩm
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete()
                ->comment('Danh mục sản phẩm');

            // Giá bán lẻ (DECIMAL chuẩn tiền tệ 15,2)
            $table->decimal('sell_price', 15, 2)->default(0)->comment('Giá bán');

            // Giá mua gợi ý (DECIMAL chuẩn tiền tệ 15,2)
            $table->decimal('buy_price', 15, 2)->nullable()->comment('Giá mua gợi ý');

            // Mức tồn kho tối thiểu cảnh báo
            $table->decimal('min_stock_level', 15, 3)->nullable()->comment('Mức tồn kho tối thiểu');

            // Có theo dõi tồn kho hay không
            $table->boolean('is_track_stock')->default(true)->comment('Theo dõi tồn kho');

            // Sản phẩm đang kinh doanh
            $table->boolean('is_active')->default(true)->comment('Đang kinh doanh');

            $table->timestamps();

            // Index cho các truy vấn thường gặp
            $table->index('category_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};