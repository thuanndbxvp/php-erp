<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\ReconStatus;
use App\Enums\TxType;
use App\Filament\Resources\BankTransactionResource\Pages;
use App\Models\BankTransaction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Sao kê NH';

    protected static ?string $modelLabel = 'Giao dịch ngân hàng';

    protected static ?string $pluralModelLabel = 'Sao kê ngân hàng';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Giao dịch')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('bank_account_id')
                        ->label('Tài khoản')
                        ->relationship('bankAccount', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),

                    Forms\Components\DatePicker::make('transaction_date')
                        ->label('Ngày GD')
                        ->required()
                        ->native(false),

                    Forms\Components\DatePicker::make('post_date')
                        ->label('Ngày hạch toán')
                        ->native(false),

                    Forms\Components\Select::make('type')
                        ->label('Loại')
                        ->required()
                        ->options(collect(TxType::cases())
                            ->mapWithKeys(fn (TxType $t) => [$t->value => $t->label()])
                            ->toArray())
                        ->native(false),

                    Forms\Components\TextInput::make('amount')
                        ->label('Số tiền (+/-)')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->helperText('Dương = tiền vào, Âm = tiền ra'),

                    Forms\Components\TextInput::make('balance')
                        ->label('Số dư sau GD')
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\TextInput::make('reference')
                        ->label('Mã tham chiếu NH')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('counterparty_name')
                        ->label('Đối phương')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('counterparty_account')
                        ->label('Số TK đối phương')
                        ->maxLength(50),

                    Forms\Components\Textarea::make('description')
                        ->label('Nội dung')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('recon_status')
                        ->label('Đối soát')
                        ->options(collect(ReconStatus::cases())
                            ->mapWithKeys(fn (ReconStatus $r) => [$r->value => $r->label()])
                            ->toArray())
                        ->default(ReconStatus::UNRECONCILED->value)
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Ngày GD')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Tài khoản')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn (TxType $state): string => $state->label())
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->color(fn ($state): string => ((float) $state) >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Số dư')
                    ->money('VND')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Mã tham chiếu')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('counterparty_name')
                    ->label('Đối phương')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Nội dung')
                    ->limit(40)
                    ->tooltip(fn ($state): ?string => $state)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('recon_status')
                    ->label('Đối soát')
                    ->formatStateUsing(fn (ReconStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (ReconStatus $state): string => match ($state) {
                        ReconStatus::UNRECONCILED => 'warning',
                        ReconStatus::MATCHED => 'success',
                        ReconStatus::DISPUTED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('matchedPayment.payment_number')
                    ->label('Payment match')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('import_batch_id')
                    ->label('Batch')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->label('Tài khoản')
                    ->relationship('bankAccount', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('recon_status')
                    ->label('Đối soát')
                    ->options(collect(ReconStatus::cases())
                        ->mapWithKeys(fn (ReconStatus $r) => [$r->value => $r->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\Filter::make('transaction_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('to')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('transaction_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('transaction_date', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\Action::make('reconcile')
                    ->label('Đối soát')
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->visible(fn (BankTransaction $record): bool => $record->recon_status !== ReconStatus::MATCHED)
                    ->form([
                        Forms\Components\Select::make('payment_id')
                            ->label('Payment')
                            ->options(function () {
                                return \App\Models\Payment::query()
                                    ->whereIn('status', [\App\Enums\PaymentStatus::PENDING->value, \App\Enums\PaymentStatus::APPLIED->value])
                                    ->orderByDesc('payment_date')
                                    ->limit(200)
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => "{$p->payment_number} - {$p->party_type} - " . number_format((float) $p->amount, 0, ',', '.') . '₫',
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (BankTransaction $record, array $data) {
                        try {
                            $payment = \App\Models\Payment::findOrFail($data['payment_id']);
                            app(\App\Services\BankTransactionService::class)->reconcileWithPayment($record, $payment, auth()->user());
                            Notification::make()->title('Đã đối soát')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('unmatch')
                    ->label('Gỡ match')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (BankTransaction $record): bool => $record->recon_status === ReconStatus::MATCHED)
                    ->action(function (BankTransaction $record) {
                        try {
                            app(\App\Services\BankTransactionService::class)->unmatch($record, auth()->user());
                            Notification::make()->title('Đã gỡ match')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (BankTransaction $record): bool => $record->recon_status === ReconStatus::UNRECONCILED),

                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (BankTransaction $record): bool => $record->recon_status === ReconStatus::UNRECONCILED),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankTransactions::route('/'),
            'create' => Pages\CreateBankTransaction::route('/create'),
            'edit' => Pages\EditBankTransaction::route('/{record}/edit'),
        ];
    }
}