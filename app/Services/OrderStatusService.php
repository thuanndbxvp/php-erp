<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Service quản lý State Machine cho đơn hàng.
 *
 * State Machine SalesOrder:
 *   DRAFT → PENDING → CONFIRMED → PROCESSING → SHIPPING → SHIPPED → COMPLETED
 *           ↘ CANCELLED (từ bất kỳ trạng thái non-terminal nào)
 *
 * State Machine PurchaseOrder:
 *   DRAFT → PENDING → CONFIRMED → PROCESSING → RECEIVED → COMPLETED
 *           ↘ CANCELLED / REJECTED
 *
 * Mọi chuyển trạng thái đều ghi vào order_status_history (polymorphic).
 */
class OrderStatusService
{
    /**
     * Ánh xạ trạng thái tiếp theo hợp lệ từ trạng thái hiện tại.
     */
    private const ALLOWED_TRANSITIONS = [
        // ===== Sales Order =====
        'SALES_ORDER' => [
            'DRAFT' => ['PENDING', 'CANCELLED'],
            'PENDING' => ['CONFIRMED', 'REJECTED', 'CANCELLED'],
            'CONFIRMED' => ['PROCESSING', 'CANCELLED'],
            'PROCESSING' => ['SHIPPING', 'CANCELLED'],
            'SHIPPING' => ['SHIPPED', 'CANCELLED'],
            'SHIPPED' => ['COMPLETED'],
            // COMPLETED / CANCELLED / REJECTED → terminal
        ],

        // ===== Purchase Order =====
        'PURCHASE_ORDER' => [
            'DRAFT' => ['PENDING', 'CANCELLED'],
            'PENDING' => ['CONFIRMED', 'REJECTED', 'CANCELLED'],
            'CONFIRMED' => ['PROCESSING', 'CANCELLED'],
            'PROCESSING' => ['RECEIVED', 'CANCELLED'],
            'RECEIVED' => ['COMPLETED'],
            // COMPLETED / CANCELLED / REJECTED → terminal
        ],
    ];

    /**
     * Chuyển trạng thái SalesOrder.
     *
     * @throws ValidationException nếu chuyển trạng thái không hợp lệ
     */
    public function transitionSalesOrder(
        SalesOrder $order,
        OrderStatus $toStatus,
        User $actor,
        ?string $reason = null,
        ?string $notes = null,
    ): SalesOrder {
        $fromStatus = $order->status;
        $this->assertTransitionAllowed('SALES_ORDER', $fromStatus, $toStatus);

        $order->status = $toStatus;

        if ($toStatus === OrderStatus::CONFIRMED && ! $order->approved_by) {
            $order->approved_by = $actor->id;
        }

        $order->save();

        $this->recordHistory('SALES_ORDER', $order->id, $fromStatus, $toStatus, $actor, $reason, $notes);

        return $order;
    }

    /**
     * Chuyển trạng thái PurchaseOrder.
     *
     * @throws ValidationException nếu chuyển trạng thái không hợp lệ
     */
    public function transitionPurchaseOrder(
        PurchaseOrder $order,
        OrderStatus $toStatus,
        User $actor,
        ?string $reason = null,
        ?string $notes = null,
    ): PurchaseOrder {
        $fromStatus = $order->status;
        $this->assertTransitionAllowed('PURCHASE_ORDER', $fromStatus, $toStatus);

        $order->status = $toStatus;

        if ($toStatus === OrderStatus::CONFIRMED && ! $order->approved_by) {
            $order->approved_by = $actor->id;
        }

        $order->save();

        $this->recordHistory('PURCHASE_ORDER', $order->id, $fromStatus, $toStatus, $actor, $reason, $notes);

        return $order;
    }

    /**
     * Kiểm tra chuyển trạng thái có hợp lệ không.
     */
    private function assertTransitionAllowed(string $orderType, OrderStatus $from, OrderStatus $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$orderType][$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển đơn từ [{$from->label()}] sang [{$to->label()}].",
            ]);
        }
    }

    /**
     * Ghi log lịch sử chuyển trạng thái (polymorphic).
     */
    private function recordHistory(
        string $orderType,
        int $orderId,
        ?OrderStatus $fromStatus,
        OrderStatus $toStatus,
        User $actor,
        ?string $reason,
        ?string $notes,
    ): OrderStatusHistory {
        return OrderStatusHistory::create([
            'order_type' => $orderType,
            'order_id' => $orderId,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'reason' => $reason,
            'notes' => $notes,
        ]);
    }
}