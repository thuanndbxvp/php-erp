<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Helper tra cứu nhanh tài khoản kế toán theo mã (code).
 *
 * Có cache (60s) để giảm query khi auto-posting từ PaymentService.
 *
 * Throw exception nếu không tìm thấy để báo lỗi rõ ràng khi seed bị thiếu.
 */
class ChartOfAccountRepository
{
    /**
     * Tra cứu tài khoản theo code (VD: "1111", "131", "5111").
     */
    public function findByCode(string $code): ChartOfAccount
    {
        $account = ChartOfAccount::query()->where('code', $code)->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_account' => "Tài khoản kế toán '{$code}' chưa được khai báo. Hãy chạy ChartOfAccountsSeeder.",
            ]);
        }

        return $account;
    }

    /**
     * Lấy id nhanh (cached).
     */
    public function idByCode(string $code): int
    {
        return Cache::remember(
            'coa:code:' . $code,
            60,
            fn () => $this->findByCode($code)->id,
        );
    }

    /**
     * Trả về đường dẫn đầy đủ từ root → account (VD: "Tài sản ngắn hạn > Tiền > Tiền VND").
     */
    public function fullPath(ChartOfAccount $account): string
    {
        $parts = [$account->name];
        $current = $account->parent;

        while ($current) {
            array_unshift($parts, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $parts);
    }
}