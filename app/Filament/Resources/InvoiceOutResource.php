<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\InvoiceOutResource\Pages;
use App\Filament\Resources\InvoiceOutResource\RelationManagers;
use App\Models\InvoiceOut;
use App\Models\SalesOrder;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class InvoiceOutResource extends Resource
{
    protected static ?string $model = InvoiceOut::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?string $navigationLabel = 'Hóa đơn bán';

    protected static ?string $modelLabel = 'Hóa đơn bán';

    protected static ?string $pluralModelLabel = 'Hóa đơn bán ra';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin hóa đơn')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('invoice_number')
                        ->label('Số hóa đơn')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Tự sinh khi lưu'),

                    Forms\Components\Select::make('sales_order_id')
                        ->label('Đơn bán')
                        ->relationship('salesOrder', 'order_number')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }
                            $so = SalesOrder::find($state);
                            if ($so) {
                                $set('customer_id', $so->customer_id);
                                $set('subtotal', (string) $so->subtotal);
                                $set('discount_amount', (string) $so->discount_amount);
                                $set('tax_amount', (string) $so->tax_amount);
                                $set('total', (string) $so->total_amount);
                                $set('currency', $so->currency);
                                $set('exchange_rate', (string) $so->exchange_rate);
                            }
                        }),

                    Forms\Components\Select::make('customer_id')
                        ->label('Khách hàng')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->native(false),

                    Forms\Components\DatePicker::make('invoice_date')
                        ->label('Ngày hóa đơn')
                        ->required()
                        ->default(now())
                        ->native(false),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Hạn thanh toán')
                        ->required()
                        ->default(now()->addDays(30))
                        ->native(false),

                    Forms\Components\Select::make('invoice_type')
                        ->label('Loại HĐ')
                        ->required()
                        ->options(collect(InvoiceType::cases())
                            ->mapWithKeys(fn (InvoiceType $t) => [$t->value => $t->label()])
                            ->toArray())
                        ->default(InvoiceType::DOMESTIC->value)
                        ->native(false),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->options(collect(InvoiceStatus::cases())
                            ->mapWithKeys(fn (InvoiceStatus $s) => [$s->value => $s->label()])
                            ->toArray())
                        ->default(InvoiceStatus::DRAFT->value)
                        ->disabled()
                        ->dehydrated()
                        ->native(false),
                ]),

            Forms\Components\Section::make('Số tiền')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->label('Subtotal')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('discount_amount')
                        ->label('Chiết khấu')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('tax_amount')
                        ->label('VAT')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('total')
                        ->label('Tổng cuối')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('paid_amount')
                        ->label('Đã thanh toán')
                        ->disabled()
                        ->dehydrated()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('balance_due')
                        ->label('Còn phải thu')
                        ->disabled()
                        ->dehydrated()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\TextInput::make('tax_rate')
                        ->label('VAT %')
                        ->numeric()
                        ->default(10)
                        ->suffix('%'),

                    Forms\Components\TextInput::make('currency')
                        ->label('Tiền tệ')
                        ->default('VND')
                        ->required()
                        ->maxLength(3),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->label('Tỷ giá')
                        ->numeric()
                        ->default(1),
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
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Số HĐ')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Ngày HĐ')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Hạn TT')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($state): string => $state && $state->isPast() ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('salesOrder.order_number')
                    ->label('Đơn bán')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Tổng HĐ')
                    ->money('VND')
                    ->sortable()
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Đã trả')
                    ->money('VND')
                    ->color('success'),

                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Còn nợ')
                    ->money('VND')
                    ->color('danger')
                    ->weight('bold')
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('invoice_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (InvoiceType $state): string => $state->label())
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(InvoiceStatus::cases())
                        ->mapWithKeys(fn (InvoiceStatus $s) => [$s->value => $s->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Khách hàng')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('to')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('due_date', '<=', $d));
                    }),

                Tables\Filters\Filter::make('outstanding_only')
                    ->label('Còn nợ')
                    ->toggle()
                    ->query(fn ($query) => $query->where('balance_due', '>', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (InvoiceOut $record): bool => $record->status === InvoiceStatus::DRAFT),
                Tables\Actions\Action::make('issue')
                    ->label('Phát hành')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (InvoiceOut $record): bool => $record->status === InvoiceStatus::DRAFT)
                    ->action(function (InvoiceOut $record) {
                        try {
                            app(\App\Services\InvoiceOutService::class)->issue($record, auth()->user());
                            Notification::make()->title('Đã phát hành hóa đơn')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Hủy')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Lý do')->required(),
                    ])
                    ->visible(fn (InvoiceOut $record): bool => ! in_array($record->status, [InvoiceStatus::PAID, InvoiceStatus::CANCELLED, InvoiceStatus::CREDITED], true))
                    ->action(function (InvoiceOut $record, array $data) {
                        try {
                            app(\App\Services\InvoiceOutService::class)->cancel($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Đã hủy')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (InvoiceOut $record): bool => $record->status === InvoiceStatus::DRAFT),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentApplicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceOuts::route('/'),
            'create' => Pages\CreateInvoiceOut::route('/create'),
            'edit' => Pages\EditInvoiceOut::route('/{record}/edit'),
        ];
    }
}