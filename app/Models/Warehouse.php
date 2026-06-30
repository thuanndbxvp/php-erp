<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Kho hàng - Đại diện cho kho vật lý/kho ảo trong hệ thống.
 *
 * @property int $id
 * @property string $code Mã kho duy nhất
 * @property string $name Tên kho
 * @property string $type Loại kho: OWN | THIRD_PARTY | VIRTUAL
 * @property string|null $address Địa chỉ kho
 * @property bool $is_default Kho mặc định
 * @property string $status Trạng thái: ACTIVE | INACTIVE
 */
class Warehouse extends Model
{
    /** @use HasFactory<\Database\Factories\WarehouseFactory> */
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Lấy danh sách tồn kho theo sản phẩm tại kho này.
     *
     * @return HasMany<Inventory>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'warehouse_id');
    }

    /**
     * Lấy lịch sử biến động tồn kho của kho.
     *
     * @return HasMany<InventoryMovement>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'warehouse_id');
    }
}