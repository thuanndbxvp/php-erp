<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\AttendanceStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\Employee;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Chấm công';

    protected static ?string $modelLabel = 'Chấm công';

    protected static ?string $pluralModelLabel = 'Chấm công';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 74;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin chấm công')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('Nhân viên')
                        ->required()
                        ->options(
                            Employee::query()->orderBy('full_name')
                                ->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->employee_code} — {$e->full_name}"])->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\DatePicker::make('date')
                        ->label('Ngày')
                        ->required()
                        ->native(false)
                        ->default(now()),

                    Forms\Components\TimePicker::make('check_in')
                        ->label('Giờ vào')
                        ->native(false),

                    Forms\Components\TimePicker::make('check_out')
                        ->label('Giờ ra')
                        ->native(false),

                    Forms\Components\TextInput::make('overtime_hours')
                        ->label('Giờ OT')
                        ->numeric()
                        ->default(0)
                        ->step(0.5)
                        ->suffix('giờ'),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options(collect(AttendanceStatus::cases())
                            ->mapWithKeys(fn (AttendanceStatus $s) => [$s->value => $s->label()])->toArray())
                        ->default(AttendanceStatus::PRESENT->value)
                        ->native(false),

                    Forms\Components\TextInput::make('work_hours')
                        ->label('Giờ công (tự tính)')
                        ->numeric()
                        ->default(0)
                        ->step(0.5)
                        ->disabled()
                        ->dehydrated(false)
                        ->suffix('giờ'),

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
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Nhân viên')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.employee_code')
                    ->label('Mã NV')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('date')
                    ->label('Ngày')
                    ->date('d/m/Y (D)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_in')
                    ->label('Vào')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('check_out')
                    ->label('Ra')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('work_hours')
                    ->label('Giờ công')
                    ->suffix('h')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('overtime_hours')
                    ->label('OT')
                    ->suffix('h')
                    ->alignCenter()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('status')
                    ->label('TT')
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (AttendanceStatus $state): string => $state->color())
                    ->icon(fn (AttendanceStatus $state): string => $state->icon()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Nhân viên')
                    ->relationship('employee', 'full_name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(AttendanceStatus::cases())
                        ->mapWithKeys(fn (AttendanceStatus $s) => [$s->value => $s->label()])->toArray())
                    ->native(false),

                Tables\Filters\Filter::make('date')
                    ->label('Khoảng ngày')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ')->native(false),
                        Forms\Components\DatePicker::make('to')->label('Đến')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d));
                    }),
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
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
