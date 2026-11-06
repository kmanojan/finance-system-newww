<?php

use App\Http\Controllers\ProjectController;
use App\Jobs\SyncEmployeesJob;
use App\Models\ApiIntegration;
use App\Models\Employee;
use App\Services\EmployeePartyService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    ApiIntegration::each(fn ($integration) => SyncEmployeesJob::dispatch($integration));
})->dailyAt('02:00');

Schedule::call(function () {
    DB::transaction(fn () => ProjectController::checkActiveSchedules());
})->dailyAt('01:00')->name('generate-recurring-invoices')->withoutOverlapping();

Schedule::call(function () {
    DB::table('reminders')->where('status', 'snoozed')->where('snoozed_until', '<=', now())->update(['status' => 'pending', 'snoozed_until' => null, 'updated_at' => now()]);
})->everyMinute()->name('resume-snoozed-reminders')->withoutOverlapping();

Artisan::command('employees:sync-parties', function () {
    $created = 0;
    Employee::query()->chunkById(200, function ($employees) use (&$created) {
        foreach ($employees as $employee) {
            $missing = ! $employee->party_id;
            EmployeePartyService::sync($employee);
            $created += (int) $missing;
        }
    });
    $this->info("Employee parties synced. Created: {$created}. Linked: ".Employee::whereNotNull('party_id')->count());
})->purpose('Create missing employee parties and synchronise linked names and contacts without merging by name');
