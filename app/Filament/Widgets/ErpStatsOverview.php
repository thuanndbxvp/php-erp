<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ErpStatsOverview extends BaseWidget
{
    /**
     * Thứ tự hiển thị widget (số nhỏ = trên).
     */
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Đơn bán tháng này
        $soMonth = SalesOrder::query()
            ->whereMonth('order_date', now()->month)
            ->whereYear('order_date', now()->year);

        $soMonthCount = $soMonth->count();
        $soMonthValue = (clone $soMonth)->sum('total_amount');
        $soPending = SalesOrder::where('status', OrderStatus::PENDING)->count();
        $soShipping = SalesOrder::where('status', OrderStatus::SHIPPING)->count();

        // Đơn mua tháng này
        $poMonth = PurchaseOrder::query()
            ->whereMonth('order_date', now()->month)
            ->whereYear('order_date', now()->year);

        $poMonthCount = $poMonth->count();
        $poMonthValue = (clone $poMonth)->sum('total_amount');
        $poProcessing = PurchaseOrder::where('status', OrderStatus::PROCESSING)->count();

        // Tồn kho
        $totalProducts = Product::where('is_active', true)->count();
        $lowStock = Product::query()
            ->where('is_track_stock', true)
            ->whereHas('inventories', function ($q) {
                $q->whereRaw('quantity_on_hand - quantity_reserved <= products.min_stock_level');
            })
            ->count();

        // Số liệu danh mục
        $warehouseCount = Warehouse::where('status', 'ACTIVE')->count();
        $customerCount = Customer::where('status', 'ACTIVE')->count();
        $supplierCount = Supplier::where('status', 'ACTIVE')->count();

        return [
            Stat::make('Đơn bán tháng này', number_format($soMonthCount))
                ->description(number_format((float) $soMonthValue, 0, ',', '.') . ' ₫')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ])
                ->url('/admin/sales-orders'),

            Stat::make('Đơn bán chờ xử lý', number_format($soPending + $soShipping))
                ->description("PENDING: {$soPending} · SHIPPING: {$soShipping}")
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url('/admin/sales-orders'),

            Stat::make('Đơn mua tháng này', number_format($poMonthCount))
                ->description(number_format((float) $poMonthValue, 0, ',', '.') . ' ₫')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info')
                ->url('/admin/purchase-orders'),

            Stat::make('PO đang xử lý', number_format($poProcessing))
                ->description('Chờ nhận hàng từ NCC')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary')
                ->url('/admin/purchase-orders'),

            Stat::make('Sản phẩm kinh doanh', number_format($totalProducts))
                ->description("{$warehouseCount} kho · {$customerCount} KH · {$supplierCount} NCC")
                ->descriptionIcon('heroicon-m-cube')
                ->color('gray'),

            Stat::make('Cảnh báo tồn kho', number_format($lowStock))
                ->description('Sản phẩm dưới định mức')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStock > 0 ? 'danger' : 'success'),
        ];
    }
}