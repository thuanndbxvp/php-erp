<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng kho hàng - Lưu trữ thông tin kho vật lý/kho ảo của hệ thống.
     */
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();

            // Mã kho (duy nhất) - VD: WH-001, WH-HN-01
            $table->string('code', 50)->unique()->comment('Mã kho duy nhất');

            // Tên kho - VD: Kho Hà Nội, Kho TP.HCM
            $table->string('name')->comment('Tên kho');

            // Loại kho: OWN (kho tự có), THIRD_PARTY (thuê ngoài), VIRTUAL (kho ảo cho dropship)
            $table->enum('type', ['OWN', 'THIRD_PARTY', 'VIRTUAL'])
                ->default('OWN')
                ->comment('Loại kho: OWN, THIRD_PARTY, VIRTUAL');

            // Địa chỉ chi tiết
            $table->string('address')->nullable()->comment('Địa chỉ kho');

            // Kho mặc định của hệ thống
            $table->boolean('is_default')->default(false)->comment('Kho mặc định');

            // Trạng thái: ACTIVE (đang hoạt động), INACTIVE (ngừng hoạt động)
            $table->enum('status', ['ACTIVE', 'INACTIVE'])
                ->default('ACTIVE')
                ->comment('Trạng thái kho');

            $table->timestamps();

            // Index cho các truy vấn thường gặp
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};