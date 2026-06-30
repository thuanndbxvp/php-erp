<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Phiếu thanh toán';

    protected static ?string $modelLabel = 'Phiếu thanh toán';

    protected static ?string $pluralModelLabel = 'Phiếu thanh toán (thu/chi)';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        // Form đầy đủ cho Edit, còn Create dùng Wizard riêng
        return $schema->components([
            Forms\Components\Section::make('Thông tin')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('payment_number')
                        ->label('Số phiếu')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('party_type')
                        ->label('Loại')
                        ->disabled()
                        ->dehydrated()
                        ->options([
                            PartyType::CUSTOMER->value => 'Thu (AR)',
                            PartyType::SUPPLIER->value => 'Chi (AP)',
                        ])
                        ->native(false),

                    Forms\Components\Select::make('payment_method')
                        ->label('Phương thức')
                        ->disabled()
                        ->dehydrated()
                        ->options(collect(PaymentMethod::cases())
                            ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => $m->label()])
                            ->toArray())
                        ->native(false),

                    Forms\Components\DatePicker::make('payment_date')
                        ->label('Ngày TT')
                        ->disabled()
                        ->dehydrated()
                        ->native(false),

                    Forms\Components\TextInput::make('amount')
                        ->label('Tổng tiền')
                        ->disabled()
                        ->dehydrated()
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\TextInput::make('reference')
                        ->label('Mã tham chiếu')
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(100),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->disabled()
                        ->dehydrated()
                        ->options(collect(PaymentStatus::cases())
                            ->mapWithKeys(fn (PaymentStatus $s) => [$s->value => $s->label()])
                            ->toArray())
                        ->native(false),

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
                Tables\Columns\TextColumn::make('payment_number')
                    ->label('Số phiếu')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Ngày TT')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('party_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (PartyType $state): string => $state === PartyType::CUSTOMER ? '💰 Thu' : '💸 Chi')
                    ->badge()
                    ->color(fn (PartyType $state): string => $state === PartyType::CUSTOMER ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('party_label')
                    ->label('Đối tượng')
                    ->getStateUsing(function (Payment $record): string {
                        if ($record->customer) {
                            return $record->customer->name;
                        }
                        if ($record->supplier) {
                            return $record->supplier->name;
                        }
                        return '—';
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label())
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Tổng')
                    ->money('VND')
                    ->sortable()
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('applied_amount')
                    ->label('Đã áp dụng')
                    ->money('VND')
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Còn dư')
                    ->money('VND')
                    ->color('warning')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('TK NH')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Mã tham chiếu')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('party_type')
                    ->label('Loại')
                    ->options([
                        PartyType::CUSTOMER->value => 'Thu (khách hàng)',
                        PartyType::SUPPLIER->value => 'Chi (NCC)',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Phương thức')
                    ->options(collect(PaymentMethod::cases())
                        ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => $m->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(PaymentStatus::cases())
                        ->mapWithKeys(fn (PaymentStatus $s) => [$s->value => $s->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('to')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('payment_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('payment_date', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\Action::make('markFailed')
                    ->label('Đánh fail')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->label('Lý do')->required()])
                    ->visible(fn (Payment $record): bool => in_array($record->status, [PaymentStatus::PENDING, PaymentStatus::APPLIED], true))
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(\App\Services\PaymentService::class)->markFailed($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Đã đánh fail')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('refund')
                    ->label('Hoàn tiền')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->label('Lý do')->required()])
                    ->visible(fn (Payment $record): bool => $record->status !== PaymentStatus::REFUNDED && $record->status !== PaymentStatus::CANCELLED)
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(\App\Services\PaymentService::class)->refund($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Đã hoàn tiền')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (Payment $record): bool => bccomp((string) $record->applied_amount, '0', 2) === 0),
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}