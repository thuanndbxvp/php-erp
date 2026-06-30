<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollRunResource\RelationManagers;

use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Payslip;
use Filament\Forms;

/**
 * RelationManager: danh sách phiếu lương (Payslips) của PayrollRun.
 *
 * Read-mostly: chỉ cho phép update các field điều chỉnh (allowances, other_earnings,
 * advance_deduction, other_deduction, notes) khi payslip còn DRAFT/APPROVED.
 */
class PayslipsRelationManager extends RelationManager
{
    protected static string $relationship = 'payslips';

    protected static ?string $title = 'Phiếu lương';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-receipt-percent';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payslip_number')
            ->columns([
                Tables\Columns\TextColumn::make('payslip_number')
                    ->label('Mã phiếu')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('employee_code_snapshot')
                    ->label('Mã NV'),

                Tables\Columns\TextColumn::make('employee_name_snapshot')
                    ->label('Họ tên')
                    ->searchable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('department_name_snapshot')
                    ->label('Phòng ban')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Lương CB')
                    ->money('VND'),

                Tables\Columns\TextColumn::make('overtime_pay')
                    ->label('OT')
                    ->money('VND'),

                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('HH')
                    ->money('VND'),

                Tables\Columns\TextColumn::make('gross_salary')
                    ->label('Gross')
                    ->money('VND')
                    ->weight('bold')
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_deduction')
                    ->label('Khấu trừ')
                    ->money('VND')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('net_salary')
                    ->label('Net')
                    ->money('VND')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('work_days')
                    ->label('Công')
                    ->alignCenter()
                    ->suffix(' d'),

                Tables\Columns\TextColumn::make('overtime_hours')
                    ->label('OT')
                    ->alignCenter()
                    ->suffix(' h')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('TT')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'APPROVED' => 'warning',
                        'PAID' => 'success',
                        'CANCELLED' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'DRAFT' => 'Nháp',
                        'APPROVED' => 'Đã duyệt',
                        'PAID' => 'Đã chi',
                        'CANCELLED' => 'Huỷ',
                        default => $state,
                    }),
            ])
            ->headerActions([
                // Không có create - payslip được auto-gen từ PayrollService::compute()
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),

                Action::make('editAdjustments')
                    ->label('Điều chỉnh')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Payslip $record) => in_array($record->status, ['DRAFT', 'APPROVED'], true))
                    ->form([
                        Forms\Components\TextInput::make('allowances')
                            ->label('Phụ cấp')
                            ->numeric()
                            ->prefix('₫'),
                        Forms\Components\TextInput::make('other_earnings')
                            ->label('Thu nhập khác')
                            ->numeric()
                            ->prefix('₫'),
                        Forms\Components\TextInput::make('advance_deduction')
                            ->label('Tạm ứng')
                            ->numeric()
                            ->prefix('₫'),
                        Forms\Components\TextInput::make('other_deduction')
                            ->label('Khấu trừ khác')
                            ->numeric()
                            ->prefix('₫'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(2),
                    ])
                    ->fillForm(fn (Payslip $record): array => [
                        'allowances' => $record->allowances,
                        'other_earnings' => $record->other_earnings,
                        'advance_deduction' => $record->advance_deduction,
                        'other_deduction' => $record->other_deduction,
                        'notes' => $record->notes,
                    ])
                    ->action(function (Payslip $record, array $data) {
                        $service = app(\App\Services\HR\PayslipService::class);
                        $service->updateAdjustments($record, $data);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Đã cập nhật phiếu lương')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xóa')
                        ->visible(fn () => false),
                ]),
            ])
            ->defaultSort('employee_code_snapshot');
    }
}
