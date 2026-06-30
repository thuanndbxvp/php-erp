<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $warehouse = isset($data['warehouse_id']) && $data['warehouse_id']
                ? Warehouse::findOrFail($data['warehouse_id'])
                : null;

            $type = \App\Enums\OrderType::from($data['type']);

            $linesData = collect($data['lines'] ?? [])
                ->map(fn (array $line) => [
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'discount_percent' => $line['discount_percent'] ?? 0,
                    'tax_percent' => $line['tax_percent'] ?? 0,
                ])
                ->all();

            /** @var PurchaseOrderService $service */
            $service = app(PurchaseOrderService::class);

            $po = $service->create(
                supplier: $supplier,
                type: $type,
                warehouse: $warehouse,
                linesData: $linesData,
                actor: auth()->user(),
                orderMeta: [
                    'order_date' => $data['order_date'] ?? null,
                    'receive_date' => $data['receive_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
            );

            Notification::make()
                ->title("Đã tạo đơn mua {$po->order_number}")
                ->success()
                ->send();

            return $po;
        });
    }
}