<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament Resource quản lý Kho hàng.
 */
class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Kho hàng';

    protected static ?string $modelLabel = 'Kho';

    protected static ?string $pluralModelLabel = 'Kho hàng';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::QUAN_LY_KHO;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin kho')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã kho')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->placeholder('VD: WH-001'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên kho')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('VD: Kho Hà Nội'),

                    Forms\Components\Select::make('type')
                        ->label('Loại kho')
                        ->required()
                        ->options([
                            'OWN' => 'Kho sở hữu',
                            'THIRD_PARTY' => 'Kho bên thứ ba',
                            'VIRTUAL' => 'Kho ảo',
                        ])
                        ->default('OWN')
                        ->native(false),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options([
                            'ACTIVE' => 'Đang hoạt động',
                            'INACTIVE' => 'Ngừng hoạt động',
                        ])
                        ->default('ACTIVE')
                        ->native(false),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Kho mặc định')
                        ->helperText('Chỉ định 1 kho mặc định cho hệ thống.')
                        ->inline(false),

                    Forms\Components\Textarea::make('address')
                        ->label('Địa chỉ')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã kho')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên kho')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'OWN' => 'Sở hữu',
                        'THIRD_PARTY' => 'Bên thứ ba',
                        'VIRTUAL' => 'Ảo',
                        default => $state,
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'Hoạt động',
                        'INACTIVE' => 'Ngừng',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ACTIVE' ? 'success' : 'danger'),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Mặc định')
                    ->boolean(),

                Tables\Columns\TextColumn::make('inventories_count')
                    ->label('Số SP tồn')
                    ->counts('inventories')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'ACTIVE' => 'Hoạt động',
                        'INACTIVE' => 'Ngừng',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại')
                    ->options([
                        'OWN' => 'Sở hữu',
                        'THIRD_PARTY' => 'Bên thứ ba',
                        'VIRTUAL' => 'Ảo',
                    ])
                    ->native(false),
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
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}

