<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Đơn bán';

    protected static ?string $modelLabel = 'Đơn bán';

    protected static ?string $pluralModelLabel = 'Đơn bán hàng';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::BAN_HANG;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin đơn')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('order_number')
                        ->label('Mã đơn')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Tự sinh khi lưu'),

                    Forms\Components\Select::make('type')
                        ->label('Loại đơn')
                        ->required()
                        ->options([
                            OrderType::WAREHOUSE->value => OrderType::WAREHOUSE->label(),
                            OrderType::DROPSHIP->value => OrderType::DROPSHIP->label(),
                        ])
                        ->default(OrderType::WAREHOUSE->value)
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            // Reset warehouse khi đổi type
                            if ($state === OrderType::DROPSHIP->value) {
                                $set('warehouse_id', null);
                            }
                        }),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->options(OrderStatus::class)
                        ->default(OrderStatus::DRAFT->value)
                        ->disabled()
                        ->dehydrated(false)
                        ->native(false),

                    Forms\Components\Select::make('customer_id')
                        ->label('Khách hàng')
                        ->required()
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->createOptionForm([
                            Forms\Components\TextInput::make('code')->label('Mã KH')->required(),
                            Forms\Components\TextInput::make('name')->label('Tên KH')->required(),
                        ]),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Kho xuất')
                        ->relationship('warehouse', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('type') === OrderType::WAREHOUSE->value)
                        ->required(fn (Get $get): bool => $get('type') === OrderType::WAREHOUSE->value),

                    Forms\Components\DatePicker::make('order_date')
                        ->label('Ngày đặt')
                        ->required()
                        ->default(now())
                        ->native(false),

                    Forms\Components\DatePicker::make('ship_date')
                        ->label('Ngày giao dự kiến')
                        ->native(false),
                ]),

            Forms\Components\Section::make('Dòng sản phẩm')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Sản phẩm')
                                ->required()
                                ->options(fn (): Collection => Product::query()
                                    ->where('is_active', true)
                                    ->orderBy('sku')
                                    ->get()
                                    ->mapWithKeys(fn (Product $p) => [
                                        $p->id => "{$p->sku} — {$p->name}",
                                    ]))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('unit_price', (string) $product->sell_price);
                                            $set('base_cost', (string) ($product->buy_price ?? 0));
                                            $set('product_name', $product->name);
                                        }
                                    }
                                })
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Số lượng')
                                ->required()
                                ->numeric()
                                ->default(1)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Đơn giá')
                                ->required()
                                ->numeric()
                                ->prefix('₫')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('base_cost')
                                ->label('Giá vốn')
                                ->required()
                                ->numeric()
                                ->prefix('₫')
                                ->helperText('Bắt buộc cho P&L')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('discount_percent')
                                ->label('CK %')
                                ->numeric()
                                ->default(0)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('tax_percent')
                                ->label('VAT %')
                                ->numeric()
                                ->default(0)
                                ->columnSpan(1),

                            Forms\Components\Hidden::make('product_name'),
                        ])
                        ->columns(12)
                        ->columnSpanFull()
                        ->addActionLabel('+ Thêm dòng')
                        ->minItems(1)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string =>
                            ($state['product_name'] ?? null) ? "{$state['product_name']}" : null),
                ]),

            Forms\Components\Section::make('Ghi chú')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Ghi chú cho KH')
                        ->rows(2),

                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Ghi chú nội bộ')
                        ->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Mã đơn')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn (OrderType $state): string => $state->label())
                    ->badge()
                    ->color(fn (OrderType $state): string => match ($state) {
                        OrderType::WAREHOUSE => 'info',
                        OrderType::DROPSHIP => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::DRAFT => 'gray',
                        OrderStatus::PENDING => 'warning',
                        OrderStatus::CONFIRMED => 'info',
                        OrderStatus::PROCESSING => 'primary',
                        OrderStatus::SHIPPING => 'primary',
                        OrderStatus::SHIPPED => 'success',
                        OrderStatus::COMPLETED => 'success',
                        OrderStatus::CANCELLED => 'danger',
                        OrderStatus::REJECTED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Kho')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Ngày đặt')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ship_date')
                    ->label('Ngày giao')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('VND'),
                    ]),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Giá vốn (COGS)')
                    ->money('VND')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Người tạo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(OrderStatus::class)
                    ->native(false),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại đơn')
                    ->options([
                        OrderType::WAREHOUSE->value => OrderType::WAREHOUSE->label(),
                        OrderType::DROPSHIP->value => OrderType::DROPSHIP->label(),
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Khách hàng')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Tables\Filters\Filter::make('order_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('to')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('order_date', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (SalesOrder $record): bool => ! $record->status->isTerminal()),
                Tables\Actions\Action::make('approve')
                    ->label('Duyệt đơn')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SalesOrder $record): bool => $record->status === OrderStatus::DRAFT || $record->status === OrderStatus::PENDING)
                    ->action(function (SalesOrder $record) {
                        try {
                            app(\App\Services\SalesOrderService::class)->approve($record, auth()->user());
                            \Filament\Notifications\Notification::make()
                                ->title('Đã duyệt đơn')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Lỗi khi duyệt đơn')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('ship')
                    ->label('Xuất kho')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (SalesOrder $record): bool => in_array($record->status, [OrderStatus::PROCESSING, OrderStatus::SHIPPING], true))
                    ->action(function (SalesOrder $record) {
                        try {
                            app(\App\Services\SalesOrderService::class)->ship($record, auth()->user());
                            \Filament\Notifications\Notification::make()
                                ->title('Đã xuất kho')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Lỗi khi xuất kho')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Hủy')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Lý do hủy')
                            ->required(),
                    ])
                    ->visible(fn (SalesOrder $record): bool => ! $record->status->isTerminal())
                    ->action(function (SalesOrder $record, array $data) {
                        try {
                            app(\App\Services\SalesOrderService::class)->cancel($record, auth()->user(), $data['reason']);
                            \Filament\Notifications\Notification::make()
                                ->title('Đã hủy đơn')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Lỗi khi hủy đơn')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (SalesOrder $record): bool => $record->status === OrderStatus::DRAFT),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa hàng loạt'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'view' => Pages\ViewSalesOrder::route('/{record}'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
        ];
    }
}

