<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Danh mục sản phẩm - Hỗ trợ cấu trúc cây phân cấp cha-con.
 *
 * @property int $id
 * @property string $code Mã danh mục
 * @property string $name Tên danh mục
 * @property string|null $description Mô tả
 * @property int|null $parent_id ID danh mục cha
 * @property string|null $image Đường dẫn hình ảnh
 * @property bool $is_active Đang hoạt động
 * @property int $sort_order Thứ tự hiển thị
 */
class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Danh mục cha.
     *
     * @return BelongsTo<Category, Category>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Danh sách danh mục con trực tiếp.
     *
     * @return HasMany<Category>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Danh sách sản phẩm thuộc danh mục.
     *
     * @return HasMany<Product>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}