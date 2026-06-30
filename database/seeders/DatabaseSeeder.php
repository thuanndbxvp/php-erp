<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Khởi tạo dữ liệu nền cho hệ thống ERP.
     */
    public function run(): void
    {
        // Bước 1: Tạo các Role và Permission cốt lõi
        $this->call(RolesAndPermissionsSeeder::class);

        // Bước 2: Tạo tài khoản Super Admin mặc định
        $admin = User::firstOrCreate(
            ['email' => 'admin@viettung.vn'],
            [
                'name' => 'Quản Trị Viên',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Gán role Super Admin cho user admin
        $admin->assignRole('Super Admin');

        // Bước 3: Hệ thống tài khoản kế toán TT200
        $this->call(ChartOfAccountsSeeder::class);

        // Bước 4: Năm tài chính + 12 kỳ mặc định
        $this->call(FiscalYearSeeder::class);
    }
}