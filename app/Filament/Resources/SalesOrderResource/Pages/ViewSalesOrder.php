<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Actions;
use Filament\Infolists;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    public function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                SchemaComponents\Section::make('Thông tin đơn')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number')->label('Mã đơn')->weight('bold'),
                        Infolists\Components\TextEntry::make('type')->label('Loại')
                            ->formatStateUsing(fn ($state) => $state->label()),
                        Infolists\Components\TextEntry::make('status')->label('Trạng thái')
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->badge(),
                        Infolists\Components\TextEntry::make('customer.name')->label('Khách hàng'),
                        Infolists\Components\TextEntry::make('warehouse.name')->label('Kho xuất')
                            ->placeholder('— (dropship)'),
                        Infolists\Components\TextEntry::make('creator.name')->label('Người tạo'),
                        Infolists\Components\TextEntry::make('order_date')->label('Ngày đặt')->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('ship_date')->label('Ngày giao')->date('d/m/Y')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('linkedPurchaseOrder.order_number')
                            ->label('PO liên kết (dropship)')
                            ->placeholder('—')
                            ->url(fn (SalesOrder $record) => $record->linked_purchase_order_id
                                ? \App\Filament\Resources\PurchaseOrderResource::getUrl('view', ['record' => $record->linked_purchase_order_id])
                                : null),
                    ]),

                SchemaComponents\Section::make('Dòng sản phẩm')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lines')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product_snapshot.name')->label('Sản phẩm'),
                                Infolists\Components\TextEntry::make('product_snapshot.sku')->label('SKU'),
                                Infolists\Components\TextEntry::make('quantity')->label('SL'),
                                Infolists\Components\TextEntry::make('unit_price')->label('Đơn giá')->money('VND'),
                                Infolists\Components\TextEntry::make('base_cost')->label('Giá vốn')->money('VND'),
                                Infolists\Components\TextEntry::make('line_cost')->label('COGS dòng')->money('VND'),
                                Infolists\Components\TextEntry::make('line_total')->label('Thành tiền')->money('VND'),
                            ])
                            ->columns(7),
                    ]),

                SchemaComponents\Section::make('Tổng hợp')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')->label('Tổng trước thuế')->money('VND'),
                        Infolists\Components\TextEntry::make('total_cost')->label('Tổng giá vốn')->money('VND'),
                        Infolists\Components\TextEntry::make('total_amount')->label('Tổng thanh toán')->money('VND')
                            ->weight('bold')->size('lg'),
                    ]),

                SchemaComponents\Section::make('Lịch sử trạng thái')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('statusHistory')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('from_status')
                                    ->label('Từ')
                                    ->formatStateUsing(fn ($state) => $state?->label() ?? '—')
                                    ->placeholder('Tạo mới'),
                                Infolists\Components\TextEntry::make('to_status')
                                    ->label('Sang')
                                    ->formatStateUsing(fn ($state) => $state->label()),
                                Infolists\Components\TextEntry::make('changedByUser.name')->label('Bởi'),
                                Infolists\Components\TextEntry::make('changed_at')->label('Lúc')->dateTime('d/m/Y H:i'),
                                Infolists\Components\TextEntry::make('reason')->label('Lý do')->placeholder('—'),
                            ])
                            ->columns(5),
                    ])
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Sửa'),
        ];
    }
}