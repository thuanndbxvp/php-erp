<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BankAccountType;
use App\Enums\BulkPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlatformTxStatus;
use App\Enums\TxType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\PaymentApplication;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BankTransactionService;
use App\Services\BulkPaymentService;
use App\Services\InvoiceOutService;
use App\Services\PaymentService;
use App\Services\SalesOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Seeder chạy end-to-end workflow KHỐI 1+2+3:
 *
 *   Phase A (Sales - Customer flow):
 *     Tạo SO (Cash) → Approve → Ship
 *                  → InvoiceOut → Issue (auto-post journal 131 / 5111 + 33311)
 *                  → Payment NH thu tiền (auto-post 1121 / 131)
 *                  → BankTransaction vào tài khoản → Reconcile with Payment
 *                  → SOLD check (invoice 100% paid, AR=0)
 *
 *   Phase B (Purchase - Supplier flow):
 *     Tạo PO → Receive → InvoiceIn → Issue (auto-post 632 + 1331 / 331)
 *                            → Payment NH chi tiền (auto-post 331 / 1121)
 *
 *   Phase C (BulkPayment - gộp nhiều HĐ bán):
 *     Tạo 2 SO khác → 2 InvoiceOut → BulkPayment gộp → Process (auto)
 *
 *   Phase D (Báo cáo):
 *     Snapshot cash flow, AR aging, P&L summary.
 *
 * Sau khi chạy:
 *   - Sổ cái có nhiều bút toán cân bằng
 *   - Tất cả relationships OK
 */
class EndToEndWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('optimize:clear');

        $actor = User::where('email', 'admin@test.vn')->firstOrFail();
        $customer = Customer::where('code', 'KH-CASH')->firstOrFail();
        $customerCredit = Customer::where('code', 'KH-CREDIT')->firstOrFail();
        $supplier = Supplier::where('code', 'NCC-MAIN')->firstOrFail();
        $product = Product::where('sku', 'P001-SHIRT')->firstOrFail();
        $warehouse = Warehouse::where('code', 'WH-HCM')->firstOrFail();
        $bankVcB = BankAccount::where('code', 'NH-VCB')->firstOrFail();
        $shopeeClr = BankAccount::where('code', 'SHOPEE-CLR')->firstOrFail();

        $this->command->info('');
        $this->command->info('========== PHASE A: SALES - CUSTOMER FLOW ==========');

        // Tạo inventory cho product
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity_on_hand' => '50', 'quantity_reserved' => '0', 'average_cost' => '80000'],
        );

        // A1) Tạo SO - khách CASH
        $orderNumber = app(\App\Services\OrderNumberGenerator::class)->nextSalesOrderNumber();
        $this->command->info("A1. Tạo SO Cash cho khách KH-CASH - {$orderNumber}");

        $soCash = SalesOrder::create([
            'order_number' => $orderNumber,
            'type' => OrderType::WAREHOUSE->value,
            'status' => OrderStatus::DRAFT->value,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'currency' => 'VND',
            'exchange_rate' => '1',
            'subtotal' => '0',
            'discount_amount' => '0',
            'tax_amount' => '0',
            'total_amount' => '0',
            'total_cost' => '0',
            'created_by' => $actor->id,
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $soCash->id,
            'product_id' => $product->id,
            'product_snapshot' => [
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
            ],
            'quantity' => '2',
            'unit_price' => '150000',
            'base_cost' => '80000',
            'line_cost' => '160000',
            'discount_percent' => '0',
            'discount_amount' => '0',
            'tax_percent' => '10',
            'line_total' => '330000',
            'sort_order' => 0,
        ]);

        // Update totals
        $soCash->subtotal = '300000';
        $soCash->tax_amount = '30000';
        $soCash->total_amount = '330000';
        $soCash->total_cost = '160000';
        $soCash->save();

        $this->command->info("   SO total: 330,000 VND (subtotal 300k + VAT 10%)");

        // A2) Approve SO (DRAFT → PENDING → CONFIRMED)
        $this->command->info('A2. Approve SO: DRAFT → PENDING → CONFIRMED');
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $soCash->fresh(), OrderStatus::PENDING, $actor, 'E2E submit',
        );
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $soCash->fresh(), OrderStatus::CONFIRMED, $actor, 'E2E approve',
        );

        // A3) Ship SO (CONFIRMED → PROCESSING → SHIPPING → SHIPPED)
        $this->command->info('A3. Ship SO: PROCESSING → SHIPPING → SHIPPED');
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $soCash->fresh(), OrderStatus::PROCESSING, $actor, 'E2E processing',
        );
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $soCash->fresh(), OrderStatus::SHIPPING, $actor, 'E2E shipping',
        );
        app(SalesOrderService::class)->ship($soCash->fresh(), $actor);

        // A4) Tạo InvoiceOut
        $this->command->info('A4. Tạo InvoiceOut từ SO (tự ghi bút toán DT)');
        $invOutCash = app(InvoiceOutService::class)->createFromSalesOrder(
            $soCash->fresh(),
            $actor,
            [], // meta - lấy từ SO
        );
        // Issue invoice - đây là lúc JournalTemplates.postInvoiceOutIssued được gọi
        app(InvoiceOutService::class)->issue($invOutCash, $actor);

        $this->command->info("   InvoiceOut {$invOutCash->invoice_number} total: {$invOutCash->total}");

        // A5) Payment - khách trả tiền mặt qua NH (tự ghi bút toán 1121 / 131)
        $this->command->info('A5. Tạo Payment - khách trả qua NH (tự ghi bút toán tiền/Nợ)');
        $payCash = app(PaymentService::class)->record(
            partyType: PartyType::CUSTOMER,
            party: $customer,
            method: PaymentMethod::BANK_TRANSFER,
            amount: '330000',
            actor: $actor,
            meta: [
                'payment_date' => now()->toDateString(),
                'bank_account_id' => $bankVcB->id,
                'reference' => 'E2E-VCB-001',
                'notes' => 'E2E Khách CASH thanh toán HĐ ' . $invOutCash->invoice_number,
            ],
        );
        app(PaymentService::class)->applyToInvoiceOut($payCash, $invOutCash->fresh(), '330000');
        $this->command->info("   Payment {$payCash->payment_number} status: {$payCash->status->value}");

        // Verify invoice paid hết
        $invOutCash->refresh();
        $this->command->info("   InvoiceOut paid: {$invOutCash->paid_amount} / {$invOutCash->total}");
        $this->command->info("   InvoiceOut status: {$invOutCash->status->label()}");
        $this->command->info("   Balance due: {$invOutCash->balance_due}");

        // A6) BankTransaction khớp với Payment
        $this->command->info('A6. Tạo BankTransaction (sao kê thực tế) + đối soát với Payment');
        $importResult = app(BankTransactionService::class)->importBatch($bankVcB, [[
            'transaction_date' => now()->toDateString(),
            'type' => TxType::TRANSFER_IN,
            'amount' => '330000',
            'balance' => '330000',
            'reference' => 'E2E-VCB-001',
            'description' => 'Khach CASH chuyen khoan',
            'counterparty_name' => 'Nguyen Van A',
        ]], $actor);

        $bankTx = \App\Models\BankTransaction::where('reference', 'E2E-VCB-001')->first();
        app(BankTransactionService::class)->reconcileWithPayment($bankTx, $payCash->fresh(), $actor);
        $bankTx->refresh();
        $this->command->info("   BankTransaction ref={$bankTx->reference} status: {$bankTx->recon_status->value}");
        $this->command->info("   Matched payment: " . ($bankTx->matched_payment_id ? "✅ #{$bankTx->matched_payment_id}" : '❌'));

        $this->command->info('');
        $this->command->info('========== PHASE B: PURCHASE - SUPPLIER FLOW ==========');

        // B1) PO tương ứng - giả lập mua lại hàng
        $poNumber = app(\App\Services\OrderNumberGenerator::class)->nextPurchaseOrderNumber();
        $po = \App\Models\PurchaseOrder::create([
            'order_number' => $poNumber,
            'type' => \App\Enums\OrderType::WAREHOUSE->value,
            'status' => OrderStatus::DRAFT->value,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'currency' => 'VND',
            'exchange_rate' => '1',
            'subtotal' => '0',
            'discount_amount' => '0',
            'tax_amount' => '0',
            'total_amount' => '0',
            'created_by' => $actor->id,
        ]);

        \App\Models\PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_snapshot' => [
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
            ],
            'quantity' => '5',
            'unit_cost' => '80000',
            'line_total' => '440000',
            'tax_percent' => '10',
            'discount_amount' => '0',
            'received_quantity' => '0',
            'sort_order' => 0,
        ]);

        $po->subtotal = '400000';
        $po->tax_amount = '40000';
        $po->total_amount = '440000';
        $po->save();

        // B2) Approve PO (DRAFT → PENDING → CONFIRMED)
        $this->command->info("B2. Approve PO {$po->order_number}");
        app(\App\Services\OrderStatusService::class)->transitionPurchaseOrder(
            $po->fresh(),
            OrderStatus::PENDING,
            $actor,
            'E2E submit',
        );
        app(\App\Services\OrderStatusService::class)->transitionPurchaseOrder(
            $po->fresh(),
            OrderStatus::CONFIRMED,
            $actor,
            'E2E approve',
        );

        // B3) Receive PO
        $this->command->info("B3. Receive PO → RECEIVED");
        app(\App\Services\OrderStatusService::class)->transitionPurchaseOrder(
            $po->fresh(),
            OrderStatus::PROCESSING,
            $actor,
            'E2E processing',
        );
        app(\App\Services\OrderStatusService::class)->transitionPurchaseOrder(
            $po->fresh(),
            OrderStatus::RECEIVED,
            $actor,
            'E2E received',
        );

        // B4) InvoiceIn
        $this->command->info('B4. Tạo InvoiceIn từ PO (ghi bút toán giá vốn + VAT đầu vào)');
        $invIn = app(\App\Services\InvoiceInService::class)->createFromPurchaseOrder(
            $po->fresh(),
            $actor,
        );
        app(\App\Services\InvoiceInService::class)->issue($invIn, $actor);
        $this->command->info("   InvoiceIn {$invIn->invoice_number} total: {$invIn->total}");

        // B5) Payment - trả tiền NCC
        $this->command->info('B5. Thanh toán cho NCC qua NH (ghi bút toán AP/Cash)');
        $paySup = app(PaymentService::class)->record(
            partyType: PartyType::SUPPLIER,
            party: $supplier,
            method: PaymentMethod::BANK_TRANSFER,
            amount: '440000',
            actor: $actor,
            meta: [
                'payment_date' => now()->toDateString(),
                'bank_account_id' => $bankVcB->id,
                'reference' => 'E2E-VCB-002',
                'notes' => 'E2E Trả NCC HĐ ' . $invIn->invoice_number,
            ],
        );
        app(PaymentService::class)->applyToInvoiceIn($paySup, $invIn->fresh(), '440000');
        $this->command->info("   Payment {$paySup->payment_number} status: {$paySup->status->value}");
        $invIn->refresh();
        $this->command->info("   InvoiceIn paid: {$invIn->paid_amount} / {$invIn->total} - status: {$invIn->status->label()}");

        // B6) Reconcile với sao kê
        $this->command->info('B6. Đối soát với sao kê NH');
        app(BankTransactionService::class)->importBatch($bankVcB, [[
            'transaction_date' => now()->toDateString(),
            'type' => TxType::TRANSFER_OUT,
            'amount' => '-440000',
            'balance' => '-110000',
            'reference' => 'E2E-VCB-002',
            'description' => 'Tra tien cho NCC ABC',
            'counterparty_name' => 'Cty ABC',
        ]], $actor);

        $bankTx2 = \App\Models\BankTransaction::where('reference', 'E2E-VCB-002')->first();
        app(BankTransactionService::class)->reconcileWithPayment($bankTx2, $paySup->fresh(), $actor);
        $bankTx2->refresh();
        $this->command->info("   BankTransaction ref={$bankTx2->reference} recon: {$bankTx2->recon_status->label()}");

        $this->command->info('');
        $this->command->info('========== PHASE C: BULK PAYMENT - GỘP NHIỀU HĐ ==========');

        // Tạo 2 SO khác với khách CREDIT
        $so2 = SalesOrder::create([
            'order_number' => app(\App\Services\OrderNumberGenerator::class)->nextSalesOrderNumber(),
            'type' => OrderType::WAREHOUSE->value,
            'status' => OrderStatus::DRAFT->value,
            'customer_id' => $customerCredit->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'subtotal' => '0',
            'tax_amount' => '0',
            'total_amount' => '0',
            'total_cost' => '0',
            'currency' => 'VND',
            'exchange_rate' => '1',
            'created_by' => $actor->id,
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $so2->id,
            'product_id' => $product->id,
            'product_snapshot' => ['sku' => $product->sku, 'name' => $product->name, 'unit' => $product->unit],
            'quantity' => '3',
            'unit_price' => '150000',
            'base_cost' => '80000',
            'line_cost' => '240000',
            'discount_percent' => '0',
            'discount_amount' => '0',
            'tax_percent' => '10',
            'line_total' => '495000',
            'sort_order' => 0,
        ]);

        $so2->subtotal = '450000';
        $so2->tax_amount = '45000';
        $so2->total_amount = '495000';
        $so2->total_cost = '240000';
        $so2->save();

        // APPROVE (DRAFT → PENDING → CONFIRMED)
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so2->fresh(), OrderStatus::PENDING, $actor, 'E2E submit',
        );
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so2->fresh(), OrderStatus::CONFIRMED, $actor, 'E2E approve',
        );

        // SHIP
        $this->command->info('   Ship SO 2 & SO 3');
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so2->fresh(), OrderStatus::PROCESSING, $actor, 'E2E processing',
        );
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so2->fresh(), OrderStatus::SHIPPING, $actor, 'E2E shipping',
        );
        app(SalesOrderService::class)->ship($so2->fresh(), $actor);

        $invOut2 = app(InvoiceOutService::class)->createFromSalesOrder($so2->fresh(), $actor);
        app(InvoiceOutService::class)->issue($invOut2, $actor);

        // Tạo SO3 tương tự
        $so3 = SalesOrder::create([
            'order_number' => app(\App\Services\OrderNumberGenerator::class)->nextSalesOrderNumber(),
            'type' => OrderType::WAREHOUSE->value,
            'status' => OrderStatus::DRAFT->value,
            'customer_id' => $customerCredit->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'subtotal' => '0',
            'tax_amount' => '0',
            'total_amount' => '0',
            'total_cost' => '0',
            'currency' => 'VND',
            'exchange_rate' => '1',
            'created_by' => $actor->id,
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $so3->id,
            'product_id' => $product->id,
            'product_snapshot' => ['sku' => $product->sku, 'name' => $product->name, 'unit' => $product->unit],
            'quantity' => '1',
            'unit_price' => '150000',
            'base_cost' => '80000',
            'line_cost' => '80000',
            'discount_percent' => '0',
            'discount_amount' => '0',
            'tax_percent' => '10',
            'line_total' => '165000',
            'sort_order' => 0,
        ]);

        $so3->subtotal = '150000';
        $so3->tax_amount = '15000';
        $so3->total_amount = '165000';
        $so3->total_cost = '80000';
        $so3->save();

        // APPROVE SO3
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so3->fresh(), OrderStatus::PENDING, $actor, 'E2E submit',
        );
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so3->fresh(), OrderStatus::CONFIRMED, $actor, 'E2E approve',
        );

        // SHIP SO3
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so3->fresh(), OrderStatus::PROCESSING, $actor, 'E2E processing',
        );
        app(\App\Services\OrderStatusService::class)->transitionSalesOrder(
            $so3->fresh(), OrderStatus::SHIPPING, $actor, 'E2E shipping',
        );
        app(SalesOrderService::class)->ship($so3->fresh(), $actor);

        $invOut3 = app(InvoiceOutService::class)->createFromSalesOrder($so3->fresh(), $actor);
        app(InvoiceOutService::class)->issue($invOut3, $actor);

        $this->command->info("   Tạo 2 HĐ CREDIT: {$invOut2->invoice_number} ({$invOut2->total}đ), {$invOut3->invoice_number} ({$invOut3->total}đ)");

        // Tạo BulkPayment gộp 2 HĐ
        $this->command->info('C1. Tạo BulkPayment gộp cả 2 HĐ');
        $bulk = \App\Models\BulkPayment::create([
            'bulk_number' => app(\App\Services\OrderNumberGenerator::class)->nextBulkPaymentNumber(),
            'party_type' => PartyType::CUSTOMER->value,
            'party_id' => $customerCredit->id,
            'customer_id' => $customerCredit->id,
            'total_amount' => '0',
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'bank_account_id' => $bankVcB->id,
            'payment_date' => now()->toDateString(),
            'reference' => 'E2E-BP-001',
            'description' => 'E2E Gộp 2 HĐ cho khách CREDIT',
            'status' => BulkPaymentStatus::PENDING->value,
            'notes' => null,
            'created_by' => $actor->id,
        ]);

        \App\Models\BulkPaymentApplication::create([
            'bulk_payment_id' => $bulk->id,
            'invoice_out_id' => $invOut2->id,
            'invoice_in_id' => null,
            'amount_applied' => '495000',
            'notes' => null,
        ]);
        \App\Models\BulkPaymentApplication::create([
            'bulk_payment_id' => $bulk->id,
            'invoice_out_id' => $invOut3->id,
            'invoice_in_id' => null,
            'amount_applied' => '165000',
            'notes' => null,
        ]);

        $total = (string) \App\Models\BulkPaymentApplication::where('bulk_payment_id', $bulk->id)->sum('amount_applied');
        $bulk->total_amount = $total;
        $bulk->save();

        // C2) Process
        $this->command->info('C2. Process BulkPayment → tạo 1 Payment + 2 applications');
        $bulkPayment = app(BulkPaymentService::class)->process($bulk->fresh(), $actor);

        $bulk->refresh();
        $this->command->info("   BulkPayment status: {$bulk->status->label()}");
        $this->command->info("   Payment tổng: {$bulkPayment->payment_number} amount={$bulkPayment->amount}");

        // Verify 2 invoice paid hết
        $invOut2->refresh(); $invOut3->refresh();
        $this->command->info("   HĐ2 paid: {$invOut2->paid_amount}/{$invOut2->total} - status: {$invOut2->status->label()}");
        $this->command->info("   HĐ3 paid: {$invOut3->paid_amount}/{$invOut3->total} - status: {$invOut3->status->label()}");

        $this->command->info('');
        $this->command->info('========== PHASE D: BÁO CÁO ==========');

        // Snapshot cash flow
        $summary = $this->computeSummary();

        $this->command->info('Cash & Inventory Snapshot:');
        $this->command->info("  Bank VCB balance: " . number_format((float) $bankVcB->fresh()->current_balance, 0, ',', '.') . ' ₫');
        $this->command->info("  ChartOfAccount 1121 (NH): " . number_format(ChartOfAccount::where('code', '1121')->first()->balance(), 0, ',', '.') . ' ₫');
        $this->command->info("  ChartOfAccount 131 (AR):  " . number_format(ChartOfAccount::where('code', '131')->first()->balance(), 0, ',', '.') . ' ₫');
        $this->command->info("  ChartOfAccount 331 (AP):  " . number_format(ChartOfAccount::where('code', '331')->first()->balance(), 0, ',', '.') . ' ₫');
        $this->command->info("  ChartOfAccount 5111 (DT): " . number_format(ChartOfAccount::where('code', '5111')->first()->balance(), 0, ',', '.') . ' ₫');
        $this->command->info("  ChartOfAccount 632 (COGS):" . number_format(ChartOfAccount::where('code', '632')->first()->balance(), 0, ',', '.') . ' ₫');

        $this->command->info('');
        $this->command->info('Journal Entry count: ' . \App\Models\JournalEntry::count() . ' (auto-posted via services)');
        $this->command->info('Ledger Entry count: ' . \App\Models\LedgerEntry::count());
        $this->command->info('InvoiceOut count: ' . \App\Models\InvoiceOut::count() . ' (paid=' . \App\Models\InvoiceOut::where('status', InvoiceStatus::PAID)->count() . ')');
        $this->command->info('InvoiceIn count: ' . \App\Models\InvoiceIn::count() . ' (paid=' . \App\Models\InvoiceIn::where('status', InvoiceStatus::PAID)->count() . ')');
        $this->command->info('Payment count: ' . \App\Models\Payment::count());
        $this->command->info('BankTransaction count: ' . \App\Models\BankTransaction::count() . ' (matched=' . \App\Models\BankTransaction::where('recon_status', \App\Enums\ReconStatus::MATCHED)->count() . ')');
    }

    private function computeSummary(): array
    {
        // E2E summary
        return [];
    }
}