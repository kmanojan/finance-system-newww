<?php

namespace App\Providers;

use App\Filesystem\DatabaseDocumentAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

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
        Storage::extend('database-documents', function ($app, $config) {
            $adapter = new DatabaseDocumentAdapter;

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
        View::share('baseCurrency', 'LKR');
        try {
            if (Schema::hasTable('companies')) {
                $baseCurrency = DB::table('companies')->value('base_currency') ?? 'LKR';
                View::share('baseCurrency', $baseCurrency);
            }

            Paginator::defaultView('pagination.custom');

            View::composer('*', function ($view) {
                try {
                    $today = date('Y-m-d');
                    $maxAdvanceWindow = date('Y-m-d', strtotime('+7 days'));
                    $hasReminders = false;

                    // 1. Custom Reminders: due today, overdue, or within their notify_before_days window
                    if (Schema::hasTable('reminders')) {
                        $pendingReminders = DB::table('reminders')
                            ->where('status', 'pending')
                            ->where('due_date', '<=', $maxAdvanceWindow)
                            ->get(['due_date', 'notify_before_days']);

                        foreach ($pendingReminders as $r) {
                            $notifyDays = (int) ($r->notify_before_days ?? 2);
                            $notifyStartDate = date('Y-m-d', strtotime($r->due_date." -{$notifyDays} days"));
                            if ($today >= $notifyStartDate) {
                                $hasReminders = true;
                                break;
                            }
                        }
                    }

                    // 2. Loan Interest Schedules: due today or overdue
                    if (! $hasReminders && Schema::hasTable('loan_interest_schedule')) {
                        $hasReminders = DB::table('loan_interest_schedule')
                            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                            ->where('due_date', '<=', $today)
                            ->exists();
                    }

                    // 3. Client Invoices: due today or overdue
                    if (! $hasReminders && Schema::hasTable('invoices')) {
                        $hasReminders = DB::table('invoices')
                            ->whereNotIn('status', ['paid', 'cancelled'])
                            ->whereNotNull('due_date')
                            ->where('due_date', '<=', $today)
                            ->exists();
                    }

                    // 4. Payment Milestones: due today or overdue
                    if (! $hasReminders && Schema::hasTable('payment_milestones')) {
                        $hasReminders = DB::table('payment_milestones')
                            ->whereNotIn('status', ['paid', 'completed', 'skipped', 'invoiced'])
                            ->whereNotNull('due_date')
                            ->where('due_date', '<=', $today)
                            ->exists();
                    }

                    // 5. Cheques: due today or overdue
                    if (! $hasReminders && Schema::hasTable('cheques')) {
                        $hasReminders = DB::table('cheques')
                            ->whereIn('status', ['pending_deposit', 'received', 'issued'])
                            ->whereNotNull('cheque_date')
                            ->where('cheque_date', '<=', $today)
                            ->exists();
                    }

                    $view->with('hasActiveReminders', $hasReminders);
                } catch (\Throwable $e) {
                    $view->with('hasActiveReminders', false);
                }
            });

        } catch (\Throwable $e) {
            // Ignore during migrations or when DB is not available
        }
    }
}
