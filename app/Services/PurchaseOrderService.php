<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RefType;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ đơn mua - Purchase Order.
 *
 * Hỗ trợ 2 luồng:
 *  - WAREHOUSE: mua nhập kho (nhập kho khi RECEIVED).
 *  - DROPSHIP_LINKED: PO tự động sinh từ SO dropship (không nhập kho).
 */
class PurchaseOrderService
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
     *     unit_cost: float|int|string,
     *     discount_percent?: float|int|string,
     *     discount_amount?: float|int|string,
     *     tax_percent?: float|int|string,
     *     direct_costs?: float|int|string,
     * }>  $linesData
     */
    public function create(
        Supplier $supplier,
        OrderType $type,
        ?Warehouse $warehouse,
        array $linesData,
        User $actor,
        array $orderMeta = [],
    ): PurchaseOrder {
        if ($type === OrderType::WAREHOUSE && ! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Đơn WAREHOUSE bắt buộc phải có kho nhập hàng.',
            ]);
        }

        if ($type === OrderType::DROPSHIP_LINKED && $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'PO DROPSHIP_LINKED không cần kho.',
            ]);
        }

        if (empty($linesData)) {
            throw ValidationException::withMessages([
                'lines' => 'Đơn mua phải có ít nhất 1 dòng sản phẩm.',
            ]);
        }

        return DB::transaction(function () use ($supplier, $type, $warehouse, $linesData, $actor, $orderMeta) {
            $po = PurchaseOrder::create([
                'order_number' => $this->orderNumber->nextPurchaseOrderNumber(),
                'type' => $type->value,
                'status' => OrderStatus::DRAFT->value,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse?->id,
                'order_date' => $orderMeta['order_date'] ?? now()->toDateString(),
                'receive_date' => $orderMeta['receive_date'] ?? null,
                'currency' => $orderMeta['currency'] ?? 'VND',
                'exchange_rate' => $orderMeta['exchange_rate'] ?? '1',
                'notes' => $orderMeta['notes'] ?? null,
                'linked_sales_order_id' => $orderMeta['linked_sales_order_id'] ?? null,
                'created_by' => $actor->id,
                'subtotal' => '0',
                'discount_amount' => '0',
                'tax_amount' => '0',
                'total_amount' => '0',
            ]);

            $subtotal = '0';

            foreach ($linesData as $idx => $lineData) {
                /** @var Product $product */
                $product = Product::findOrFail($lineData['product_id']);

                $qty = (string) $lineData['quantity'];
                $unitCost = (string) $lineData['unit_cost'];
                $discountPercent = (string) ($lineData['discount_percent'] ?? '0');
                $discountAmount = (string) ($lineData['discount_amount'] ?? '0');
                $taxPercent = (string) ($lineData['tax_percent'] ?? '0');
                $directCosts = (string) ($lineData['direct_costs'] ?? '0');

                $lineSubtotal = bcmul($qty, $unitCost, 2);
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

                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $product->id,
                    'product_snapshot' => [
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'unit' => $product->unit,
                    ],
                    'quantity' => $qty,
                    'ordered_quantity' => $qty,
                    'received_quantity' => '0',
                    'unit_cost' => $unitCost,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'tax_percent' => $taxPercent,
                    'line_total' => $lineTotal,
                    'direct_costs' => $directCosts,
                    'sort_order' => $idx,
                ]);

                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            }

            $po->subtotal = $subtotal;
            $po->total_amount = bcadd($subtotal, $po->tax_amount, 2);
            $po->save();

            return $po->fresh('lines');
        });
    }

    /**
     * Nhận hàng (PROCESSING → RECEIVED), ghi sổ cái PURCHASE movement.
     *
     * @param  array<int, string>|null  $receivedQuantities  Mảng line_id => quantity (mặc định = line.quantity)
     */
    public function receive(PurchaseOrder $po, User $actor, ?array $receivedQuantities = null): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor, $receivedQuantities) {
            $po = $this->statusService->transitionPurchaseOrder(
                $po,
                OrderStatus::RECEIVED,
                $actor,
                'Nhận hàng từ nhà cung cấp'
            );

            // Chỉ nhập kho với PO WAREHOUSE
            if ($po->type === OrderType::WAREHOUSE && $po->warehouse_id) {
                foreach ($po->lines as $line) {
                    $receivedQty = $receivedQuantities[$line->id] ?? (string) $line->quantity;

                    // Cập nhật received_quantity trên line
                    $line->received_quantity = bcadd((string) $line->received_quantity, $receivedQty, 3);

                    // Ghi sổ cái nhập kho
                    $this->inventoryService->recordMovement([
                        'product_id' => $line->product_id,
                        'warehouse_id' => $po->warehouse_id,
                        'type' => MovementType::PURCHASE,
                        'quantity' => $receivedQty,
                        'unit_cost' => (string) $line->unit_cost,
                        'ref_type' => RefType::PURCHASE_ORDER,
                        'ref_id' => $po->id,
                        'reason' => 'Nhập kho từ đơn mua',
                        'notes' => "PO {$po->order_number}",
                    ], $actor);
                }
            }

            return $po->fresh();
        });
    }

    /**
     * Hủy đơn mua.
     */
    public function cancel(PurchaseOrder $po, User $actor, string $reason): PurchaseOrder
    {
        return $this->statusService->transitionPurchaseOrder(
            $po,
            OrderStatus::CANCELLED,
            $actor,
            'Hủy đơn mua',
            $reason,
        );
    }

    /**
     * Sinh PO đối ứng từ SO dropship (gọi khi duyệt SO dropship).
     */
    public function createFromDropshipOrder(
        SalesOrder $so,
        Supplier $supplier,
        User $actor,
        array $linesData,
    ): PurchaseOrder {
        $po = $this->create(
            supplier: $supplier,
            type: OrderType::DROPSHIP_LINKED,
            warehouse: null,
            linesData: $linesData,
            actor: $actor,
            orderMeta: [
                'linked_sales_order_id' => $so->id,
                'notes' => "Tự động sinh từ SO dropship {$so->order_number}",
            ],
        );

        // Liên kết ngược: SO → PO
        $so->linked_purchase_order_id = $po->id;
        $so->save();

        return $po;
    }
}