<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Model lịch sử trạng thái đơn hàng - Audit Trail BẤT BIẾN.
 *
 * Polymorphic: trỏ về SalesOrder HOẶC PurchaseOrder thông qua (order_type, order_id).
 * Bảng này chỉ INSERT, không UPDATE / DELETE.
 *
 * @property int $id
 * @property string $order_type
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property int $changed_by
 * @property \Illuminate\Support\Carbon $changed_at
 * @property string|null $reason
 * @property string|null $notes
 */
class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';

    /**
     * Bảng audit dùng changed_at (không phải created_at) và KHÔNG có updated_at.
     */
    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_type',
        'order_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    // ============= Relationships =============

    /**
     * Polymorphic: lấy về đơn hàng tương ứng (SalesOrder hoặc PurchaseOrder).
     */
    public function order(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'order_type', 'order_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}