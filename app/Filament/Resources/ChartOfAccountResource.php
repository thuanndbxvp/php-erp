<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\AccountType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\ChartOfAccountResource\Pages;
use App\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = ChartOfAccount::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Hệ thống TK';

    protected static ?string $modelLabel = 'Tài khoản kế toán';

    protected static ?string $pluralModelLabel = 'Hệ thống tài khoản kế toán';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin tài khoản')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã tài khoản')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->placeholder('VD: 1111, 131, 5111'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên tài khoản')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('type')
                        ->label('Loại')
                        ->required()
                        ->options(collect(AccountType::cases())
                            ->mapWithKeys(fn (AccountType $t) => [$t->value => $t->label()])
                            ->toArray())
                        ->native(false),

                    Forms\Components\Select::make('parent_id')
                        ->label('Tài khoản cha')
                        ->relationship('parent', 'code')
                        ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $r) => "{$r->code} - {$r->name}")
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('currency')
                        ->label('Tiền tệ')
                        ->required()
                        ->options(['VND' => 'VND', 'USD' => 'USD'])
                        ->default('VND')
                        ->native(false),
                ]),

            Forms\Components\Section::make('Cờ')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_detail')
                        ->label('TK chi tiết')
                        ->helperText('True = ghi sổ, False = tổng hợp')
                        ->default(true)
                        ->inline(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang sử dụng')
                        ->default(true)
                        ->inline(false),

                    Forms\Components\Toggle::make('show_in_reports')
                        ->label('Hiển thị trên BC')
                        ->default(true)
                        ->inline(false),
                ]),

            Forms\Components\Section::make('Mô tả')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Mô tả')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã TK')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->formatStateUsing(fn (AccountType $state): string => $state->label())
                    ->badge()
                    ->color(fn (AccountType $state): string => match ($state) {
                        AccountType::ASSET => 'info',
                        AccountType::LIABILITY => 'warning',
                        AccountType::EQUITY => 'primary',
                        AccountType::REVENUE => 'success',
                        AccountType::EXPENSE => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('parent.code')
                    ->label('Cha')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Số dư')
                    ->getStateUsing(function (ChartOfAccount $record): string {
                        try {
                            return number_format($record->balance(), 0, ',', '.') . ' ₫';
                        } catch (\Throwable) {
                            return '—';
                        }
                    })
                    ->alignEnd()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_detail')
                    ->label('Chi tiết')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại')
                    ->options(collect(AccountType::cases())
                        ->mapWithKeys(fn (AccountType $t) => [$t->value => $t->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Đang sử dụng'),
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
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChartOfAccounts::route('/'),
            'create' => Pages\CreateChartOfAccount::route('/create'),
            'edit' => Pages\EditChartOfAccount::route('/{record}/edit'),
        ];
    }
}