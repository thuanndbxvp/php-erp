<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\OpexCategoryResource\Pages;
use App\Models\ChartOfAccount;
use App\Models\OpexCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class OpexCategoryResource extends Resource
{
    protected static ?string $model = OpexCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Danh mục OPEX';

    protected static ?string $modelLabel = 'Danh mục chi phí vận hành';

    protected static ?string $pluralModelLabel = 'Danh mục chi phí vận hành';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 55;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin danh mục')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã danh mục')
                        ->required()
                        ->maxLength(32)
                        ->unique(ignoreRecord: true)
                        ->placeholder('VD: OPEX-ELEC, OPEX-SAL'),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên danh mục')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('account_id')
                        ->label('TK kế toán ghi Nợ')
                        ->helperText('TK chi phí (6xx, 8xx) mặc định khi ghi nhận OPEX')
                        ->required()
                        ->options(
                            ChartOfAccount::query()
                                ->where('is_active', true)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (ChartOfAccount $a) => [$a->id => "{$a->code} - {$a->name}"])
                                ->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang sử dụng')
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
                    ->label('Mã')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên danh mục')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('account.code')
                    ->label('TK Nợ')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Tên TK')
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('expenses_count')
                    ->label('Số phiếu')
                    ->counts('expenses')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            'index' => Pages\ListOpexCategories::route('/'),
            'create' => Pages\CreateOpexCategory::route('/create'),
            'edit' => Pages\EditOpexCategory::route('/{record}/edit'),
        ];
    }
}
