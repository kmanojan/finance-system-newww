<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        try {
            if (Schema::hasTable('companies')) {
                $baseCurrency = DB::table('companies')->value('base_currency') ?? 'LKR';
                View::share('baseCurrency', $baseCurrency);
            }

            \Illuminate\Pagination\Paginator::defaultView('pagination.custom');

            // Auto-run pending migrations in production / serverless environment if new columns are missing
            if (Schema::hasTable('loans') && !Schema::hasColumn('loans', 'maturity_date')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            // Ignore during migrations or when DB is not available
        }
    }
}
