<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\PositionResource\Pages;
use App\Models\Department;
use App\Models\Position;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Chức vụ';

    protected static ?string $modelLabel = 'Chức vụ';

    protected static ?string $pluralModelLabel = 'Chức vụ';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 72;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin chức vụ')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã chức vụ')
                        ->placeholder('Để trống → tự sinh POS-YYYY-000001')
                        ->maxLength(50)
                        ->unique(ignorable: fn ($record) => $record),

                    Forms\Components\TextInput::make('title')
                        ->label('Tên chức vụ')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('department_id')
                        ->label('Phòng ban')
                        ->required()
                        ->options(
                            Department::query()->where('is_active', true)->orderBy('name')
                                ->get()->mapWithKeys(fn (Department $d) => [$d->id => "{$d->code} — {$d->name}"])->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\TextInput::make('level')
                        ->label('Cấp bậc (1-10)')
                        ->numeric()
                        ->required()
                        ->default(5)
                        ->minValue(1)
                        ->maxValue(10)
                        ->helperText('1 = cấp cao nhất, 10 = nhân viên'),

                    Forms\Components\TextInput::make('min_salary')
                        ->label('Lương tối thiểu (tham khảo)')
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\TextInput::make('max_salary')
                        ->label('Lương tối đa (tham khảo)')
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang sử dụng')
                        ->default(true)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Mô tả')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Mô tả chức vụ')
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
                    ->color('primary'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tên chức vụ')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Phòng ban')
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('level')
                    ->label('Cấp')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('min_salary')
                    ->label('Min')
                    ->money('VND')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('max_salary')
                    ->label('Max')
                    ->money('VND')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Số NV')
                    ->counts('employees')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Phòng ban')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái')
                    ->native(false),
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
            'index' => Pages\ListPositions::route('/'),
            'create' => Pages\CreatePosition::route('/create'),
            'edit' => Pages\EditPosition::route('/{record}/edit'),
        ];
    }
}
