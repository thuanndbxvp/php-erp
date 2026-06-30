<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Enums\MovementType;
use App\Enums\RefType;
use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Models\InventoryMovement;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Lịch sử tồn kho';

    protected static ?string $modelLabel = 'Biến động tồn kho';

    protected static ?string $pluralModelLabel = 'Lịch sử biến động';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::QUAN_LY_KHO;

    protected static ?int $navigationSort = 99;

    /**
     * Read-only resource: không cho edit/create.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // Read-only nên form không cần thiết, nhưng Filament yêu cầu override.
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn (MovementType $state): string => $state->label())
                    ->badge()
                    ->color(fn (MovementType $state): string => match ($state) {
                        MovementType::PURCHASE, MovementType::RETURN_IN => 'success',
                        MovementType::SALE, MovementType::RETURN_OUT => 'danger',
                        MovementType::DAMAGE => 'warning',
                        MovementType::ADJUSTMENT, MovementType::TRANSFER => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Sản phẩm')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Kho')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Số lượng')
                    ->numeric(3)
                    ->formatStateUsing(fn (string $state): string =>
                        (float) $state > 0 ? '+'.$state : $state)
                    ->color(fn (string $state): string => (float) $state > 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Đơn giá')
                    ->money('VND')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Thành tiền')
                    ->money('VND')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('VND'),
                    ]),

                Tables\Columns\TextColumn::make('ref_type')
                    ->label('Chứng từ')
                    ->formatStateUsing(fn (?RefType $state): string => $state?->label() ?? '—')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Lý do')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Người thao tác')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại biến động')
                    ->options(MovementType::class)
                    ->native(false),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Kho')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Sản phẩm')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([]) // read-only
            ->bulkActions([]) // read-only
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
        ];
    }
}

