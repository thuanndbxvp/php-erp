<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkPaymentResource\Pages;

use App\Filament\Resources\BulkPaymentResource;
use App\Models\Customer;
use App\Models\InvoiceIn;
use App\Models\InvoiceOut;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBulkPayment extends CreateRecord
{
    protected static string $resource = BulkPaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['status'] = \App\Enums\BulkPaymentStatus::PENDING->value;
        $data['customer_id'] = $data['party_type'] === 'CUSTOMER' ? ($data['party_id'] ?? null) : null;
        $data['supplier_id'] = $data['party_type'] === 'SUPPLIER' ? ($data['party_id'] ?? null) : null;
        return $data;
    }

    protected function afterCreate(): void
    {
        $bulk = $this->record;
        $items = $this->data['items'] ?? [];

        if (empty($items)) {
            return;
        }

        $mapped = [];
        foreach ($items as $row) {
            $mapped[] = [
                'invoice_out_id' => $row['invoice_out_id'] ?? null,
                'invoice_in_id' => $row['invoice_in_id'] ?? null,
                'amount_applied' => (string) ($row['amount_applied'] ?? '0'),
                'notes' => null,
            ];
        }

        // Recreate applications directly (để tách khỏi BulkPaymentService.create nhằm đơn giản hóa)
        \App\Models\BulkPaymentApplication::insert(
            array_map(fn ($i) => [
                'bulk_payment_id' => $bulk->id,
                'invoice_out_id' => $i['invoice_out_id'],
                'invoice_in_id' => $i['invoice_in_id'],
                'amount_applied' => $i['amount_applied'],
                'notes' => null,
            ], $mapped),
        );

        // Recalculate total
        $total = (string) \App\Models\BulkPaymentApplication::where('bulk_payment_id', $bulk->id)->sum('amount_applied');
        $bulk->total_amount = $total;
        $bulk->save();
    }
}