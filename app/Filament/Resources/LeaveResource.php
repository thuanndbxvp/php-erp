<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\LeaveResource\Pages;
use App\Models\Employee;
use App\Models\Leave;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class LeaveResource extends Resource
{
    protected static ?string $model = Leave::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Nghỉ phép';

    protected static ?string $modelLabel = 'Đơn nghỉ phép';

    protected static ?string $pluralModelLabel = 'Nghỉ phép';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 75;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin đơn nghỉ phép')
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

                    Forms\Components\Select::make('leave_type')
                        ->label('Loại nghỉ')
                        ->required()
                        ->options(collect(LeaveType::cases())
                            ->mapWithKeys(fn (LeaveType $t) => [$t->value => $t->label()])->toArray())
                        ->native(false),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('Từ ngày')
                        ->required()
                        ->native(false),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('Đến ngày')
                        ->required()
                        ->native(false)
                        ->afterOrEqual('start_date'),

                    Forms\Components\TextInput::make('total_days')
                        ->label('Tổng ngày (tự tính)')
                        ->disabled()
                        ->dehydrated(false)
                        ->suffix('ngày'),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options(collect(LeaveStatus::cases())
                            ->mapWithKeys(fn (LeaveStatus $s) => [$s->value => $s->label()])->toArray())
                        ->default(LeaveStatus::PENDING->value)
                        ->native(false),

                    Forms\Components\Textarea::make('reason')
                        ->label('Lý do')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Phê duyệt (chỉ duyệt khi đã chuyển trạng thái)')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('approver_notes')
                        ->label('Ghi chú của người duyệt')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('leave_number')
                    ->label('Số đơn')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Nhân viên')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('leave_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (LeaveType $state): string => $state->label())
                    ->badge()
                    ->color(fn (LeaveType $state): string => $state->color()),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Từ')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Đến')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('total_days')
                    ->label('Số ngày')
                    ->suffix(' ngày')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('TT')
                    ->formatStateUsing(fn (LeaveStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (LeaveStatus $state): string => $state->color())
                    ->icon(fn (LeaveStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Người duyệt')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Duyệt lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->options(collect(LeaveStatus::cases())
                        ->mapWithKeys(fn (LeaveStatus $s) => [$s->value => $s->label()])->toArray())
                    ->native(false),

                Tables\Filters\SelectFilter::make('leave_type')
                    ->label('Loại')
                    ->options(collect(LeaveType::cases())
                        ->mapWithKeys(fn (LeaveType $t) => [$t->value => $t->label()])->toArray())
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),

                Tables\Actions\Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Leave $record) => $record->status === LeaveStatus::PENDING)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('approver_notes')
                            ->label('Ghi chú (tuỳ chọn)')
                            ->rows(2),
                    ])
                    ->action(function (Leave $record, array $data) {
                        $service = app(\App\Services\HR\LeaveService::class);
                        $service->approve($record, auth()->user(), $data['approver_notes'] ?? null);
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Từ chối')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Leave $record) => $record->status === LeaveStatus::PENDING)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('approver_notes')
                            ->label('Lý do từ chối (bắt buộc)')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (Leave $record, array $data) {
                        $service = app(\App\Services\HR\LeaveService::class);
                        $service->reject($record, auth()->user(), $data['approver_notes']);
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Huỷ đơn')
                    ->icon('heroicon-o-trash')
                    ->color('warning')
                    ->visible(fn (Leave $record) => in_array($record->status, [LeaveStatus::PENDING, LeaveStatus::APPROVED], true))
                    ->requiresConfirmation()
                    ->action(fn (Leave $record) => app(\App\Services\HR\LeaveService::class)->cancel($record)),

                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (Leave $record) => $record->status === LeaveStatus::PENDING || $record->status === LeaveStatus::DRAFT),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaves::route('/'),
            'create' => Pages\CreateLeave::route('/create'),
            'edit' => Pages\EditLeave::route('/{record}/edit'),
        ];
    }
}
