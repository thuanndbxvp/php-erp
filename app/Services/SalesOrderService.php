<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RefType;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ đơn bán - Sales Order.
 *
 * Xử lý 2 luồng:
 *  - WAREHOUSE: xuất từ kho (đặt chỗ, xuất kho khi SHIPPED).
 *  - DROPSHIP:  bán giao thẳng (tự sinh PO đối ứng).
 */
class SalesOrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumber,
        private readonly OrderStatusService $statusService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * @param  array<int, array{
     *     product_id: int,
     *     quantity: float|int|string,
     *     unit_price: float|int|string,
     *     base_cost?: float|int|string,
     *     discount_percent?: float|int|string,
     *     discount_amount?: float|int|string,
     *     tax_percent?: float|int|string,
     *     direct_costs?: float|int|string,
     * }>  $linesData
     */
    public function create(
        Customer $customer,
        OrderType $type,
        ?Warehouse $warehouse,
        array $linesData,
        User $actor,
        array $orderMeta = [],
    ): SalesOrder {
        if ($type === OrderType::WAREHOUSE && ! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Đơn WAREHOUSE bắt buộc phải có kho xuất hàng.',
            ]);
        }

        if ($type === OrderType::DROPSHIP && $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Đơn DROPSHIP không được gán kho.',
            ]);
        }

        if (empty($linesData)) {
            throw ValidationException::withMessages([
                'lines' => 'Đơn hàng phải có ít nhất 1 dòng sản phẩm.',
            ]);
        }

        return DB::transaction(function () use ($customer, $type, $warehouse, $linesData, $actor, $orderMeta) {
            $orderNumber = $this->orderNumber->nextSalesOrderNumber();

            // 1. Tạo header
            $order = SalesOrder::create(array_merge([
                'order_number' => $orderNumber,
                'type' => $type->value,
                'status' => OrderStatus::DRAFT->value,
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse?->id,
                'order_date' => $orderMeta['order_date'] ?? now()->toDateString(),
                'ship_date' => $orderMeta['ship_date'] ?? null,
                'currency' => $orderMeta['currency'] ?? 'VND',
                'exchange_rate' => $orderMeta['exchange_rate'] ?? '1',
                'notes' => $orderMeta['notes'] ?? null,
                'internal_notes' => $orderMeta['internal_notes'] ?? null,
                'created_by' => $actor->id,
            ], [
                'subtotal' => '0',
                'discount_amount' => '0',
                'tax_amount' => '0',
                'total_amount' => '0',
                'total_cost' => '0',
            ]));

            // 2. Tạo lines + snapshot product
            $subtotal = '0';
            $totalCost = '0';

            foreach ($linesData as $idx => $lineData) {
                /** @var Product $product */
                $product = Product::findOrFail($lineData['product_id']);

                $qty = (string) $lineData['quantity'];
                $unitPrice = (string) $lineData['unit_price'];
                $discountPercent = (string) ($lineData['discount_percent'] ?? '0');
                $discountAmount = (string) ($lineData['discount_amount'] ?? '0');
                $taxPercent = (string) ($lineData['tax_percent'] ?? '0');
                $directCosts = (string) ($lineData['direct_costs'] ?? '0');

                // baseCost: lấy từ input HOẶC tự lấy từ average_cost nếu WAREHOUSE
                if (isset($lineData['base_cost']) && bccomp((string) $lineData['base_cost'], '0', 2) > 0) {
                    $baseCost = (string) $lineData['base_cost'];
                } elseif ($type === OrderType::WAREHOUSE && $warehouse) {
                    $inv = Inventory::where('product_id', $product->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->first();
                    $baseCost = $inv ? (string) $inv->average_cost : '0';
                } else {
                    // Dropship: phải nhập baseCost khi tạo PO đối ứng
                    $baseCost = '0';
                }

                // Tính dòng
                $lineSubtotal = bcmul($qty, $unitPrice, 2);
                $lineAfterDiscount = bcsub($lineSubtotal, $discountAmount, 2);
                if (bccomp($discountPercent, '0', 2) > 0) {
                    $discountFromPct = bcmul($lineSubtotal, bcdiv($discountPercent, '100', 4), 2);
                    $lineAfterDiscount = bcsub($lineSubtotal, $discountFromPct, 2);
                }

                $taxAmount = '0';
                if (bccomp($taxPercent, '0', 2) > 0) {
                    $taxAmount = bcmul($lineAfterDiscount, bcdiv($taxPercent, '100', 4), 2);
                }

                $lineTotal = bcadd($lineAfterDiscount, $taxAmount, 2);
                $lineCost = bcmul($qty, $baseCost, 2);

                SalesOrderLine::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'unit' => $product->unit,
                    ],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'base_cost' => $baseCost,
                    'line_cost' => $lineCost,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'tax_percent' => $taxPercent,
                    'line_total' => $lineTotal,
                    'direct_costs' => $directCosts,
                    'sort_order' => $idx,
                ]);

                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                $totalCost = bcadd($totalCost, $lineCost, 2);
            }

            // 3. Cập nhật tổng
            $order->subtotal = $subtotal;
            $order->total_cost = $totalCost;
            $order->total_amount = bcadd($subtotal, $order->tax_amount, 2);
            $order->save();

            return $order->fresh('lines');
        });
    }

    /**
     * Duyệt đơn bán (DRAFT → CONFIRMED), đặt chỗ tồn kho với luồng WAREHOUSE.
     */
    public function approve(SalesOrder $order, User $actor): SalesOrder
    {
        if ($order->type === OrderType::WAREHOUSE && $order->warehouse_id) {
            foreach ($order->lines as $line) {
                $this->inventoryService->reserve(
                    $line->product_id,
                    $order->warehouse_id,
                    (string) $line->quantity,
                );
            }
        }

        return $this->statusService->transitionSalesOrder(
            $order,
            OrderStatus::CONFIRMED,
            $actor,
            'Duyệt đơn bán'
        );
    }

    /**
     * Khi SO chuyển sang SHIPPED (xuất kho):
     *  - WAREHOUSE: ghi SALE movement (âm), giải phóng reserved.
     *  - DROPSHIP:   ghi PO link ref, không động vào inventory.
     */
    public function ship(SalesOrder $order, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = $this->statusService->transitionSalesOrder(
                $order,
                OrderStatus::SHIPPED,
                $actor,
                'Xuất kho / giao hàng'
            );

            if ($order->type === OrderType::WAREHOUSE && $order->warehouse_id) {
                foreach ($order->lines as $line) {
                    $qty = (string) $line->quantity;

                    // Ghi sổ cái: xuất kho
                    $this->inventoryService->recordMovement([
                        'product_id' => $line->product_id,
                        'warehouse_id' => $order->warehouse_id,
                        'type' => MovementType::SALE,
                        'quantity' => bcmul($qty, '-1', 3),
                        'unit_cost' => (string) $line->base_cost,
                        'ref_type' => RefType::SALES_ORDER,
                        'ref_id' => $order->id,
                        'reason' => 'Xuất kho bán hàng',
                        'notes' => "SO {$order->order_number}",
                    ], $actor);

                    // Giải phóng đặt chỗ
                    $this->inventoryService->releaseReservation(
                        $line->product_id,
                        $order->warehouse_id,
                        $qty,
                    );
                }
            }

            return $order->fresh();
        });
    }

    /**
     * Hủy đơn bán:
     *  - WAREHOUSE: giải phóng reservation nếu đã CONFIRMED.
     *  - DROPSHIP:  hủy PO đối ứng (cascade).
     */
    public function cancel(SalesOrder $order, User $actor, string $reason): SalesOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            // Nếu đã CONFIRMED và WAREHOUSE → giải phóng reservation
            if (
                $order->type === OrderType::WAREHOUSE
                && $order->warehouse_id
                && in_array($order->status, [
                    OrderStatus::PENDING,
                    OrderStatus::CONFIRMED,
                    OrderStatus::PROCESSING,
                    OrderStatus::SHIPPING,
                ], true)
            ) {
                foreach ($order->lines as $line) {
                    $this->inventoryService->releaseReservation(
                        $line->product_id,
                        $order->warehouse_id,
                        (string) $line->quantity,
                    );
                }
            }

            // Hủy PO đối ứng (dropship)
            if (
                $order->type === OrderType::DROPSHIP
                && $order->linked_purchase_order_id
                && $order->linkedPurchaseOrder
                && ! $order->linkedPurchaseOrder->status->isTerminal()
            ) {
                $po = $order->linkedPurchaseOrder;
                $po->status = OrderStatus::CANCELLED;
                $po->save();
            }

            return $this->statusService->transitionSalesOrder(
                $order,
                OrderStatus::CANCELLED,
                $actor,
                'Hủy đơn bán',
                $reason,
            );
        });
    }

    /**
     * Gắn PO đối ứng (dùng cho luồng dropship khi tạo PO sau).
     */
    public function linkPurchaseOrder(SalesOrder $order, PurchaseOrder $po): void
    {
        $order->linked_purchase_order_id = $po->id;
        $order->save();
    }
}