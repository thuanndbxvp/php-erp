<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Nhà cung cấp';

    protected static ?string $modelLabel = 'Nhà cung cấp';

    protected static ?string $pluralModelLabel = 'Nhà cung cấp';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::DOI_TAC;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin cơ bản')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã NCC')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->placeholder('VD: NCC-001'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên nhà cung cấp')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('supplier_type')
                        ->label('Loại')
                        ->required()
                        ->options([
                            'INDIVIDUAL' => 'Cá nhân',
                            'COMPANY' => 'Doanh nghiệp',
                        ])
                        ->default('COMPANY')
                        ->native(false),

                    Forms\Components\TextInput::make('tax_code')
                        ->label('Mã số thuế')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('phone')
                        ->label('Điện thoại')
                        ->tel(),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email(),

                    Forms\Components\TextInput::make('website')
                        ->label('Website')
                        ->url()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Địa chỉ')
                ->columns(2)
                ->schema([
                    Forms\Components\Textarea::make('billing_address')
                        ->label('Địa chỉ hóa đơn')
                        ->rows(2),

                    Forms\Components\Textarea::make('shipping_address')
                        ->label('Địa chỉ giao hàng')
                        ->rows(2),
                ]),

            Forms\Components\Section::make('Điều khoản thương mại')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('payment_term_days')
                        ->label('Thời hạn TT (ngày)')
                        ->numeric()
                        ->default(30),

                    Forms\Components\TextInput::make('credit_limit')
                        ->label('Hạn mức tín dụng')
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('lead_time_days')
                        ->label('Lead time (ngày)')
                        ->numeric()
                        ->default(7),

                    Forms\Components\TextInput::make('min_order_value')
                        ->label('Giá trị đơn tối thiểu')
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),
                ]),

            Forms\Components\Section::make('Trạng thái')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options([
                            'ACTIVE' => 'Đang hợp tác',
                            'PENDING' => 'Chờ duyệt',
                            'INACTIVE' => 'Ngừng hợp tác',
                            'BLOCKED' => 'Bị khóa',
                        ])
                        ->default('ACTIVE')
                        ->native(false),

                    Forms\Components\TagsInput::make('tags')
                        ->label('Nhãn')
                        ->placeholder('Nhấn Enter để thêm nhãn'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Ghi chú')
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
                    ->label('Mã NCC')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên nhà cung cấp')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('supplier_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'INDIVIDUAL' => 'Cá nhân',
                        'COMPANY' => 'Doanh nghiệp',
                        default => $state,
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('payment_term_days')
                    ->label('TT (ngày)')
                    ->numeric()
                    ->suffix(' ngày')
                    ->sortable(),

                Tables\Columns\TextColumn::make('credit_limit')
                    ->label('Hạn mức')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'Hoạt động',
                        'PENDING' => 'Chờ duyệt',
                        'INACTIVE' => 'Ngừng',
                        'BLOCKED' => 'Khóa',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'success',
                        'PENDING' => 'warning',
                        'INACTIVE' => 'gray',
                        'BLOCKED' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'ACTIVE' => 'Hoạt động',
                        'PENDING' => 'Chờ duyệt',
                        'INACTIVE' => 'Ngừng',
                        'BLOCKED' => 'Khóa',
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}

