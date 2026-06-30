<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Sản phẩm';

    protected static ?string $modelLabel = 'Sản phẩm';

    protected static ?string $pluralModelLabel = 'Sản phẩm';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::QUAN_LY_KHO;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin cơ bản')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('sku')
                        ->label('Mã SKU')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->placeholder('VD: SP-001'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên sản phẩm')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('category_id')
                        ->label('Danh mục')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\TextInput::make('unit')
                        ->label('Đơn vị tính')
                        ->required()
                        ->maxLength(20)
                        ->default('cái')
                        ->placeholder('cái, hộp, kg...'),

                    Forms\Components\Textarea::make('description')
                        ->label('Mô tả')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Giá & Tồn kho')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('sell_price')
                        ->label('Giá bán')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('buy_price')
                        ->label('Giá mua')
                        ->numeric()
                        ->prefix('₫')
                        ->helperText('Làm giá đề xuất khi tạo PO'),

                    Forms\Components\TextInput::make('min_stock_level')
                        ->label('Tồn kho tối thiểu')
                        ->numeric()
                        ->helperText('Cảnh báo khi tồn khả dụng < mức này')
                        ->default(0),

                    Forms\Components\Toggle::make('is_track_stock')
                        ->label('Theo dõi tồn kho')
                        ->default(true)
                        ->inline(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang kinh doanh')
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('Mã SKU')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên sản phẩm')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sell_price')
                    ->label('Giá bán')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('buy_price')
                    ->label('Giá mua')
                    ->money('VND')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Tồn kho')
                    ->numeric(3)
                    ->getStateUsing(function (Product $record): string {
                        return number_format((float) $record->inventories->sum('quantity_on_hand'), 3);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withSum('inventories as total_stock', 'quantity_on_hand')
                            ->orderBy('total_stock', $direction);
                    })
                    ->color(function (Product $record): string {
                        $stock = (float) $record->inventories->sum('quantity_on_hand');
                        $min = (float) ($record->min_stock_level ?? 0);

                        return $stock <= $min ? 'danger' : 'success';
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Kinh doanh')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_track_stock')
                    ->label('Theo dõi')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Danh mục')
                    ->relationship('category', 'name')
                    ->native(false),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Đang kinh doanh'),
                Tables\Filters\TernaryFilter::make('is_track_stock')
                    ->label('Theo dõi tồn'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\DeleteAction::make()->label('Xóa'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa hàng loạt'),
                ]),
            ])
            ->defaultSort('sku');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

