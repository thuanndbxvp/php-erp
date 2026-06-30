<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\RefType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service thao tác tồn kho - LÕI NGHIỆP VỤ của ERP.
 *
 * NGUYÊN TẮC:
 *  - KHÔNG BAO GIỜ UPDATE / DELETE dòng InventoryMovement.
 *  - Mỗi biến động đều phải có 1 dòng InventoryMovement + cập nhật Inventory.
 *  - Wrap trong DB transaction.
 *  - Quantity: dương = nhập, âm = xuất.
 *  - MovementType.defaultSign() cung cấp dấu mặc định.
 */
class InventoryService
{
    /**
     * Ghi nhận một biến động tồn kho.
     *
     * @param  array{
     *     product_id: int,
     *     warehouse_id: int,
     *     type: MovementType,
     *     quantity: float|int|string,
     *     unit_cost?: float|int|string,
     *     reason?: string|null,
     *     notes?: string|null,
     *     ref_type?: RefType|null,
     *     ref_id?: int|null,
     * }  $movement
     */
    public function recordMovement(array $movement, User $actor): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $actor) {
            // 1. Validate input cơ bản
            $product = Product::findOrFail($movement['product_id']);
            $warehouse = Warehouse::findOrFail($movement['warehouse_id']);

            $quantity = (string) $movement['quantity'];
            $type = $movement['type'] instanceof MovementType
                ? $movement['type']
                : MovementType::from((string) $movement['type']);

            $unitCost = (string) ($movement['unit_cost'] ?? '0');

            if (bccomp($quantity, '0', 3) === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng biến động phải khác 0.',
                ]);
            }

            // 2. Ghi dòng sổ cái (inventory_movements) - BẤT BIẾN
            $totalValue = bcmul($quantity, $unitCost, 2);

            $invMovement = InventoryMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => $type->value,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_value' => $totalValue,
                'ref_type' => isset($movement['ref_type'])
                    ? ($movement['ref_type'] instanceof RefType ? $movement['ref_type']->value : (string) $movement['ref_type'])
                    : null,
                'ref_id' => $movement['ref_id'] ?? null,
                'reason' => $movement['reason'] ?? null,
                'notes' => $movement['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            // 3. Cập nhật / tạo mới snapshot tồn kho
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'quantity_in_transit' => 0,
                    'average_cost' => 0,
                ]
            );

            // Cộng dồn tồn thực tế
            $inventory->quantity_on_hand = bcadd(
                (string) $inventory->quantity_on_hand,
                $quantity,
                3
            );

            // Cập nhật giá vốn bình quân gia quyền (WAC) - chỉ áp dụng khi NHẬP kho
            if (bccomp($quantity, '0', 3) > 0) {
                $oldQty = (string) $inventory->quantity_on_hand;
                $oldCost = (string) $inventory->average_cost;
                // Trừ lại biến động vừa cộng để tính "trước khi cập nhật"
                $prevQty = bcsub($oldQty, $quantity, 3);

                if (bccomp($prevQty, '0', 3) > 0 && bccomp($oldCost, '0', 2) > 0) {
                    // Trộn 2 lô theo WAC
                    $totalValueBefore = bcmul($prevQty, $oldCost, 2);
                    $newTotalValue = bcadd($totalValueBefore, $totalValue, 2);
                    $newQty = bcadd($prevQty, $quantity, 3);
                    $inventory->average_cost = bcdiv($newTotalValue, $newQty, 2);
                } else {
                    // Lô đầu tiên → lấy giá nhập làm WAC
                    $inventory->average_cost = bcdiv($totalValue, $quantity, 2);
                }
            }

            $inventory->save();

            return $invMovement;
        });
    }

    /**
     * Đặt chỗ tồn kho khi SO chuyển sang CONFIRMED.
     *
     * @throws ValidationException nếu không đủ hàng khả dụng
     */
    public function reserve(int $productId, int $warehouseId, string $quantity): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $quantity) {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                throw ValidationException::withMessages([
                    'inventory' => "Không tồn tại tồn kho cho sản phẩm #{$productId} tại kho #{$warehouseId}.",
                ]);
            }

            $available = bcsub(
                (string) $inventory->quantity_on_hand,
                (string) $inventory->quantity_reserved,
                3
            );

            if (bccomp($available, $quantity, 3) < 0) {
                throw ValidationException::withMessages([
                    'inventory' => "Không đủ tồn kho khả dụng. Có {$available}, cần {$quantity}.",
                ]);
            }

            $inventory->quantity_reserved = bcadd(
                (string) $inventory->quantity_reserved,
                $quantity,
                3
            );
            $inventory->save();
        });
    }

    /**
     * Giải phóng đặt chỗ (rollback khi hủy SO / hoàn tất xuất kho).
     */
    public function releaseReservation(int $productId, int $warehouseId, string $quantity): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $quantity) {
            $inventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                return;
            }

            $inventory->quantity_reserved = bcsub(
                (string) $inventory->quantity_reserved,
                $quantity,
                3
            );

            // Tránh âm do rounding
            if (bccomp((string) $inventory->quantity_reserved, '0', 3) < 0) {
                $inventory->quantity_reserved = '0.000';
            }

            $inventory->save();
        });
    }
}