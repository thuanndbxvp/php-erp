<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\OperatingExpenseResource\Pages;
use App\Models\ChartOfAccount;
use App\Models\OperatingExpense;
use App\Models\OpexCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class OperatingExpenseResource extends Resource
{
    protected static ?string $model = OperatingExpense::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Chi phí vận hành (OPEX)';

    protected static ?string $modelLabel = 'Chi phí vận hành';

    protected static ?string $pluralModelLabel = 'Chi phí vận hành';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::TAI_CHINH;

    protected static ?int $navigationSort = 56;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin chi phí')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('category_id')
                        ->label('Danh mục OPEX')
                        ->required()
                        ->options(
                            OpexCategory::query()
                                ->where('is_active', true)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (OpexCategory $c) => [$c->id => "{$c->code} - {$c->name}"])
                                ->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $cat = OpexCategory::find($state);
                            if ($cat) {
                                $set('debit_account_id', $cat->account_id);
                            }
                        }),

                    Forms\Components\TextInput::make('expense_number')
                        ->label('Mã phiếu chi')
                        ->maxLength(50)
                        ->placeholder('Tự sinh nếu để trống'),

                    Forms\Components\TextInput::make('title')
                        ->label('Tiêu đề')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('VD: Tiền điện T6/2026'),

                    Forms\Components\DatePicker::make('expense_date')
                        ->label('Ngày chi')
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
                        ->label('TK NỢ (chi phí)')
                        ->required()
                        ->helperText('TK 6xx, 8xx, 9xx - thường lấy từ danh mục')
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
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Ngày chi')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expense_number')
                    ->label('Mã phiếu')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category.code')
                    ->label('Danh mục')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('danger'),

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

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Người tạo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Danh mục')
                    ->relationship('category', 'code')
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
            'index' => Pages\ListOperatingExpenses::route('/'),
            'create' => Pages\CreateOperatingExpense::route('/create'),
            'edit' => Pages\EditOperatingExpense::route('/{record}/edit'),
        ];
    }
}
