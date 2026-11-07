<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Central role boundary; controller validation still governs individual records. */
class AuthorizeWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Your account is inactive. Contact an administrator.');
        }
        $params = $request->route()->parameters();
        if ($request->is('projects/*')) {
            $projectId = $params['projectId'] ?? $params['id'] ?? null;
            foreach (['scheduleId' => 'invoice_schedules', 'invoiceId' => 'invoices', 'milestoneId' => 'payment_milestones', 'crId' => 'change_requests', 'commId' => 'project_commissions'] as $key => $table) {
                if (isset($params[$key])) {
                    abort_unless(DB::table($table)->where('id', $params[$key])->where('project_id', $projectId)->exists(), 404);
                }
            }
        }
        if ($request->is('loans/*') && isset($params['scheduleId'])) {
            abort_unless(DB::table('loan_interest_schedule')->where('id', $params['scheduleId'])->where('loan_id', $params['id'])->exists(), 404);
        }
        if ($user->isAdmin()) {
            return $next($request);
        }
        $role = strtolower($user->role ?? 'viewer');
        if ($request->is('payroll', 'payroll/*', 'employees', 'employees/*', 'api/employees')) {
            abort_unless(in_array($role, ['manager', 'accountant']), 403, 'Payroll and salary records are restricted to authorised finance staff.');
        }
        $profile = $request->is('profile', 'profile/*');
        $adminOnly = $request->is('master/users', 'master/users/*', 'settings/*', 'api/api-integrations*', 'employee-integrations*', 'activity-logs');
        abort_if($adminOnly, 403, 'Administrator access is required.');
        if (! $request->isMethodSafe() && ! $profile) {
            abort_unless(in_array($role, ['manager', 'accountant']), 403, 'Your role has read-only access.');
            abort_if($request->is('master/*'), 403, 'Only administrators can change master data.');
            abort_if(($request->is('approvals/*') || str_ends_with($request->path(), '/approve') || str_ends_with($request->path(), '/reject')) && $role !== 'manager', 403, 'Manager approval is required.');
        }

        return $next($request);
    }
}
