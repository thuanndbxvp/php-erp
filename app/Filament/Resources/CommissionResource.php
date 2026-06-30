<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CommissionStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\CommissionResource\Pages;
use App\Models\Commission;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Hoa hồng';

    protected static ?string $modelLabel = 'Khoản hoa hồng';

    protected static ?string $pluralModelLabel = 'Hoa hồng';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 77;

    /**
     * CommissionResource chủ yếu READ-ONLY (được auto-gen từ SalesOrder Observer).
     * Không có form Create/Edit — chỉ cho phép action: Approve, Reverse, Cancel.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin hoa hồng (chỉ xem)')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('employee.full_name')
                        ->label('Nhân viên')
                        ->disabled(),
                    Forms\Components\TextInput::make('salesOrder.order_number')
                        ->label('Đơn bán')
                        ->disabled(),
                    Forms\Components\TextInput::make('rule.name')
                        ->label('Luật áp dụng')
                        ->disabled(),
                    Forms\Components\TextInput::make('order_amount')
                        ->label('Giá trị đơn')
                        ->disabled(),
                    Forms\Components\TextInput::make('target_value')
                        ->label('Target value')
                        ->disabled(),
                    Forms\Components\TextInput::make('commission_amount')
                        ->label('Hoa hồng')
                        ->disabled(),
                    Forms\Components\TextInput::make('status')
                        ->label('Trạng thái')
                        ->disabled(),
                    Forms\Components\TextInput::make('earned_date')
                        ->label('Ngày phát sinh')
                        ->disabled(),
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
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('salesOrder.order_number')
                    ->label('Đơn bán')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('rule.name')
                    ->label('Luật')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order_amount')
                    ->label('Giá trị đơn')
                    ->money('VND')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('target_value')
                    ->label('Target')
                    ->numeric(decimalPlaces: 0)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Hoa hồng')
                    ->money('VND')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('TT')
                    ->formatStateUsing(fn (CommissionStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (CommissionStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('earned_date')
                    ->label('Ngày phát sinh')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Duyệt')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payslip.payslip_number')
                    ->label('Payslip')
                    ->placeholder('—')
                    ->badge()
                    ->color('info')
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
                    ->options(collect(CommissionStatus::cases())
                        ->mapWithKeys(fn (CommissionStatus $s) => [$s->value => $s->label()])->toArray())
                    ->native(false),

                Tables\Filters\Filter::make('earned_date')
                    ->label('Khoảng ngày')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ')->native(false),
                        Forms\Components\DatePicker::make('to')->label('Đến')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('earned_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('earned_date', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),

                Tables\Actions\Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Commission $record) => $record->status === CommissionStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(fn (Commission $record) => app(\App\Services\HR\CommissionService::class)->approve($record)),

                Tables\Actions\Action::make('reverse')
                    ->label('Đảo ngược')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Commission $record) => ! $record->status->isTerminal())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Lý do (không bắt buộc)')
                            ->rows(2),
                    ])
                    ->action(fn (Commission $record, array $data) => app(\App\Services\HR\CommissionService::class)->reverse($record, $data['notes'] ?? null)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Xóa (debug)'),
                ]),
            ])
            ->defaultSort('earned_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissions::route('/'),
            // Không Create/Edit: commission được auto-gen từ SO Observer
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
