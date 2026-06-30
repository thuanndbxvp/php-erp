<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng danh mục sản phẩm - Hỗ trợ cấu trúc cây phân cấp cha-con.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Mã danh mục (duy nhất) - VD: DM-001
            $table->string('code', 50)->unique()->comment('Mã danh mục duy nhất');

            // Tên danh mục - VD: Điện tử, Thời trang nam
            $table->string('name')->comment('Tên danh mục');

            // Mô tả danh mục
            $table->text('description')->nullable()->comment('Mô tả danh mục');

            // FK tự tham chiếu - hỗ trợ cấu trúc cây
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete()
                ->comment('Danh mục cha (hỗ trợ đa cấp)');

            // Hình ảnh đại diện
            $table->string('image')->nullable()->comment('Đường dẫn hình ảnh');

            // Trạng thái hoạt động
            $table->boolean('is_active')->default(true)->comment('Đang hoạt động');

            // Thứ tự hiển thị
            $table->unsignedInteger('sort_order')->default(0)->comment('Thứ tự hiển thị');

            $table->timestamps();

            // Index cho các truy vấn thường gặp
            $table->index('parent_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};