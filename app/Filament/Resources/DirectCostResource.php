<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\DirectCostType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\DirectCostResource\Pages;
use App\Models\ChartOfAccount;
use App\Models\DirectCost;
use App\Models\SalesOrder;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class DirectCostResource extends Resource
{
    protected static ?string $model = DirectCost::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Chi phí trực tiếp';

    protected static ?string $modelLabel = 'Chi phí trực tiếp';

    protected static ?string $pluralModelLabel = 'Chi phí trực tiếp theo Đơn';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 57;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin chi phí trực tiếp')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('sales_order_id')
                        ->label('Đơn bán (SO) - BẮT BUỘC')
                        ->required()
                        ->helperText('Direct Cost BẮT BUỘC gắn với 1 đơn bán')
                        ->relationship('salesOrder', 'order_number')
                        ->getOptionLabelFromRecordUsing(fn (SalesOrder $so) => "{$so->order_number} ({$so->order_date?->format('d/m/Y')})")
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('cost_type')
                        ->label('Loại chi phí')
                        ->required()
                        ->options(collect(DirectCostType::cases())
                            ->mapWithKeys(fn (DirectCostType $t) => [$t->value => $t->label()])
                            ->toArray())
                        ->native(false),

                    Forms\Components\TextInput::make('title')
                        ->label('Tiêu đề')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('VD: Phí ship đơn SO-2026-00001'),

                    Forms\Components\DatePicker::make('expense_date')
                        ->label('Ngày phát sinh')
                        ->required()
                        ->native(false)
                        ->default(now()),

                    Forms\Components\TextInput::make('amount')
                        ->label('Số tiền')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->prefix('₫'),

                    Forms\Components\Select::make('status')
                        ->label('Trạng thái')
                        ->required()
                        ->options([
                            'DRAFT' => 'Nháp',
                            'APPROVED' => 'Đã duyệt',
                            'POSTED' => 'Đã hạch toán',
                            'CANCELLED' => 'Đã hủy',
                        ])
                        ->default('APPROVED')
                        ->native(false),
                ]),

            Forms\Components\Section::make('Hạch toán (Nợ/Có)')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('debit_account_id')
                        ->label('TK NỢ (chi phí trực tiếp)')
                        ->required()
                        ->helperText('Thường là TK 6411 - Chi phí bán hàng')
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

                    Forms\Components\Select::make('credit_account_id')
                        ->label('TK CÓ (nguồn chi)')
                        ->required()
                        ->helperText('Tiền (1111, 1121) hoặc phải trả (331)')
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

                    Forms\Components\Select::make('currency')
                        ->label('Tiền tệ')
                        ->required()
                        ->options(['VND' => 'VND', 'USD' => 'USD'])
                        ->default('VND')
                        ->native(false),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->label('Tỷ giá')
                        ->numeric()
                        ->default(1)
                        ->minValue(0.0001),
                ]),

            Forms\Components\Section::make('Ghi chú')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Mô tả chi tiết')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('salesOrder.order_number')
                    ->label('Đơn bán')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('cost_type')
                    ->label('Loại')
                    ->formatStateUsing(fn (DirectCostType $state): string => $state->label())
                    ->badge()
                    ->color(fn (DirectCostType $state): string => $state->color())
                    ->icon(fn (DirectCostType $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Ngày chi')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('debitAccount.code')
                    ->label('TK Nợ')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('creditAccount.code')
                    ->label('TK Có')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'DRAFT' => 'Nháp',
                        'APPROVED' => 'Đã duyệt',
                        'POSTED' => 'Đã hạch toán',
                        'CANCELLED' => 'Đã hủy',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'APPROVED' => 'success',
                        'POSTED' => 'info',
                        'DRAFT' => 'gray',
                        'CANCELLED' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cost_type')
                    ->label('Loại chi phí')
                    ->options(collect(DirectCostType::cases())
                        ->mapWithKeys(fn (DirectCostType $t) => [$t->value => $t->label()])
                        ->toArray())
                    ->native(false),

                Tables\Filters\SelectFilter::make('sales_order_id')
                    ->label('Đơn bán')
                    ->relationship('salesOrder', 'order_number')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'DRAFT' => 'Nháp',
                        'APPROVED' => 'Đã duyệt',
                        'POSTED' => 'Đã hạch toán',
                        'CANCELLED' => 'Đã hủy',
                    ])
                    ->native(false),

                Tables\Filters\Filter::make('expense_date')
                    ->label('Khoảng ngày')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Từ')->native(false),
                        Forms\Components\DatePicker::make('to')->label('Đến')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d));
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
            ->defaultSort('expense_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDirectCosts::route('/'),
            'create' => Pages\CreateDirectCost::route('/create'),
            'edit' => Pages\EditDirectCost::route('/{record}/edit'),
        ];
    }
}
