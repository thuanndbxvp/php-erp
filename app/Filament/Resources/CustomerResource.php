<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use UnitEnum;
use BackedEnum;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Khách hàng';

    protected static ?string $modelLabel = 'Khách hàng';

    protected static ?string $pluralModelLabel = 'Khách hàng';

    protected static \UnitEnum|string|null $navigationGroup = \App\Enums\NavigationGroup::DOI_TAC;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin khách hàng')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã KH')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->placeholder('VD: KH-001'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên khách hàng')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('tax_code')
                        ->label('Mã số thuế')
                        ->maxLength(50),

                    Forms\Components\Select::make('type')
                        ->label('Loại khách')
                        ->required()
                        ->options([
                            'INDIVIDUAL' => 'Cá nhân',
                            'COMPANY' => 'Doanh nghiệp',
                        ])
                        ->default('INDIVIDUAL')
                        ->native(false),

                    Forms\Components\TextInput::make('contact_person')
                        ->label('Người liên hệ'),

                    Forms\Components\TextInput::make('phone')
                        ->label('Điện thoại')
                        ->tel(),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email(),

                    Forms\Components\Textarea::make('address')
                        ->label('Địa chỉ')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options([
                            'ACTIVE' => 'Đang hợp tác',
                            'INACTIVE' => 'Ngừng hợp tác',
                        ])
                        ->default('ACTIVE')
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã KH')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên khách hàng')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('type')
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

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'Hoạt động',
                        'INACTIVE' => 'Ngừng',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ACTIVE' ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại')
                    ->options([
                        'INDIVIDUAL' => 'Cá nhân',
                        'COMPANY' => 'Doanh nghiệp',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'ACTIVE' => 'Đang hợp tác',
                        'INACTIVE' => 'Ngừng',
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}

