<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PlatformTxStatus;
use App\Filament\Resources\PlatformTransactionResource\Pages;
use App\Models\PlatformTransaction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class PlatformTransactionResource extends Resource
{
    protected static ?string $model = PlatformTransaction::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'GD Sàn TMĐT';

    protected static ?string $modelLabel = 'Giao dịch sàn';

    protected static ?string $pluralModelLabel = 'Giao dịch sàn TMĐT';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 32;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin sàn')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('platform_id')
                        ->label('Mã sàn')
                        ->required()
                        ->placeholder('SHOPEE / LAZADA / TIKI / TIKTOKSHOP')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('platform_order_id')
                        ->label('Mã đơn sàn')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\Select::make('sales_order_id')
                        ->label('Đơn bán nội bộ')
                        ->relationship('salesOrder', 'order_number')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('clearing_bank_account_id')
                        ->label('TK trung gian clearing')
                        ->relationship('clearingBankAccount', 'name', fn ($q) => $q->where('account_type', 'PLATFORM_CLEARING'))
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->options(collect(PlatformTxStatus::cases())
                            ->mapWithKeys(fn (PlatformTxStatus $s) => [$s->value => $s->label()])
                            ->toArray())
                        ->default(PlatformTxStatus::PENDING->value)
                        ->required()
                        ->native(false),

                    Forms\Components\DatePicker::make('settlement_date')
                        ->label('Ngày quyết toán')
                        ->native(false),
                ]),

            Forms\Components\Section::make('Số tiền')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('gross_amount')
                        ->label('Gross (KH trả)')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                            $gross = (float) ($get('gross_amount') ?? 0);
                            $fee = (float) ($get('platform_fee') ?? 0);
                            $set('net_amount', (string) round($gross - $fee, 2));
                        }),

                    Forms\Components\TextInput::make('platform_fee')
                        ->label('Phí sàn')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                            $gross = (float) ($get('gross_amount') ?? 0);
                            $fee = (float) ($get('platform_fee') ?? 0);
                            $set('net_amount', (string) round($gross - $fee, 2));
                        }),

                    Forms\Components\TextInput::make('net_amount')
                        ->label('Net (= gross - fee)')
                        ->required()
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\TextInput::make('actual_received')
                        ->label('Thực nhận')
                        ->numeric()
                        ->prefix('₫')
                        ->helperText('Set khi SETTLED'),
                ]),

            Forms\Components\Section::make('Mapping')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('matched_payment_id')
                        ->label('Payment đã match')
                        ->relationship('matchedPayment', 'payment_number')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('matched_bank_transaction_id')
                        ->label('Sao kê NH')
                        ->relationship('matchedBankTransaction', 'reference')
                        ->searchable()
                        ->preload()
                        ->native(false),
                ]),

            Forms\Components\Section::make('Ghi chú')
                ->collapsed()
                ->schema([
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
                Tables\Columns\TextColumn::make('platform_id')
                    ->label('Sàn')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('platform_order_id')
                    ->label('Mã đơn sàn')
                    ->searchable()
                    ->copyable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('salesOrder.order_number')
                    ->label('SO nội bộ')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('platform_fee')
                    ->label('Phí')
                    ->money('VND')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Net')
                    ->money('VND')
                    ->color('success')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('actual_received')
                    ->label('Thực nhận')
                    ->money('VND')
                    ->placeholder('—')
                    ->color(fn ($state): string => $state !== null ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('settlement_date')
                    ->label('Ngày quyết toán')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (PlatformTxStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (PlatformTxStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('clearingBankAccount.name')
                    ->label('TK Clearing')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform_id')
                    ->label('Sàn')
                    ->options([
                        'SHOPEE' => 'Shopee',
                        'LAZADA' => 'Lazada',
                        'TIKI' => 'Tiki',
                        'TIKTOKSHOP' => 'TikTok Shop',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(PlatformTxStatus::cases())
                        ->mapWithKeys(fn (PlatformTxStatus $s) => [$s->value => $s->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\Filter::make('settlement_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('to')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('settlement_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('settlement_date', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\DeleteAction::make()->label('Xóa'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformTransactions::route('/'),
            'create' => Pages\CreatePlatformTransaction::route('/create'),
            'edit' => Pages\EditPlatformTransaction::route('/{record}/edit'),
        ];
    }
}