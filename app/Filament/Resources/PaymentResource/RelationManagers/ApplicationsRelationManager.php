<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\RelationManagers;

use App\Models\PaymentApplication;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    protected static ?string $title = 'Áp dụng cho Invoice';

    protected static ?string $modelLabel = 'Áp dụng';

    protected static ?string $pluralModelLabel = 'Các lần áp dụng';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('invoice_label')
                ->label('Hóa đơn')
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('amount_applied')
                ->label('Số tiền')
                ->disabled()
                ->dehydrated(false)
                ->numeric()
                ->prefix('₫'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_label')
                    ->label('Hóa đơn')
                    ->getStateUsing(function (PaymentApplication $record): string {
                        if ($record->invoiceOut) {
                            return "📤 {$record->invoiceOut->invoice_number} (AR)";
                        }
                        if ($record->invoiceIn) {
                            return "📥 {$record->invoiceIn->invoice_number} (AP)";
                        }
                        return '—';
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('amount_applied')
                    ->label('Số tiền')
                    ->money('VND')
                    ->color('success')
                    ->weight('bold')
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('applied_at')
                    ->label('Áp lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Ghi chú')
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
                    ->label('Xem HĐ')
                    ->url(function (PaymentApplication $record): ?string {
                        if ($record->invoiceOut) {
                            return route('filament.admin.resources.invoice-outs.view', $record->invoiceOut);
                        }
                        if ($record->invoiceIn) {
                            return route('filament.admin.resources.invoice-ins.view', $record->invoiceIn);
                        }
                        return null;
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('applied_at', 'desc');
    }
}