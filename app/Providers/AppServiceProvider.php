<?php

namespace App\Providers;

use App\Models\SalesOrder;
use App\Observers\SalesOrderObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Wire SO observer — khi SO chuyển sang SHIPPED/COMPLETED → auto-tạo Commission
        SalesOrder::observe(SalesOrderObserver::class);
    }
}
