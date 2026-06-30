<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceInResource\RelationManagers;

use App\Models\PaymentApplication;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class PaymentApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentApplications';

    protected static ?string $title = 'Lịch sử thanh toán';

    protected static ?string $modelLabel = 'Lần thanh toán';

    protected static ?string $pluralModelLabel = 'Các lần thanh toán';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('payment.payment_number')
                ->label('Số phiếu TT')
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('amount_applied')
                ->label('Số tiền')
                ->disabled()
                ->dehydrated(false)
                ->numeric()
                ->prefix('₫'),

            Forms\Components\DateTimePicker::make('applied_at')
                ->label('Áp lúc')
                ->disabled()
                ->dehydrated(false)
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('payment.payment_number')
                    ->label('Số phiếu TT')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('payment.payment_method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'CASH' => 'Tiền mặt',
                        'BANK_TRANSFER' => 'CK NH',
                        'QR_PAY' => 'QR',
                        'E_WALLET' => 'Ví',
                        'CARD' => 'Thẻ',
                        'PLATFORM' => 'Sàn',
                        default => $state,
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('amount_applied')
                    ->label('Số tiền')
                    ->money('VND')
                    ->color('danger')
                    ->weight('bold')
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('applied_at')
                    ->label('Áp lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment.reference')
                    ->label('Mã tham chiếu')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('unapply')
                    ->label('Gỡ')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (PaymentApplication $record) {
                        try {
                            app(\App\Services\PaymentService::class)->unapply($record);
                            Notification::make()->title('Đã gỡ')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\ViewAction::make()
                    ->label('Xem Payment')
                    ->url(fn (PaymentApplication $record) => route('filament.admin.resources.payments.view', $record->payment_id)),
            ])
            ->bulkActions([])
            ->defaultSort('applied_at', 'desc');
    }
}