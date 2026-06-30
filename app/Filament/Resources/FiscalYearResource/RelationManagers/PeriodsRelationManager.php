<?php

declare(strict_types=1);

namespace App\Filament\Resources\FiscalYearResource\RelationManagers;

use App\Models\AccountingPeriod;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class PeriodsRelationManager extends RelationManager
{
    protected static string $relationship = 'periods';

    protected static ?string $title = 'Các kỳ kế toán';

    protected static ?string $pluralModelLabel = 'Kỳ kế toán';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('period_number')
                ->label('Số thứ tự')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(13),

            Forms\Components\TextInput::make('name')
                ->label('Tên')
                ->required()
                ->maxLength(100),

            Forms\Components\DatePicker::make('start_date')
                ->label('Bắt đầu')
                ->required()
                ->native(false),

            Forms\Components\DatePicker::make('end_date')
                ->label('Kết thúc')
                ->required()
                ->native(false),

            Forms\Components\Select::make('status')
                ->label('Trạng thái')
                ->required()
                ->options(['OPEN' => 'Đang mở', 'CLOSED' => 'Đã đóng', 'LOCKED' => 'Khóa'])
                ->default('OPEN')
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('period_number')
                    ->label('Kỳ')
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Bắt đầu')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Kết thúc')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'OPEN' => '🟢 Mở',
                        'CLOSED' => '🟡 Đóng',
                        'LOCKED' => '🔒 Khóa',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'OPEN' => 'success',
                        'CLOSED' => 'warning',
                        'LOCKED' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('journalEntries_count')
                    ->label('Số BT')
                    ->counts('journalEntries')
                    ->alignCenter(),
            ])
            ->headerActions([
                Actions\CreateAction::make()->label('Tạo kỳ'),
            ])
            ->actions([
                Actions\Action::make('close')
                    ->label('Đóng kỳ')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AccountingPeriod $record): bool => $record->status === 'OPEN')
                    ->action(function (AccountingPeriod $record) {
                        $record->status = 'CLOSED';
                        $record->closed_by = auth()->id();
                        $record->closed_at = now();
                        $record->save();
                        Notification::make()->title('Đã đóng kỳ')->success()->send();
                    }),

                Actions\Action::make('reopen')
                    ->label('Mở lại')
                    ->icon('heroicon-o-lock-open')
                    ->color('info')
                    ->visible(fn (AccountingPeriod $record): bool => $record->status === 'CLOSED')
                    ->action(function (AccountingPeriod $record) {
                        $record->status = 'OPEN';
                        $record->closed_by = null;
                        $record->closed_at = null;
                        $record->save();
                        Notification::make()->title('Đã mở lại')->success()->send();
                    }),

                Actions\EditAction::make()->label('Sửa'),
                Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (AccountingPeriod $record): bool => $record->journalEntries()->count() === 0),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('period_number');
    }
}