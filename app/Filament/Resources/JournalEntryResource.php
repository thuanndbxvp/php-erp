<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\JournalStatus;
use App\Enums\JournalType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\JournalEntryResource\Pages;
use App\Filament\Resources\JournalEntryResource\RelationManagers;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Bút toán';

    protected static ?string $modelLabel = 'Bút toán kế toán';

    protected static ?string $pluralModelLabel = 'Bút toán kế toán';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 51;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin chung')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('journal_number')
                        ->label('Số bút toán')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\DatePicker::make('entry_date')
                        ->label('Ngày')
                        ->required()
                        ->native(false),

                    Forms\Components\Select::make('accounting_period_id')
                        ->label('Kỳ kế toán')
                        ->relationship('accountingPeriod', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('type')
                        ->label('Loại')
                        ->required()
                        ->options(collect(JournalType::cases())
                            ->mapWithKeys(fn (JournalType $t) => [$t->value => $t->label()])
                            ->toArray())
                        ->native(false),

                    Forms\Components\TextInput::make('description')
                        ->label('Mô tả')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),
                ]),

            Forms\Components\Section::make('Dòng bút toán')
                ->description('Mỗi bút toán phải cân bằng: Tổng Nợ = Tổng Có')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('account_id')
                                ->label('Tài khoản')
                                ->required()
                                ->options(fn () => ChartOfAccount::query()
                                    ->where('is_active', true)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"])
                                    ->toArray())
                                ->searchable()
                                ->columnSpan(2),

                            Forms\Components\Select::make('dc')
                                ->label('Loại')
                                ->required()
                                ->options([
                                    'DEBIT' => 'Nợ',
                                    'CREDIT' => 'Có',
                                ])
                                ->native(false)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('amount')
                                ->label('Số tiền')
                                ->required()
                                ->numeric()
                                ->prefix('₫')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('line_description')
                                ->label('Ghi chú')
                                ->maxLength(255)
                                ->columnSpan(3),
                        ])
                        ->columns(8)
                        ->addActionLabel('+ Thêm dòng')
                        ->minItems(2)
                        ->collapsible(),
                ]),

            Forms\Components\Section::make('Khác')
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
                Tables\Columns\TextColumn::make('journal_number')
                    ->label('Số BT')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('entry_date')
                    ->label('Ngày')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('accountingPeriod.name')
                    ->label('Kỳ')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn (JournalType $state): string => $state->label())
                    ->badge(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Mô tả')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('total_debit')
                    ->label('Tổng Nợ')
                    ->money('VND')
                    ->color('success')
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('total_credit')
                    ->label('Tổng Có')
                    ->money('VND')
                    ->color('danger')
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (JournalStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (JournalStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('ref_type')
                    ->label('Nguồn')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'INVOICE_OUT' => '📤 HĐ bán',
                        'INVOICE_IN' => '📥 HĐ mua',
                        'PAYMENT' => '💵 Payment',
                        'BANK_TX' => '🏦 Sao kê',
                        'PLATFORM_TX' => '🛍 Sàn',
                        'MANUAL' => '✍️ Thủ công',
                        default => $state ?? '—',
                    })
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(JournalStatus::cases())
                        ->mapWithKeys(fn (JournalStatus $s) => [$s->value => $s->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại')
                    ->options(collect(JournalType::cases())
                        ->mapWithKeys(fn (JournalType $t) => [$t->value => $t->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\SelectFilter::make('accounting_period_id')
                    ->label('Kỳ kế toán')
                    ->relationship('accountingPeriod', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\Filter::make('entry_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ ngày'),
                        Forms\Components\DatePicker::make('to')->label('Đến ngày'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\Action::make('reverse')
                    ->label('Đảo ngược')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->label('Lý do')->required()])
                    ->visible(fn (JournalEntry $record): bool => $record->isPosted() && ! $record->reversed_by_id)
                    ->action(function (JournalEntry $record, array $data) {
                        try {
                            app(\App\Services\JournalService::class)->reverse($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Đã đảo ngược')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (JournalEntry $record): bool => $record->status === JournalStatus::DRAFT),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (JournalEntry $record): bool => $record->status === JournalStatus::DRAFT),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('entry_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LedgerEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }
}