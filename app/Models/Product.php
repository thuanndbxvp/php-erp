<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Sản phẩm - Master data hàng hóa.
 *
 * @property int $id
 * @property string $sku Mã SKU duy nhất
 * @property string $name Tên sản phẩm
 * @property string|null $description Mô tả sản phẩm
 * @property string $unit Đơn vị tính
 * @property int|null $category_id ID danh mục
 * @property string $sell_price Giá bán (DECIMAL 15,2)
 * @property string|null $buy_price Giá mua (DECIMAL 15,2)
 * @property string|null $min_stock_level Mức tồn kho tối thiểu
 * @property bool $is_track_stock Có theo dõi tồn kho
 * @property bool $is_active Đang kinh doanh
 */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'sku',
        'name',
        'description',
        'unit',
        'category_id',
        'sell_price',
        'buy_price',
        'min_stock_level',
        'is_track_stock',
        'is_active',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'buy_price' => 'decimal:2',
        'min_stock_level' => 'decimal:3',
        'is_track_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Danh mục của sản phẩm.
     *
     * @return BelongsTo<Category, Product>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Danh sách tồn kho của sản phẩm (theo từng kho).
     *
     * @return HasMany<Inventory>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_id');
    }
}