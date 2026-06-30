<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\PayslipResource\Pages;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

/**
 * Filament Resource: Phiếu lương (Payslip) - read-only.
 *
 * Payslip chủ yếu được quản lý qua PayrollRun (relation manager).
 * Resource này cho phép tra cứu nhanh theo NV / tháng từ navigation.
 *
 * KHÔNG cho phép tạo/sửa/xoá từ resource này (chỉ View).
 */
class PayslipResource extends Resource
{
    protected static ?string $model = Payslip::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Phiếu lương';

    protected static ?string $modelLabel = 'Phiếu lương';

    protected static ?string $pluralModelLabel = 'Phiếu lương';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 79;

    public static function form(Schema $schema): Schema
    {
        // Read-only form (chỉ View sử dụng)
        return $schema->components([
            Forms\Components\Section::make('Thông tin phiếu lương')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('payslip_number')->label('Mã phiếu')->disabled(),
                    Forms\Components\TextInput::make('payrollRun.run_number')->label('Kỳ lương')->disabled(),
                    Forms\Columns\Column::make()
                        ->schema([
                            Forms\Components\TextInput::make('employee_code_snapshot')->label('Mã NV')->disabled(),
                            Forms\Components\TextInput::make('employee_name_snapshot')->label('Họ tên')->disabled(),
                            Forms\Components\TextInput::make('department_name_snapshot')->label('Phòng ban')->disabled(),
                            Forms\Components\TextInput::make('position_name_snapshot')->label('Chức vụ')->disabled(),
                        ]),
                ]),
            Forms\Components\Section::make('Thu nhập (EARNINGS)')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('base_salary')->label('Lương CB')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('allowances')->label('Phụ cấp')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('overtime_pay')->label('OT')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('commission_amount')->label('Hoa hồng')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('other_earnings')->label('Thu nhập khác')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('gross_salary')->label('Tổng Gross')->disabled()->prefix('₫'),
                ]),
            Forms\Components\Section::make('Khấu trừ (DEDUCTIONS)')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('personal_tax')->label('PIT')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('social_insurance')->label('BHXH')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('health_insurance')->label('BHYT')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('unemployment_insurance')->label('BHTN')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('advance_deduction')->label('Tạm ứng')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('other_deduction')->label('Khấu trừ khác')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('total_deduction')->label('Tổng khấu trừ')->disabled()->prefix('₫'),
                ]),
            Forms\Components\Section::make('Thực nhập')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('net_salary')->label('Net (thực nhận)')->disabled()->prefix('₫'),
                    Forms\Components\TextInput::make('payment_date')->label('Ngày chi')->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payslip_number')
                    ->label('Mã phiếu')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('payrollRun.run_number')
                    ->label('Kỳ')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('employee_code_snapshot')
                    ->label('Mã NV')
                    ->searchable(),

                Tables\Columns\TextColumn::make('employee_name_snapshot')
                    ->label('Họ tên')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('gross_salary')
                    ->label('Gross')
                    ->money('VND')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_deduction')
                    ->label('Khấu trừ')
                    ->money('VND')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('net_salary')
                    ->label('Net')
                    ->money('VND')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),

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

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Ngày chi')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payroll_run_id')
                    ->label('Kỳ lương')
                    ->relationship('payrollRun', 'run_number')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\Filter::make('period')
                    ->label('Năm/Tháng')
                    ->form([
                        Forms\Components\Select::make('year')
                            ->label('Năm')
                            ->options(fn () => PayrollRun::query()->distinct()->orderBy('period_year', 'desc')->pluck('period_year', 'period_year')->toArray())
                            ->native(false),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayslips::route('/'),
        ];
    }

    // Không cho tạo / sửa / xoá từ resource này (vào PayrollRun mới có actions)
    public static function canCreate(): bool
    {
        return false;
    }
}
