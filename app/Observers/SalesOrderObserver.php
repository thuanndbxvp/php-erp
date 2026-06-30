<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\SalesOrder;
use App\Services\HR\CommissionService;
use Illuminate\Support\Facades\Log;

/**
 * Observer cho SalesOrder.
 *
 * - Khi SO chuyển sang SHIPPED hoặc COMPLETED (và trước đó chưa phải 1 trong 2)
 *   → gọi CommissionService::calculateForSalesOrder() để tự động sinh Commission.
 * - Không throw error lên (try/catch + log) vì commission là side-effect,
 *   SO transition vẫn phải thành công kể cả khi commission fail.
 */
class SalesOrderObserver
{
    public function __construct(
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * Hook sau khi SO được cập nhật (chỉ xử lý khi status thực sự đổi).
     */
    public function updated(SalesOrder $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $newStatus = $order->status;
        $oldStatus = $order->getOriginal('status');

        // Chỉ trigger khi chuyển sang SHIPPED / COMPLETED
        if (! in_array($newStatus, [OrderStatus::SHIPPED, OrderStatus::COMPLETED], true)) {
            return;
        }

        // Tránh re-trigger nếu vẫn ở cùng trạng thái terminal
        if (in_array($oldStatus, [OrderStatus::SHIPPED, OrderStatus::COMPLETED], true)) {
            return;
        }

        try {
            $commission = $this->commissionService->calculateForSalesOrder($order->fresh());
            if ($commission) {
                Log::info("Auto-generated Commission #{$commission->id} for SO {$order->order_number}");
            }
        } catch (\Throwable $e) {
            Log::warning("Commission calc failed for SO {$order->order_number}: " . $e->getMessage());
        }
    }
}
