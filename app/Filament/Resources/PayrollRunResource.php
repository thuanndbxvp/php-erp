<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PayrollStatus;
use App\Filament\Resources\PayrollRunResource\Pages;
use App\Filament\Resources\PayrollRunResource\RelationManagers;
use App\Models\PayrollRun;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

/**
 * Filament Resource: Kỳ tính lương (PayrollRun).
 *
 * Quy trình tương tác:
 *  1. Create → DRAFT
 *  2. Action "Tính lương" → chạy PayrollService::compute() → fills payslips
 *  3. User review payslips (xem ở tab Payslips)
 *  4. Action "Duyệt" → DRAFT → APPROVED
 *  5. Action "Chi trả" → APPROVED → PAID + mark Commission PAID
 *  6. Action "Huỷ" → về CANCELLED (nếu chưa PAID)
 */
class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Kỳ tính lương';

    protected static ?string $modelLabel = 'Kỳ tính lương';

    protected static ?string $pluralModelLabel = 'Kỳ tính lương';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 78;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin kỳ lương')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('period_month')
                        ->label('Tháng')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->default(now()->month),

                    Forms\Components\TextInput::make('period_year')
                        ->label('Năm')
                        ->required()
                        ->numeric()
                        ->minValue(2020)
                        ->maxValue(now()->year + 1)
                        ->default(now()->year),

                    Forms\Components\TextInput::make('run_number')
                        ->label('Mã kỳ (auto-gen)')
                        ->helperText('Tự sinh dạng PR-YYYY-MM khi tạo, không sửa')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('PR-YYYY-MM'),

                    Forms\Components\TextInput::make('period_label')
                        ->label('Tên kỳ (auto-gen)')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Tháng MM/YYYY'),

                    Forms\Components\DatePicker::make('payment_date')
                        ->label('Ngày dự kiến chi')
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
                Tables\Columns\TextColumn::make('run_number')
                    ->label('Mã kỳ')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('period_label')
                    ->label('Kỳ')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('period_start_date')
                    ->label('Từ')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('period_end_date')
                    ->label('Đến')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_gross')
                    ->label('Tổng Gross')
                    ->money('VND')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_deduction')
                    ->label('Tổng khấu trừ')
                    ->money('VND')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_net')
                    ->label('Tổng Net')
                    ->money('VND')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('payslips_count')
                    ->label('Số NV')
                    ->counts('payslips')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('TT')
                    ->formatStateUsing(fn (PayrollStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (PayrollStatus $state): string => $state->color())
                    ->icon(fn (PayrollStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Ngày chi')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(collect(PayrollStatus::cases())
                        ->mapWithKeys(fn (PayrollStatus $s) => [$s->value => $s->label()])->toArray())
                    ->native(false),

                Tables\Filters\Filter::make('period_year')
                    ->label('Năm')
                    ->form([
                        Forms\Components\Select::make('value')
                            ->label('Năm')
                            ->options(fn () => PayrollRun::query()->distinct()->pluck('period_year', 'period_year')->toArray())
                            ->native(false),
                    ])
                    ->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($q, $y) => $q->where('period_year', $y))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),

                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (PayrollRun $record) => ! $record->status->isTerminal()),

                // ===== Action: Tính lương =====
                Tables\Actions\Action::make('compute')
                    ->label('Tính lương')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->visible(fn (PayrollRun $record) =>
                        auth()->user()?->can('tinh_luong') ?? false
                        && in_array($record->status->value, ['DRAFT', 'PROCESSING'], true)
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Tính lương cho kỳ này?')
                    ->modalDescription('Hệ thống sẽ tạo/cập nhật payslip cho từng nhân viên đang active. Payslip cũ (nếu có) sẽ bị xoá và tính lại.')
                    ->action(function (PayrollRun $record) {
                        $service = app(\App\Services\HR\PayrollService::class);
                        $payslips = $service->compute($record);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Đã tính lương')
                            ->body("Sinh ra {$payslips->count()} phiếu lương.")
                            ->send();
                    }),

                // ===== Action: Duyệt =====
                Tables\Actions\Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (PayrollRun $record) =>
                        (auth()->user()?->can('duyet_tinh_luong') ?? false)
                        && $record->status->value === 'DRAFT'
                    )
                    ->requiresConfirmation()
                    ->action(function (PayrollRun $record) {
                        $service = app(\App\Services\HR\PayrollService::class);
                        $service->approve($record, auth()->user());
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Đã duyệt kỳ lương')
                            ->send();
                    }),

                // ===== Action: Chi trả =====
                Tables\Actions\Action::make('pay')
                    ->label('Chi trả')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (PayrollRun $record) =>
                        (auth()->user()?->can('chi_tra_luong') ?? false)
                        && $record->status->value === 'APPROVED'
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Ngày chi trả')
                            ->native(false)
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (PayrollRun $record, array $data) {
                        $service = app(\App\Services\HR\PayrollService::class);
                        $service->pay($record, auth()->user(), $data['payment_date']);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Đã chi trả')
                            ->body("Marked {$record->total_net} as paid. Commissions gộp vào payslip đã chuyển sang PAID.")
                            ->send();
                    }),

                // ===== Action: Huỷ =====
                Tables\Actions\Action::make('cancel')
                    ->label('Huỷ kỳ')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PayrollRun $record) =>
                        (auth()->user()?->can('huy_dot_tinh_luong') ?? false)
                        && ! $record->status->isTerminal()
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Lý do huỷ')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (PayrollRun $record, array $data) {
                        $service = app(\App\Services\HR\PayrollService::class);
                        $service->cancel($record, $data['reason']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xóa (debug)')
                        ->visible(fn () => false), // ẩn mặc định
                ]),
            ])
            ->defaultSort('period_year', 'desc')
            ->defaultSort('period_month', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PayslipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'edit' => Pages\EditPayrollRun::route('/{record}/edit'),
        ];
    }

    // ─── RBAC Gates ────────────────────────────────────────────────────────────

    public static function canAccessNavigation(): bool
    {
        $u = auth()->user();

        return $u?->canAny(['xem_danh_sach_tinh_luong', 'xem_chi_tiet_phieu_luong']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('tao_dot_tinh_luong') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $u = auth()->user();
        if (! $u?->can('huy_dot_tinh_luong')) {
            return false;
        }

        return $record->status->value === 'DRAFT';
    }
}
