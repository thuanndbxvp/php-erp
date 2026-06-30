<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Override create: dùng SalesOrderService để tạo đơn có tính tổng chuẩn.
     */
    protected function handleRecordCreation(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);
            $warehouse = isset($data['warehouse_id']) && $data['warehouse_id']
                ? Warehouse::findOrFail($data['warehouse_id'])
                : null;

            $type = \App\Enums\OrderType::from($data['type']);

            $linesData = collect($data['lines'] ?? [])
                ->map(fn (array $line) => [
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'base_cost' => $line['base_cost'] ?? 0,
                    'discount_percent' => $line['discount_percent'] ?? 0,
                    'tax_percent' => $line['tax_percent'] ?? 0,
                ])
                ->all();

            /** @var SalesOrderService $service */
            $service = app(SalesOrderService::class);

            $order = $service->create(
                customer: $customer,
                type: $type,
                warehouse: $warehouse,
                linesData: $linesData,
                actor: auth()->user(),
                orderMeta: [
                    'order_date' => $data['order_date'] ?? null,
                    'ship_date' => $data['ship_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'internal_notes' => $data['internal_notes'] ?? null,
                ],
            );

            Notification::make()
                ->title("Đã tạo đơn {$order->order_number}")
                ->success()
                ->send();

            return $order;
        });
    }
}