<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BankAccountType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Tài khoản';

    protected static ?string $modelLabel = 'Tài khoản NH/Ví';

    protected static ?string $pluralModelLabel = 'Tài khoản ngân hàng & Ví';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin chung')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã tài khoản')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->placeholder('VD: NH-VCB-001, WALLET-MOMO'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên hiển thị')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('account_type')
                        ->label('Loại tài khoản')
                        ->required()
                        ->options(collect(BankAccountType::cases())
                            ->mapWithKeys(fn (BankAccountType $t) => [$t->value => $t->label()])
                            ->toArray())
                        ->default(BankAccountType::CHECKING->value)
                        ->native(false)
                        ->live(),

                    Forms\Components\Select::make('currency')
                        ->label('Tiền tệ')
                        ->required()
                        ->options([
                            'VND' => 'VND',
                            'USD' => 'USD',
                            'EUR' => 'EUR',
                            'JPY' => 'JPY',
                        ])
                        ->default('VND')
                        ->native(false),

                    Forms\Components\TextInput::make('account_number')
                        ->label('Số tài khoản')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('bank_name')
                        ->label('Tên ngân hàng / Ví')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('bank_branch')
                        ->label('Chi nhánh')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('platform_id')
                        ->label('Platform ID (cho PLATFORM_CLEARING)')
                        ->placeholder('SHOPEE / LAZADA / TIKI')
                        ->visible(fn (Forms\Get $get): bool => $get('account_type') === BankAccountType::PLATFORM_CLEARING->value)
                        ->maxLength(50),
                ]),

            Forms\Components\Section::make('Số dư & Trạng thái')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('opening_balance')
                        ->label('Số dư đầu kỳ')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\DatePicker::make('opening_date')
                        ->label('Ngày bắt đầu')
                        ->required()
                        ->default(now())
                        ->native(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang sử dụng')
                        ->default(true)
                        ->inline(false),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Mặc định')
                        ->helperText('Chỉ chọn 1 tài khoản mặc định')
                        ->default(false)
                        ->inline(false),

                    Forms\Components\Select::make('created_by')
                        ->label('Người tạo')
                        ->relationship('creator', 'name')
                        ->default(auth()->id())
                        ->disabled()
                        ->dehydrated()
                        ->native(false),
                ]),

            Forms\Components\Section::make('Ghi chú')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Ghi chú nội bộ')
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
                    ->label('Mã')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('account_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (BankAccountType $state): string => $state->label())
                    ->badge()
                    ->color(fn (BankAccountType $state): string => match ($state) {
                        BankAccountType::CHECKING => 'info',
                        BankAccountType::SAVINGS => 'success',
                        BankAccountType::PLATFORM_CLEARING => 'warning',
                        BankAccountType::WALLET => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('NH / Ví')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Số TK')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('opening_balance')
                    ->label('Số dư đầu')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('currentBalance')
                    ->label('Số dư hiện tại')
                    ->getStateUsing(fn (BankAccount $record): string => number_format($record->current_balance, 0, ',', '.') . ' ₫')
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Tiền tệ')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_type')
                    ->label('Loại')
                    ->options(collect(BankAccountType::cases())
                        ->mapWithKeys(fn (BankAccountType $t) => [$t->value => $t->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Đang sử dụng'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
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
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}