<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * Seeder dữ liệu mẫu để chạy End-to-End smoke test.
 *
 * Tạo:
 *  - Admin user (admin@test.vn / password)
 *  - 1 Category, 1 Product
 *  - 2 Warehouse (Main + Dropship vendor)
 *  - 2 Customer (CASH + CREDIT)
 *  - 2 Supplier
 *  - 2 BankAccount (VCB cash + Shopee clearing)
 *
 * Idempotent - chạy nhiều lần không lỗi.
 */
class E2ESetupSeeder extends Seeder
{
    public function run(): void
    {
        // User admin
        User::firstOrCreate(
            ['email' => 'admin@test.vn'],
            [
                'name' => 'E2E Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Category
        Category::firstOrCreate(
            ['code' => 'CAT-001'],
            ['name' => 'Thời trang', 'is_active' => true],
        );

        // Product
        $product = Product::firstOrCreate(
            ['sku' => 'P001-SHIRT'],
            [
                'name' => 'Áo thun Cotton',
                'category_id' => Category::where('code', 'CAT-001')->value('id'),
                'unit' => 'CÁI',
                'sell_price' => '150000',
                'buy_price' => '80000',
                'is_active' => true,
            ],
        );

        // Warehouses
        $whMain = Warehouse::firstOrCreate(
            ['code' => 'WH-HCM'],
            ['name' => 'Kho HCM', 'type' => 'OWN', 'status' => 'ACTIVE', 'address' => '123 Nguyễn Huệ'],
        );
        Warehouse::firstOrCreate(
            ['code' => 'WH-HN'],
            ['name' => 'Kho Hà Nội', 'type' => 'OWN', 'status' => 'ACTIVE', 'address' => '456 Tràng Tiền'],
        );

        // Customers
        Customer::firstOrCreate(
            ['code' => 'KH-CASH'],
            [
                'name' => 'Nguyễn Văn A (Cash)',
                'customer_type' => 'INDIVIDUAL',
                'phone' => '0909000001',
                'email' => 'a@test.vn',
                'payment_term_days' => 0,
                'status' => 'ACTIVE',
            ],
        );
        Customer::firstOrCreate(
            ['code' => 'KH-CREDIT'],
            [
                'name' => 'Cty TNHH B2B (Credit)',
                'customer_type' => 'COMPANY',
                'phone' => '0909000002',
                'email' => 'b2b@test.vn',
                'tax_code' => '0123456789',
                'payment_term_days' => 30,
                'credit_limit' => '50000000',
                'status' => 'ACTIVE',
            ],
        );

        // Suppliers
        Supplier::firstOrCreate(
            ['code' => 'NCC-MAIN'],
            [
                'name' => 'Cty CP May Mặc ABC',
                'supplier_type' => 'COMPANY',
                'phone' => '0909000011',
                'email' => 'abc@ncc.vn',
                'payment_term_days' => 30,
                'status' => 'ACTIVE',
            ],
        );

        // BankAccount
        BankAccount::firstOrCreate(
            ['code' => 'NH-VCB'],
            [
                'name' => 'Vietcombank - CN HCM',
                'account_number' => '0071001234567',
                'bank_name' => 'Vietcombank',
                'bank_branch' => 'CN HCM',
                'account_type' => BankAccountType::CHECKING,
                'currency' => 'VND',
                'opening_balance' => '0',
                'opening_date' => now()->startOfYear(),
                'is_active' => true,
                'is_default' => true,
            ],
        );

        BankAccount::firstOrCreate(
            ['code' => 'SHOPEE-CLR'],
            [
                'name' => 'TK trung gian Shopee',
                'account_number' => null,
                'bank_name' => 'ShopeePay',
                'account_type' => BankAccountType::PLATFORM_CLEARING,
                'currency' => 'VND',
                'opening_balance' => '0',
                'opening_date' => now()->startOfYear(),
                'is_active' => true,
                'platform_id' => 'SHOPEE',
            ],
        );

        $this->command->info('   E2E setup OK: customers, suppliers, products, bank accounts ready.');
    }
}