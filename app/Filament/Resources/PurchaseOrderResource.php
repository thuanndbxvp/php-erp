<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Đơn mua';

    protected static ?string $modelLabel = 'Đơn mua';

    protected static ?string $pluralModelLabel = 'Đơn mua hàng';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::MUA_HANG;

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
                            OrderType::DROPSHIP_LINKED->value => OrderType::DROPSHIP_LINKED->label(),
                        ])
                        ->default(OrderType::WAREHOUSE->value)
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            if ($state === OrderType::DROPSHIP_LINKED->value) {
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

                    Forms\Components\Select::make('supplier_id')
                        ->label('Nhà cung cấp')
                        ->required()
                        ->relationship('supplier', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->createOptionForm([
                            Forms\Components\TextInput::make('code')->label('Mã NCC')->required(),
                            Forms\Components\TextInput::make('name')->label('Tên NCC')->required(),
                        ]),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Kho nhập')
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

                    Forms\Components\DatePicker::make('receive_date')
                        ->label('Ngày nhận dự kiến')
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
                                            $set('unit_cost', (string) ($product->buy_price ?? 0));
                                            $set('product_name', $product->name);
                                        }
                                    }
                                })
                                ->columnSpan(4),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Số lượng')
                                ->required()
                                ->numeric()
                                ->default(1)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('unit_cost')
                                ->label('Đơn giá mua')
                                ->required()
                                ->numeric()
                                ->prefix('₫')
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
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Ghi chú')
                        ->rows(2),
                ])
                ->collapsed(),
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
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn (OrderType $state): string => $state->label())
                    ->badge()
                    ->color(fn (OrderType $state): string => match ($state) {
                        OrderType::WAREHOUSE => 'info',
                        OrderType::DROPSHIP_LINKED => 'warning',
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
                        OrderStatus::RECEIVED => 'success',
                        OrderStatus::COMPLETED => 'success',
                        OrderStatus::CANCELLED => 'danger',
                        OrderStatus::REJECTED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Nhà cung cấp')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Kho nhập')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('linkedSalesOrder.order_number')
                    ->label('SO liên kết')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Ngày đặt')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receive_date')
                    ->label('Ngày nhận')
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
                        OrderType::DROPSHIP_LINKED->value => OrderType::DROPSHIP_LINKED->label(),
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Nhà cung cấp')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (PurchaseOrder $record): bool => ! $record->status->isTerminal()),

                Tables\Actions\Action::make('receive')
                    ->label('Nhận hàng')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [OrderStatus::CONFIRMED, OrderStatus::PROCESSING], true))
                    ->action(function (PurchaseOrder $record) {
                        try {
                            app(\App\Services\PurchaseOrderService::class)->receive($record, auth()->user());
                            \Filament\Notifications\Notification::make()
                                ->title('Đã nhập kho')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Lỗi khi nhận hàng')
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
                    ->visible(fn (PurchaseOrder $record): bool => ! $record->status->isTerminal())
                    ->action(function (PurchaseOrder $record, array $data) {
                        try {
                            app(\App\Services\PurchaseOrderService::class)->cancel($record, auth()->user(), $data['reason']);
                            \Filament\Notifications\Notification::make()
                                ->title('Đã hủy đơn')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Lỗi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === OrderStatus::DRAFT),
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}

