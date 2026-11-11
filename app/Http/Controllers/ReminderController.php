<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $types = ['invoice' => 'Invoice', 'loan' => 'Loan', 'cheque' => 'Cheque', 'milestone' => 'Milestone', 'custom' => 'Custom'];
        $viewMode = $request->query('view') === 'calendar' ? 'calendar' : 'list';
        $typeFilter = $request->query('type');
        $typeFilter = in_array($typeFilter, array_keys($types), true) ? $typeFilter : 'all';
        $statusFilter = $request->query('status');
        $statusFilter = in_array($statusFilter, ['pending', 'completed', 'snoozed', 'all'], true) ? $statusFilter : 'pending';
        $includeSystem = $statusFilter !== 'snoozed';
        $inactiveInvoices = ['paid', 'cancelled', 'draft', 'pending_approval', 'rejected'];
        $openSchedules = ['pending', 'partially_paid', 'overdue'];
        $openCheques = ['pending_deposit', 'received', 'issued'];
        $paidCheques = ['deposited', 'cleared'];
        $inactiveMilestones = ['paid', 'completed', 'skipped', 'invoiced'];
        $requestedMonth = $request->query('month');
        $requestedMonth = is_string($requestedMonth) && preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $requestedMonth) ? $requestedMonth : null;
        $search = $request->query('search');
        $search = is_string($search) ? Str::substr(trim($search), 0, 255) : '';
        $today = today();
        $todayKey = $today->toDateString();
        $weekEnd = $today->copy()->addDays(7)->toDateString();

        // System & Custom Reminders Container
        $allReminders = collect();

        // 1. Client Invoices Due Dates
        if ($includeSystem && in_array($typeFilter, ['all', 'invoice'], true) && Schema::hasTable('invoices')) {
            $invQuery = DB::table('invoices')
                ->leftJoin('parties', 'invoices.client_id', '=', 'parties.id')
                ->select('invoices.*', 'parties.name as client_name');

            if ($statusFilter === 'pending') {
                $invQuery->whereNotIn('invoices.status', $inactiveInvoices);
            } elseif ($statusFilter === 'completed') {
                $invQuery->where('invoices.status', 'paid');
            }

            $invoices = $invQuery->get();
            foreach ($invoices as $inv) {
                $rawDate = $inv->due_date ?? $inv->issue_date ?? $inv->created_at;
                if (! $rawDate) {
                    continue;
                }
                $dueDateFormatted = date('Y-m-d', strtotime($rawDate));
                $allReminders->push((object) [
                    'id' => 'invoice_'.$inv->id,
                    'title' => "Invoice #{$inv->invoice_no} (".($inv->client_name ?? 'Client').')',
                    'type' => 'invoice',
                    'due_date' => $dueDateFormatted,
                    'amount_formatted' => ($inv->currency ?? 'LKR').' '.number_format($inv->grand_total > 0 ? $inv->grand_total : $inv->amount, 2),
                    'status' => match (true) {
                        $inv->status === 'paid' => 'completed',
                        in_array($inv->status, $inactiveInvoices, true) => $inv->status,
                        default => 'pending',
                    },
                    'is_system' => true,
                    'link' => '/invoices?'.http_build_query(['search' => $inv->invoice_no]),
                ]);
            }
        }

        // 2. Loan Interest & Maturity Schedules
        if ($includeSystem && in_array($typeFilter, ['all', 'loan'], true) && Schema::hasTable('loan_interest_schedule')) {
            $schedQuery = DB::table('loan_interest_schedule')
                ->join('loans', 'loan_interest_schedule.loan_id', '=', 'loans.id')
                ->select(
                    'loan_interest_schedule.*',
                    'loans.lender_name',
                    'loans.currency',
                    'loans.principal_amount',
                    'loans.maturity_date',
                    'loans.status as loan_status'
                );

            if ($statusFilter === 'pending') {
                $schedQuery->whereIn('loan_interest_schedule.status', $openSchedules);
            } elseif ($statusFilter === 'completed') {
                $schedQuery->whereIn('loan_interest_schedule.status', [...$openSchedules, 'paid']);
            }

            $schedules = $schedQuery->get();
            $linkedPayments = DB::table('transactions')
                ->where('reference_no', 'like', 'LOAN-INT-%')
                ->where('type', 'expense')
                ->where('payment_status', 'completed')
                ->where('transaction_date', '<=', $todayKey)
                ->groupBy('reference_no', 'currency')
                ->select('reference_no', 'currency')->selectRaw('SUM(amount) as paid')
                ->get()->keyBy(fn ($payment) => $payment->reference_no.':'.$payment->currency);
            $coveredMaturities = [];

            foreach ($schedules as $s) {
                $isOpen = in_array($s->status, $openSchedules, true);
                if ($isOpen && ($s->interest_amount <= 0 || $s->loan_status === 'pending')) {
                    continue;
                }
                // The schedule may already include its linked payments. Use the larger recorded amount rather than summing them twice.
                $linkedPaid = $linkedPayments->get("LOAN-INT-{$s->loan_id}-{$s->id}:{$s->currency}")->paid ?? 0;
                $paid = max($s->paid_amount ?? 0, $linkedPaid);
                $interestDue = max(0, round($s->interest_amount - $paid, 2));
                $status = match (true) {
                    $s->status === 'paid', $isOpen && $interestDue <= 0 => 'completed',
                    $isOpen => 'pending',
                    default => $s->status ?? 'unknown',
                };
                if ($statusFilter !== 'all' && $status !== $statusFilter) {
                    continue;
                }
                $dueDateFormatted = date('Y-m-d', strtotime($s->due_date));
                $principalMaturityDue = 0;
                if ($status === 'pending' && in_array($s->loan_status, ['active', 'defaulted'], true)
                    && $s->maturity_date === $dueDateFormatted && ! isset($coveredMaturities[$s->loan_id])) {
                    $principalMaturityDue = $this->outstandingPrincipal($s->loan_id, $s->principal_amount);
                    $coveredMaturities[$s->loan_id] = true;
                }

                $totalAmountDue = ($status === 'completed' ? 0 : $interestDue) + $principalMaturityDue;
                $title = $principalMaturityDue > 0
                    ? "Loan Maturity & Interest: {$s->lender_name}"
                    : "Loan Interest: {$s->lender_name}";

                $allReminders->push((object) [
                    'id' => 'loan_'.$s->id,
                    'title' => $title,
                    'type' => 'loan',
                    'due_date' => $dueDateFormatted,
                    'amount_formatted' => $s->currency.' '.number_format($totalAmountDue, 2),
                    'status' => $status,
                    'is_system' => true,
                    'link' => "/loans/{$s->loan_id}",
                ]);
            }

            // Also check active loans for Principal Maturity Due Date if not already covered by pending schedules
            if ($statusFilter !== 'completed' && Schema::hasTable('loans')) {
                $activeLoans = DB::table('loans')
                    ->whereIn('status', ['active', 'defaulted'])
                    ->whereNotNull('maturity_date')
                    ->get();

                foreach ($activeLoans as $al) {
                    if (isset($coveredMaturities[$al->id])) {
                        continue;
                    }
                    $outstandingPrincipal = $this->outstandingPrincipal($al->id, $al->principal_amount);
                    if ($outstandingPrincipal <= 0) {
                        continue;
                    }
                    $allReminders->push((object) [
                        'id' => 'loan_mat_'.$al->id,
                        'title' => "Loan Principal Repayment: {$al->lender_name}",
                        'type' => 'loan',
                        'due_date' => date('Y-m-d', strtotime($al->maturity_date)),
                        'amount_formatted' => $al->currency.' '.number_format($outstandingPrincipal, 2),
                        'status' => 'pending',
                        'is_system' => true,
                        'link' => "/loans/{$al->id}",
                    ]);
                }
            }
        }

        // 3. Cheque Deposit / Clearance Dates
        if ($includeSystem && in_array($typeFilter, ['all', 'cheque'], true) && Schema::hasTable('cheques')) {
            $cheqQuery = DB::table('cheques');
            if ($statusFilter === 'pending') {
                $cheqQuery->whereIn('status', $openCheques);
            } elseif ($statusFilter === 'completed') {
                $cheqQuery->whereIn('status', $paidCheques);
            }
            $cheques = $cheqQuery->get();
            foreach ($cheques as $c) {
                $cDate = date('Y-m-d', strtotime($c->cheque_date ?? $c->created_at));
                $allReminders->push((object) [
                    'id' => 'cheque_'.$c->id,
                    'title' => "Cheque #{$c->cheque_number} ({$c->bank_name})",
                    'type' => 'cheque',
                    'due_date' => $cDate,
                    'amount_formatted' => $c->currency.' '.number_format($c->amount, 2),
                    'status' => match (true) {
                        in_array($c->status, $paidCheques, true) => 'completed',
                        in_array($c->status, $openCheques, true) => 'pending',
                        default => $c->status ?? 'unknown',
                    },
                    'is_system' => true,
                    'link' => '/cheques',
                ]);
            }
        }

        // 4. Payment Milestones
        if ($includeSystem && in_array($typeFilter, ['all', 'milestone'], true) && Schema::hasTable('payment_milestones')) {
            $pmQuery = DB::table('payment_milestones')
                ->leftJoin('projects', 'payment_milestones.project_id', '=', 'projects.id')
                ->select('payment_milestones.*', 'projects.name as project_name', 'projects.currency');

            if ($statusFilter === 'pending') {
                $pmQuery->whereNotIn('payment_milestones.status', $inactiveMilestones);
            } elseif ($statusFilter === 'completed') {
                $pmQuery->whereIn('payment_milestones.status', ['paid', 'completed']);
            }

            $milestones = $pmQuery->get();
            foreach ($milestones as $m) {
                $rawDate = $m->due_date ?? $m->created_at;
                if (! $rawDate) {
                    continue;
                }
                $dueDateFormatted = date('Y-m-d', strtotime($rawDate));
                $allReminders->push((object) [
                    'id' => 'milestone_'.$m->id,
                    'title' => "Payment Milestone: {$m->name}".($m->project_name ? " ({$m->project_name})" : ''),
                    'type' => 'milestone',
                    'due_date' => $dueDateFormatted,
                    'amount_formatted' => ($m->currency ?? 'LKR').' '.number_format($m->amount, 2),
                    'status' => match (true) {
                        in_array($m->status, ['paid', 'completed'], true) => 'completed',
                        in_array($m->status, $inactiveMilestones, true) => $m->status,
                        default => 'pending',
                    },
                    'is_system' => true,
                    'link' => $m->project_id ? "/projects/{$m->project_id}#milestones" : '/projects',
                ]);
            }
        }

        if (in_array($typeFilter, ['all', 'custom'], true)) {
            foreach ($this->manualReminders()->get() as $reminder) {
                $status = $reminder->status === 'settled' ? 'completed' : ($reminder->status ?: 'unknown');
                $snoozedUntil = $reminder->snoozed_until ? Carbon::parse($reminder->snoozed_until) : null;
                if ($status === 'snoozed' && $snoozedUntil?->isPast()) {
                    $status = 'pending';
                }
                if ($statusFilter !== 'all' && $status !== $statusFilter) {
                    continue;
                }

                $allReminders->push((object) [
                    'id' => 'custom_'.$reminder->id,
                    'manual_id' => $reminder->id,
                    'title' => $reminder->title,
                    'type' => 'custom',
                    'due_date' => date('Y-m-d', strtotime($reminder->due_date)),
                    'amount_formatted' => '-',
                    'status' => $status,
                    'is_system' => false,
                    'link' => null,
                    'notes' => $reminder->notes,
                    'snoozed_until' => $status === 'snoozed' ? $snoozedUntil : null,
                ]);
            }
        }

        foreach ($allReminders as $reminder) {
            $reminder->group = match (true) {
                $reminder->status !== 'pending' => $reminder->status,
                $reminder->due_date < $todayKey => 'overdue',
                $reminder->due_date === $todayKey => 'today',
                default => 'upcoming',
            };
        }

        if ($search !== '') {
            $allReminders = $allReminders->filter(fn ($reminder) => Str::contains(implode(' ', [
                $reminder->title, $types[$reminder->type], $reminder->notes ?? null,
                $reminder->due_date, $reminder->amount_formatted, str_replace(',', '', $reminder->amount_formatted),
                $reminder->group, $reminder->status,
            ]), $search, ignoreCase: true));
        }
        $allReminders = $allReminders->sortBy('due_date')->values();

        $priority = [
            'overdue' => $allReminders->where('group', 'overdue')->count(),
            'today' => $allReminders->where('group', 'today')->count(),
            'week' => $allReminders->where('group', 'upcoming')->where('due_date', '<=', $weekEnd)->count(),
        ];
        $window = $request->query('when');
        $window = $viewMode === 'list' && in_array($statusFilter, ['pending', 'all'], true)
            && in_array($window, array_keys($priority), true) ? $window : null;
        $listReminders = match ($window) {
            'overdue', 'today' => $allReminders->where('group', $window),
            'week' => $allReminders->where('group', 'upcoming')->where('due_date', '<=', $weekEnd),
            default => $allReminders,
        };

        $defaultMonth = $today->format('Y-m');
        if (! $requestedMonth && $allReminders->isNotEmpty()
            && $allReminders->doesntContain(fn ($reminder) => str_starts_with($reminder->due_date, $defaultMonth))) {
            $firstEvent = $allReminders->first(fn ($reminder) => $reminder->due_date > $todayKey) ?? $allReminders->first();
            $defaultMonth = substr($firstEvent->due_date, 0, 7);
        }

        $carbonMonth = Carbon::parse(($requestedMonth ?? $defaultMonth).'-01');
        $monthKey = $carbonMonth->format('Y-m');
        $prevMonth = $carbonMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $carbonMonth->copy()->addMonth()->format('Y-m');
        $monthTitle = $carbonMonth->format('F Y');
        $monthReminders = $allReminders->filter(fn ($reminder) => str_starts_with($reminder->due_date, $monthKey));
        $monthDays = [];
        $daysByDate = $monthReminders->groupBy('due_date');
        for ($day = 1; $day <= $carbonMonth->daysInMonth; $day++) {
            $date = $carbonMonth->copy()->day($day);
            $monthDays[$date->toDateString()] = [
                'date' => $date,
                'items' => $daysByDate->get($date->toDateString(), collect()),
            ];
        }
        $dateFilter = $request->query('date');
        $selectedDate = match (true) {
            is_string($dateFilter) && isset($monthDays[$dateFilter]) => $dateFilter,
            isset($monthDays[$todayKey]) => $todayKey,
            default => $monthReminders->first()?->due_date ?? array_key_first($monthDays),
        };

        return view('reminders', compact(
            'listReminders', 'viewMode', 'types', 'typeFilter', 'statusFilter', 'search',
            'todayKey', 'priority', 'window', 'monthKey', 'prevMonth', 'nextMonth', 'monthTitle',
            'carbonMonth', 'monthReminders', 'monthDays', 'daysByDate', 'selectedDate'
        ));
    }

    private function outstandingPrincipal(int $loanId, float $principal): float
    {
        $movement = DB::table('loan_principal_records')->where('loan_id', $loanId)
            ->selectRaw("COALESCE(SUM(CASE record_type WHEN 'draw' THEN amount WHEN 'repayment' THEN -amount ELSE 0 END), 0) as net_movement")
            ->first()->net_movement;

        return max(0, round($principal + $movement, 2));
    }

    private function manualReminders(): Builder
    {
        return DB::table('reminders')->where(function ($query) {
            $query->whereNull('reference_type')
                ->orWhere('reference_type', '!=', 'Loan')
                ->orWhereNull('reference_id');
        });
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'due_date' => 'required|date',
            'notify_before_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::table('reminders')->insert([
            'title' => $validated['title'],
            'type' => $validated['type'] ?? 'custom',
            'due_date' => $validated['due_date'],
            'notify_before_days' => $validated['notify_before_days'] ?? 2,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Reminder created successfully!');
    }

    public function updateStatus(Request $request, $id)
    {

        $request->validate(['status' => 'required|in:pending,completed,settled,snoozed', 'snoozed_until' => 'nullable|date|after:now']);
        $status = $request->input('status');

        abort_unless(ctype_digit((string) $id), 404);
        $reminder = $this->manualReminders()->where('id', $id);
        abort_unless($reminder->exists(), 404);

        $reminder->update([
            'status' => $status,
            'snoozed_until' => $status === 'snoozed' ? ($request->input('snoozed_until') ?: now()->addDay()) : null,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Reminder updated successfully!');
    }

    public function destroy($id)
    {
        abort_unless(ctype_digit((string) $id), 404);
        abort_unless($this->manualReminders()->where('id', $id)->delete(), 404);

        return back()->with('success', 'Reminder deleted!');
    }
}
