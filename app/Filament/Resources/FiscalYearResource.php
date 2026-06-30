<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\FiscalYearResource\Pages;
use App\Filament\Resources\FiscalYearResource\RelationManagers;
use App\Models\FiscalYear;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class FiscalYearResource extends Resource
{
    protected static ?string $model = FiscalYear::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Năm tài chính';

    protected static ?string $modelLabel = 'Năm tài chính';

    protected static ?string $pluralModelLabel = 'Năm tài chính';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 52;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Năm tài chính')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('year')
                        ->label('Năm')
                        ->required()
                        ->numeric()
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->unique(ignoreRecord: true),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('Ngày bắt đầu')
                        ->required()
                        ->native(false),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('Ngày kết thúc')
                        ->required()
                        ->native(false),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options([
                            'OPEN' => 'Đang mở',
                            'CLOSED' => 'Đã đóng',
                            'LOCKED' => 'Khóa vĩnh viễn',
                        ])
                        ->default('OPEN')
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
                Tables\Columns\TextColumn::make('year')
                    ->label('Năm')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Bắt đầu')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Kết thúc')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('periods_count')
                    ->label('Số kỳ')
                    ->counts('periods')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'OPEN' => '🟢 Đang mở',
                        'CLOSED' => '🟡 Đã đóng',
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

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Người tạo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\Action::make('generatePeriods')
                    ->label('Tạo 12 kỳ')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (FiscalYear $record): bool => $record->status === 'OPEN')
                    ->action(function (FiscalYear $record) {
                        $count = 0;
                        for ($m = 1; $m <= 12; $m++) {
                            $start = sprintf('%d-%02d-01', $record->year, $m);
                            $end = date('Y-m-t', strtotime($start));
                            $name = sprintf('T%02d/%d', $m, $record->year);

                            $created = \App\Models\AccountingPeriod::firstOrCreate(
                                ['fiscal_year_id' => $record->id, 'period_number' => $m],
                                ['name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => 'OPEN'],
                            );
                            if ($created->wasRecentlyCreated) {
                                $count++;
                            }
                        }
                        Notification::make()->title("Đã tạo {$count} kỳ mới")->success()->send();
                    }),

                Tables\Actions\Action::make('close')
                    ->label('Đóng năm')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FiscalYear $record): bool => $record->status === 'OPEN')
                    ->action(function (FiscalYear $record) {
                        $record->status = 'CLOSED';
                        $record->save();
                        // Đóng luôn các kỳ
                        $record->periods()->where('status', 'OPEN')->update(['status' => 'CLOSED']);
                        Notification::make()->title('Đã đóng năm tài chính')->success()->send();
                    }),

                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (FiscalYear $record): bool => $record->periods()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('year', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PeriodsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalYears::route('/'),
            'create' => Pages\CreateFiscalYear::route('/create'),
            'edit' => Pages\EditFiscalYear::route('/{record}/edit'),
        ];
    }
}