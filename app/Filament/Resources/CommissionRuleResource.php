<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\TargetType;
use App\Filament\Resources\CommissionRuleResource\Pages;
use App\Models\CommissionRule;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class CommissionRuleResource extends Resource
{
    protected static ?string $model = CommissionRule::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-percent-badge';

    protected static ?string $navigationLabel = 'Luật hoa hồng';

    protected static ?string $modelLabel = 'Luật hoa hồng';

    protected static ?string $pluralModelLabel = 'Luật hoa hồng';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::NHAN_SU;

    protected static ?int $navigationSort = 76;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Thông tin luật')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Tên luật')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('target_type')
                        ->label('Loại chỉ tiêu')
                        ->required()
                        ->options(collect(TargetType::cases())
                            ->mapWithKeys(fn (TargetType $t) => [$t->value => $t->label()])->toArray())
                        ->native(false)
                        ->helperText('REVENUE = doanh thu, ORDER_COUNT = số đơn, PROFIT = lợi nhuận gộp, COLLECTED_AMT = tiền thu hộ, NEW_CUSTOMER = khách hàng mới'),

                    Forms\Components\TextInput::make('rate_percent')
                        ->label('Tỷ lệ hoa hồng (%)')
                        ->required()
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%'),

                    Forms\Components\TextInput::make('min_target_amount')
                        ->label('Target tối thiểu')
                        ->helperText('Không tính hoa hồng nếu target_value < ngưỡng này')
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\TextInput::make('max_commission_amount')
                        ->label('Hạn mức tối đa / kỳ')
                        ->helperText('Cap hoa hồng tối đa / NV / kỳ')
                        ->numeric()
                        ->prefix('₫'),

                    Forms\Components\DatePicker::make('effective_from')
                        ->label('Hiệu lực từ')
                        ->native(false),

                    Forms\Components\DatePicker::make('effective_to')
                        ->label('Hiệu lực đến')
                        ->native(false)
                        ->afterOrEqual('effective_from'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Đang áp dụng')
                        ->default(true)
                        ->inline(false),
                ]),

            Forms\Components\Section::make('Mô tả')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên luật')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('target_type')
                    ->label('Chỉ tiêu')
                    ->formatStateUsing(fn (TargetType $state): string => $state->label())
                    ->badge()
                    ->color(fn (TargetType $state): string => $state->color()),

                Tables\Columns\TextColumn::make('rate_percent')
                    ->label('Tỷ lệ')
                    ->suffix('%')
                    ->alignCenter()
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('min_target_amount')
                    ->label('Tối thiểu')
                    ->money('VND')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('max_commission_amount')
                    ->label('Tối đa')
                    ->money('VND')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label('Từ')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('effective_to')
                    ->label('Đến')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('target_type')
                    ->label('Chỉ tiêu')
                    ->options(collect(TargetType::cases())
                        ->mapWithKeys(fn (TargetType $t) => [$t->value => $t->label()])->toArray())
                    ->native(false),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Trạng thái')
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
            ->defaultSort('rate_percent', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissionRules::route('/'),
            'create' => Pages\CreateCommissionRule::route('/create'),
            'edit' => Pages\EditCommissionRule::route('/{record}/edit'),
        ];
    }

    // ─── RBAC Gates ────────────────────────────────────────────────────────────

    public static function canAccessNavigation(): bool
    {
        return auth()->user()?->canAny(['xem_danh_sach_luat_hoa_hong', 'xem_danh_sach_hoa_hong']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('tao_luat_hoa_hong') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('cap_nhat_luat_hoa_hong') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('xoa_luat_hoa_hong') ?? false;
    }
}
