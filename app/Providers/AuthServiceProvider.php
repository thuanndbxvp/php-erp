<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AuthServiceProvider — đăng ký HR Gates và Policies.
 *
 * Dùng plain ServiceProvider (không extend Laravel AuthServiceProvider)
 * vì L11 đã bỏ $policies property.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any authentication / authorization services.
     */
    public function boot(): void
    {
        // Register policies
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Employee::class, \App\Policies\EmployeePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Attendance::class, \App\Policies\AttendancePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Leave::class, \App\Policies\LeavePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\PayrollRun::class, \App\Policies\PayrollRunPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Payslip::class, \App\Policies\PayslipPolicy::class);

        // Super Admin bypasses all Gates (không cần gán hết 88 permissions)
        \Illuminate\Support\Facades\Gate::before(function (\App\Models\User $user, string $ability) {
            if ($user->hasRole('Super Admin', 'web')) {
                return true;
            }

            return null;
        });
    }
}
