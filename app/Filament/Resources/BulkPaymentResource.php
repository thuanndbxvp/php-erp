<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BulkPaymentStatus;
use App\Enums\NavigationGroup;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\BulkPaymentResource\Pages;
use App\Filament\Resources\BulkPaymentResource\RelationManagers;
use App\Models\BulkPayment;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class BulkPaymentResource extends Resource
{
    protected static ?string $model = BulkPayment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Gom thanh toán';

    protected static ?string $modelLabel = 'Phiếu gom';

    protected static ?string $pluralModelLabel = 'Phiếu gom thanh toán';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 31;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('bulk_number')
                        ->label('Số phiếu gom')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('party_type')
                        ->label('Loại')
                        ->required()
                        ->options([
                            PartyType::CUSTOMER->value => 'Thu (gộp HĐ bán)',
                            PartyType::SUPPLIER->value => 'Chi (gộp HĐ mua)',
                        ])
                        ->live()
                        ->native(false),

                    Forms\Components\Select::make('party_id')
                        ->label(fn (Forms\Get $get): string => $get('party_type') === PartyType::SUPPLIER->value ? 'NCC' : 'KH')
                        ->required()
                        ->options(function (Forms\Get $get): array {
                            if ($get('party_type') === PartyType::SUPPLIER->value) {
                                return \App\Models\Supplier::query()->pluck('name', 'id')->toArray();
                            }
                            return \App\Models\Customer::query()->pluck('name', 'id')->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\DatePicker::make('payment_date')
                        ->label('Ngày thanh toán')
                        ->required()
                        ->default(now())
                        ->native(false),

                    Forms\Components\TextInput::make('total_amount')
                        ->label('Tổng tiền')
                        ->required()
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\Select::make('payment_method')
                        ->label('Phương thức')
                        ->required()
                        ->options(collect(PaymentMethod::cases())
                            ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => $m->label()])
                            ->toArray())
                        ->default(PaymentMethod::BANK_TRANSFER->value)
                        ->native(false),

                    Forms\Components\Select::make('bank_account_id')
                        ->label('TK NH')
                        ->relationship('bankAccount', 'name', fn ($q) => $q->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\TextInput::make('reference')
                        ->label('Mã tham chiếu')
                        ->maxLength(100),

                    Forms\Components\Textarea::make('description')
                        ->label('Mô tả')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Danh sách HĐ')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('invoice_out_id')
                                ->label('HĐ bán')
                                ->options(fn (Forms\Get $get) => \App\Models\InvoiceOut::query()
                                    ->where('customer_id', $get('../../party_id'))
                                    ->where('balance_due', '>', 0)
                                    ->orderByDesc('invoice_date')
                                    ->limit(200)
                                    ->get()
                                    ->mapWithKeys(fn ($i) => [$i->id => "{$i->invoice_number} - Còn: " . number_format((float) $i->balance_due, 0, ',', '.')])
                                    ->toArray())
                                ->searchable()
                                ->visible(fn (Forms\Get $get) => ($get('../../party_type')) === PartyType::CUSTOMER->value)
                                ->columnSpan(3),

                            Forms\Components\Select::make('invoice_in_id')
                                ->label('HĐ mua')
                                ->options(fn (Forms\Get $get) => \App\Models\InvoiceIn::query()
                                    ->where('supplier_id', $get('../../party_id'))
                                    ->where('balance_due', '>', 0)
                                    ->orderByDesc('invoice_date')
                                    ->limit(200)
                                    ->get()
                                    ->mapWithKeys(fn ($i) => [$i->id => "{$i->invoice_number} - Còn: " . number_format((float) $i->balance_due, 0, ',', '.')])
                                    ->toArray())
                                ->searchable()
                                ->visible(fn (Forms\Get $get) => ($get('../../party_type')) === PartyType::SUPPLIER->value)
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('amount_applied')
                                ->label('Số tiền')
                                ->required()
                                ->numeric()
                                ->prefix('₫')
                                ->columnSpan(2),
                        ])
                        ->columns(5)
                        ->addActionLabel('+ Thêm hóa đơn')
                        ->minItems(1),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bulk_number')
                    ->label('Số phiếu')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Ngày')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('party_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (PartyType $state): string => $state === PartyType::CUSTOMER ? '💰 Thu' : '💸 Chi')
                    ->badge(),

                Tables\Columns\TextColumn::make('party_label')
                    ->label('Đối tượng')
                    ->getStateUsing(function (BulkPayment $record): string {
                        return $record->customer?->name ?? $record->supplier?->name ?? '—';
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label())
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (BulkPaymentStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (BulkPaymentStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('applications_count')
                    ->label('Số HĐ')
                    ->counts('applications')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(BulkPaymentStatus::cases())
                        ->mapWithKeys(fn (BulkPaymentStatus $s) => [$s->value => $s->label()])
                        ->toArray())
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\Action::make('process')
                    ->label('Xử lý')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (BulkPayment $record): bool => $record->status === BulkPaymentStatus::PENDING)
                    ->action(function (BulkPayment $record) {
                        try {
                            app(\App\Services\BulkPaymentService::class)->process($record, auth()->user());
                            Notification::make()->title('Đã xử lý - Payment đã được tạo')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Hủy')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->label('Lý do')->required()])
                    ->visible(fn (BulkPayment $record): bool => $record->status === BulkPaymentStatus::PENDING)
                    ->action(function (BulkPayment $record, array $data) {
                        try {
                            app(\App\Services\BulkPaymentService::class)->cancel($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Đã hủy')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (BulkPayment $record): bool => $record->status !== BulkPaymentStatus::COMPLETED),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ApplicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkPayments::route('/'),
            'create' => Pages\CreateBulkPayment::route('/create'),
            'edit' => Pages\EditBulkPayment::route('/{record}/edit'),
        ];
    }
}