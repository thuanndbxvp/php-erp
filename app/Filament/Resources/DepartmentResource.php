<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use App\Models\Employee;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

/**
 * Filament Resource: Phòng ban (Department).
 *
 * Hỗ trợ:
 *  - CRUD cơ bản
 *  - Quan hệ parent/children + manager (nullable)
 *  - Bảng cho thấy số NV / số chức vụ trực thuộc
 */
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Phòng ban';

    protected static ?string $modelLabel = 'Phòng ban';

    protected static ?string $pluralModelLabel = 'Phòng ban';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 71;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin phòng ban')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Mã phòng ban')
                        ->placeholder('Để trống → tự sinh DEP-YYYY-000001')
                        ->maxLength(50)
                        ->unique(ignorable: fn ($record) => $record),

                    Forms\Components\TextInput::make('name')
                        ->label('Tên phòng ban')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignorable: fn ($record) => $record)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('parent_id')
                        ->label('Phòng ban cha')
                        ->helperText('Để trống nếu là phòng ban cấp cao nhất')
                        ->relationship('parent', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('manager_id')
                        ->label('Trưởng phòng')
                        ->helperText('Nhân viên đứng đầu phòng ban (nullable)')
                        ->relationship('manager', 'full_name')
                        ->getOptionLabelFromRecordUsing(fn (Employee $e) => "{$e->employee_code} — {$e->full_name}")
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang hoạt động')
                        ->default(true)
                        ->inline(false)
                        ->columnSpanFull(),

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
                    ->label('Tên phòng ban')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Phòng ban cha')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manager.full_name')
                    ->label('Trưởng phòng')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Số NV')
                    ->counts('employees')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('positions_count')
                    ->label('Số chức vụ')
                    ->counts('positions')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái')
                    ->placeholder('Tất cả')
                    ->trueLabel('Đang active')
                    ->falseLabel('Ngừng active')
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
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
