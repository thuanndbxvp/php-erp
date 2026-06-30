<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource;
use App\Models\InvoiceIn;
use App\Models\InvoiceOut;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wizard tạo Payment 3 bước:
 *  1. Chọn đối tượng (CUSTOMER/SUPPLIER) + Số tiền
 *  2. Chọn phương thức + tài khoản NH + mã tham chiếu
 *  3. Áp dụng cho Invoice (lặp nhiều lần nếu thanh toán gộp)
 */
class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Thông tin')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('party_type')
                            ->label('Loại dòng tiền')
                            ->required()
                            ->options([
                                PartyType::CUSTOMER->value => '💰 Thu tiền (khách hàng)',
                                PartyType::SUPPLIER->value => '💸 Chi tiền (trả NCC)',
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('invoices', [])),

                        Forms\Components\Select::make('party_id')
                            ->label(function (Forms\Get $get): string {
                                return $get('party_type') === PartyType::SUPPLIER->value ? 'Nhà cung cấp' : 'Khách hàng';
                            })
                            ->required()
                            ->options(function (Forms\Get $get): array {
                                if ($get('party_type') === PartyType::SUPPLIER->value) {
                                    return \App\Models\Supplier::query()->orderBy('name')->pluck('name', 'id')->toArray();
                                }
                                if ($get('party_type') === PartyType::CUSTOMER->value) {
                                    return \App\Models\Customer::query()->orderBy('name')->pluck('name', 'id')->toArray();
                                }
                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('invoices', [])),

                        Forms\Components\TextInput::make('amount')
                            ->label('Tổng số tiền thanh toán')
                            ->required()
                            ->numeric()
                            ->prefix('₫')
                            ->live(onBlur: true)
                            ->helperText('Tổng tiền phiếu - có thể lớn hơn tổng invoice để dư tiền'),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Ngày thanh toán')
                            ->required()
                            ->default(now())
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Step::make('Phương thức & Tài khoản')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->label('Phương thức')
                            ->required()
                            ->options(collect(PaymentMethod::cases())
                                ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => $m->label()])
                                ->toArray())
                            ->default(PaymentMethod::BANK_TRANSFER->value)
                            ->native(false),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Tài khoản nhận/chi')
                            ->relationship('bankAccount', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->native(false),

                        Forms\Components\TextInput::make('reference')
                            ->label('Mã tham chiếu')
                            ->placeholder('Mã CK ngân hàng / mã QR')
                            ->maxLength(100),

                        Forms\Components\Select::make('currency')
                            ->label('Tiền tệ')
                            ->required()
                            ->options(['VND' => 'VND', 'USD' => 'USD'])
                            ->default('VND')
                            ->native(false),

                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('Tỷ giá')
                            ->numeric()
                            ->default(1),
                    ]),

                Step::make('Áp dụng cho Invoice')
                    ->schema([
                        Forms\Components\Repeater::make('invoices')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('invoice_id')
                                    ->label('Hóa đơn')
                                    ->required()
                                    ->options(function (Forms\Get $get) {
                                        $partyType = $get('../../party_type') ?: $get('party_type');
                                        $partyId = $get('../../party_id') ?: $get('party_id');
                                        if (! $partyType || ! $partyId) {
                                            return [];
                                        }
                                        $model = $partyType === PartyType::SUPPLIER->value ? InvoiceIn::class : InvoiceOut::class;
                                        $fk = $partyType === PartyType::SUPPLIER->value ? 'supplier_id' : 'customer_id';
                                        return $model::query()
                                            ->where($fk, $partyId)
                                            ->where('balance_due', '>', 0)
                                            ->whereNotIn('status', ['CANCELLED', 'CREDITED'])
                                            ->orderByDesc('invoice_date')
                                            ->limit(200)
                                            ->get()
                                            ->mapWithKeys(fn ($inv) => [
                                                $inv->id => "{$inv->invoice_number} - Còn: " . number_format((float) $inv->balance_due, 0, ',', '.') . '₫',
                                            ])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                                        if (! $state) {
                                            return;
                                        }
                                        $partyType = $get('../../party_type');
                                        $model = $partyType === PartyType::SUPPLIER->value ? InvoiceIn::class : InvoiceOut::class;
                                        $inv = $model::find($state);
                                        if ($inv) {
                                            $set('amount_applied', (string) $inv->balance_due);
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('amount_applied')
                                    ->label('Số tiền áp dụng')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₫')
                                    ->columnSpan(2),
                            ])
                            ->columns(5)
                            ->addActionLabel('+ Thêm hóa đơn')
                            ->minItems(0)
                            ->collapsible(),

                        Forms\Components\Placeholder::make('hint')
                            ->label('')
                            ->content('💡 Có thể bỏ qua nếu muốn ghi nhận phiếu trước, áp dụng sau từ trang Payment.'),
                    ]),
            ])
            ->columnSpanFull()
            ->submitAction(
                Forms\Components\ButtonAction::make('submit')
                    ->label('Tạo Payment')
                    ->submit('create')
                    ->key('submit'),
            ),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['applied_amount'] = '0';
        $data['remaining_amount'] = (string) ($data['amount'] ?? 0);
        $data['party_id'] = (int) ($data['party_id'] ?? 0);
        $data['status'] = \App\Enums\PaymentStatus::PENDING->value;
        return $data;
    }

    protected function afterCreate(): void
    {
        $payment = $this->record;
        $invoices = $this->data['invoices'] ?? [];

        if (empty($invoices)) {
            return;
        }

        $service = app(\App\Services\PaymentService::class);

        DB::transaction(function () use ($payment, $invoices, $service) {
            foreach ($invoices as $row) {
                $invoiceId = $row['invoice_id'] ?? null;
                $amount = (string) ($row['amount_applied'] ?? '0');
                if (! $invoiceId || bccomp($amount, '0', 2) <= 0) {
                    continue;
                }

                if ($payment->party_type === PartyType::CUSTOMER->value) {
                    $invoice = InvoiceOut::find($invoiceId);
                    if ($invoice) {
                        try {
                            $service->applyToInvoiceOut($payment, $invoice, $amount);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Lỗi áp dụng #' . $invoice->invoice_number)
                                ->body(collect($e->errors())->flatten()->first() ?? 'Lỗi không xác định')
                                ->danger()
                                ->send();
                        }
                    }
                } else {
                    $invoice = InvoiceIn::find($invoiceId);
                    if ($invoice) {
                        try {
                            $service->applyToInvoiceIn($payment, $invoice, $amount);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Lỗi áp dụng #' . $invoice->invoice_number)
                                ->body(collect($e->errors())->flatten()->first() ?? 'Lỗi không xác định')
                                ->danger()
                                ->send();
                        }
                    }
                }
            }
        });
    }
}