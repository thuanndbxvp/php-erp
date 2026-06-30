<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Seeder khởi tạo Hệ thống tài khoản kế toán theo TT200/2014/TT-BTC.
 *
 * Gồm các tài khoản thường dùng cho ERP thương mại điện tử:
 *  - 1xx : Tài sản (Tiền, NH, Phải thu, Tồn kho)
 *  - 3xx : Nợ phải trả (Phải trả NCC, Người mua trả trước)
 *  - 4xx : Vốn chủ sở hữu
 *  - 5xx : Doanh thu
 *  - 6xx : Chi phí
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ===== 1xx - Tài sản =====
            ['code' => '1111', 'name' => 'Tiền mặt',                'type' => AccountType::ASSET],
            ['code' => '1121', 'name' => 'Tiền gửi ngân hàng',     'type' => AccountType::ASSET],
            ['code' => '1128', 'name' => 'TK trung gian sàn TMĐT', 'type' => AccountType::ASSET],
            ['code' => '131',  'name' => 'Phải thu khách hàng',    'type' => AccountType::ASSET],
            ['code' => '1331', 'name' => 'Thuế GTGT đầu vào',      'type' => AccountType::ASSET],
            ['code' => '141',  'name' => 'Tạm ứng / Trả trước',   'type' => AccountType::ASSET],
            ['code' => '1561', 'name' => 'Hàng hóa (giá vốn)',     'type' => AccountType::ASSET],

            // ===== 3xx - Nợ phải trả =====
            ['code' => '331',   'name' => 'Phải trả nhà cung cấp',    'type' => AccountType::LIABILITY],
            ['code' => '33311', 'name' => 'Thuế GTGT đầu ra',         'type' => AccountType::LIABILITY],
            ['code' => '3335',  'name' => 'Thuế TNDN phải nộp',       'type' => AccountType::LIABILITY],
            ['code' => '3387',  'name' => 'Doanh thu chưa thực hiện', 'type' => AccountType::LIABILITY],

            // ===== 4xx - Vốn CSH =====
            ['code' => '4111',  'name' => 'Vốn đầu tư của chủ sở hữu', 'type' => AccountType::EQUITY],
            ['code' => '4211',  'name' => 'Lợi nhuận chưa phân phối',   'type' => AccountType::EQUITY],

            // ===== 5xx - Doanh thu =====
            ['code' => '5111',  'name' => 'Doanh thu bán hàng hóa',     'type' => AccountType::REVENUE],
            ['code' => '5112',  'name' => 'Doanh thu bán nội bộ',       'type' => AccountType::REVENUE],
            ['code' => '515',   'name' => 'Doanh thu hoạt động tài chính', 'type' => AccountType::REVENUE],

            // ===== 6xx - Chi phí =====
            ['code' => '632',   'name' => 'Giá vốn hàng bán',           'type' => AccountType::EXPENSE],
            ['code' => '635',   'name' => 'Chi phí tài chính',          'type' => AccountType::EXPENSE],
            ['code' => '6411',  'name' => 'Chi phí bán hàng - Lương',   'type' => AccountType::EXPENSE],
            ['code' => '6412',  'name' => 'Chi phí bán hàng - Vận chuyển', 'type' => AccountType::EXPENSE],
            ['code' => '6414',  'name' => 'Chi phí bán hàng - Phí sàn', 'type' => AccountType::EXPENSE],
            ['code' => '6421',  'name' => 'Chi phí quản lý - Lương',    'type' => AccountType::EXPENSE],
            ['code' => '6422',  'name' => 'Chi phí quản lý - VPP',      'type' => AccountType::EXPENSE],
            ['code' => '6423',  'name' => 'Chi phí quản lý - Khấu hao', 'type' => AccountType::EXPENSE],
        ];

        foreach ($accounts as $row) {
            ChartOfAccount::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'currency' => 'VND',
                    'is_detail' => true,
                    'is_active' => true,
                    'show_in_reports' => true,
                ]),
            );
        }
    }
}