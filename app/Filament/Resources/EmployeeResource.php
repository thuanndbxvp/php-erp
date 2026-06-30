<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\NavigationGroup;
use App\Enums\SalaryType;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Nhân viên';

    protected static ?string $modelLabel = 'Nhân viên';

    protected static ?string $pluralModelLabel = 'Nhân viên';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 73;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin cá nhân')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('employee_code')
                        ->label('Mã NV')
                        ->placeholder('Để trống → tự sinh EMP-YYYY-000001')
                        ->maxLength(50)
                        ->unique(ignorable: fn ($record) => $record),

                    Forms\Components\TextInput::make('full_name')
                        ->label('Họ và tên')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('phone')
                        ->label('Số điện thoại')
                        ->tel()
                        ->maxLength(20),

                    Forms\Components\DatePicker::make('date_of_birth')
                        ->label('Ngày sinh')
                        ->native(false)
                        ->maxDate(now()->subYears(18)),

                    Forms\Components\Select::make('gender')
                        ->label('Giới tính')
                        ->options(['M' => 'Nam', 'F' => 'Nữ', 'O' => 'Khác'])
                        ->native(false),

                    Forms\Components\TextInput::make('id_card_number')
                        ->label('CMND/CCCD')
                        ->maxLength(20),

                    Forms\Components\Textarea::make('address')
                        ->label('Địa chỉ')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Liên kết User & Tổ chức')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('User liên kết')
                        ->helperText('User phải UNIQUE trên bảng NV (1 user ↔ 1 NV)')
                        ->options(
                            User::query()->orderBy('name')->get()
                                ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"])->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('department_id')
                        ->label('Phòng ban')
                        ->options(
                            Department::query()->where('is_active', true)->orderBy('name')
                                ->get()->mapWithKeys(fn (Department $d) => [$d->id => "{$d->code} — {$d->name}"])->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('position_id')
                        ->label('Chức vụ')
                        ->options(
                            Position::query()->where('is_active', true)->orderBy('title')
                                ->get()->mapWithKeys(fn (Position $p) => [$p->id => "{$p->title} (lv {$p->level})"])->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('manager_id')
                        ->label('Quản lý trực tiếp')
                        ->helperText('Để trống = top-level (giám đốc)')
                        ->options(
                            Employee::query()->where('status', '!=', EmployeeStatus::TERMINATED->value)
                                ->orderBy('full_name')
                                ->get()
                                ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} — {$e->full_name}"])
                                ->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),
                ]),

            Forms\Components\Section::make('Hợp đồng & Trạng thái')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('employee_type')
                        ->label('Loại NV')
                        ->required()
                        ->options(collect(EmployeeType::cases())
                            ->mapWithKeys(fn (EmployeeType $t) => [$t->value => $t->label()])->toArray())
                        ->native(false),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options(collect(EmployeeStatus::cases())
                            ->mapWithKeys(fn (EmployeeStatus $s) => [$s->value => $s->label()])->toArray())
                        ->native(false),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('Ngày vào làm')
                        ->required()
                        ->native(false)
                        ->default(now()),

                    Forms\Components\DatePicker::make('probation_end_date')
                        ->label('Kết thúc thử việc')
                        ->native(false),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('Ngày nghỉ việc')
                        ->native(false)
                        ->helperText('Tự động set khi chuyển sang TERMINATED'),
                ]),

            Forms\Components\Section::make('Lương & Phúc lợi')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('base_salary')
                        ->label('Lương cơ bản')
                        ->required()
                        ->numeric()
                        ->prefix('₫')
                        ->default(0),

                    Forms\Components\Select::make('salary_type')
                        ->label('Hình thức lương')
                        ->required()
                        ->options(collect(SalaryType::cases())
                            ->mapWithKeys(fn (SalaryType $t) => [$t->value => $t->label()])->toArray())
                        ->native(false),

                    Forms\Components\TextInput::make('bank_name')
                        ->label('Tên ngân hàng')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('bank_account_number')
                        ->label('Số TK')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('bank_account_holder')
                        ->label('Chủ TK')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('tax_code')
                        ->label('Mã số thuế')
                        ->maxLength(50),

                    Forms\Components\TextInput::make('dependents_count')
                        ->label('Số người phụ thuộc')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Giảm trừ gia cảnh khi tính PIT'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('Mã NV')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Họ tên')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Phòng ban')
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('position.title')
                    ->label('Chức vụ')
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('manager.full_name')
                    ->label('Quản lý')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('employee_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (EmployeeType $state): string => $state->label())
                    ->badge()
                    ->color(fn (EmployeeType $state): string => $state->color()),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (EmployeeStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (EmployeeStatus $state): string => $state->color())
                    ->icon(fn (EmployeeStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Lương CB')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Vào làm')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Nghỉ việc')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Phòng ban')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('position_id')
                    ->label('Chức vụ')
                    ->relationship('position', 'title')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(EmployeeStatus::cases())
                        ->mapWithKeys(fn (EmployeeStatus $s) => [$s->value => $s->label()])->toArray())
                    ->native(false),

                Tables\Filters\SelectFilter::make('employee_type')
                    ->label('Loại NV')
                    ->options(collect(EmployeeType::cases())
                        ->mapWithKeys(fn (EmployeeType $t) => [$t->value => $t->label()])->toArray())
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
            ->defaultSort('employee_code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
