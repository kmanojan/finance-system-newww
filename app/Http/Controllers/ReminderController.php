<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ReminderController extends Controller
{
    private function ensureTableExists()
    {
        if (!Schema::hasTable('reminders')) {
            Schema::create('reminders', function ($table) {
                $table->id();
                $table->string('title');
                $table->string('type', 50)->default('custom');
                $table->date('due_date');
                $table->integer('notify_before_days')->default(2);
                $table->string('linked_type', 100)->nullable();
                $table->integer('linked_id')->nullable();
                $table->string('status', 50)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function index(Request $request)
    {
        $this->ensureTableExists();

        $viewMode = $request->query('view', 'list'); // 'list' or 'calendar'
        $typeFilter = $request->query('type', 'all');
        $statusFilter = $request->query('status', 'pending');
        $requestedMonth = $request->query('month');


        // System & Custom Reminders Container
        $allReminders = collect();

        // 1. Client Invoices Due Dates
        if (Schema::hasTable('invoices')) {
            $invQuery = DB::table('invoices')
                ->leftJoin('parties', 'invoices.client_id', '=', 'parties.id')
                ->select('invoices.*', 'parties.name as client_name');

            if ($statusFilter === 'pending') {
                $invQuery->whereNotIn('invoices.status', ['paid', 'cancelled']);
            } elseif ($statusFilter === 'completed') {
                $invQuery->where('invoices.status', 'paid');
            }

            $invoices = $invQuery->get();
            foreach ($invoices as $inv) {
                $rawDate = $inv->due_date ?? $inv->issue_date ?? $inv->created_at;
                if (!$rawDate) continue;
                $dueDateFormatted = date('Y-m-d', strtotime($rawDate));
                $allReminders->push((object)[
                    'id' => 'invoice_' . $inv->id,
                    'title' => "Invoice #{$inv->invoice_no} (" . ($inv->client_name ?? 'Client') . ")",
                    'type' => 'invoice',
                    'due_date' => $dueDateFormatted,
                    'amount_formatted' => ($inv->currency ?? 'LKR') . ' ' . number_format($inv->grand_total > 0 ? $inv->grand_total : $inv->amount, 2),
                    'status' => $inv->status === 'paid' ? 'completed' : 'pending',
                    'is_system' => true,
                    'link' => '/invoices',
                ]);
            }
        }

        // 2. Loan Interest & Maturity Schedules
        if (Schema::hasTable('loan_interest_schedule')) {
            $schedQuery = DB::table('loan_interest_schedule')
                ->join('loans', 'loan_interest_schedule.loan_id', '=', 'loans.id')
                ->select(
                    'loan_interest_schedule.*', 
                    'loans.lender_name', 
                    'loans.currency',
                    'loans.principal_amount',
                    'loans.status as loan_status'
                );

            if ($statusFilter === 'pending') {
                $schedQuery->whereIn('loan_interest_schedule.status', ['pending', 'partially_paid', 'overdue']);
            } elseif ($statusFilter === 'completed') {
                $schedQuery->where('loan_interest_schedule.status', 'paid');
            }

            $schedules = $schedQuery->get();

            // Find maximum schedule ID for each loan to identify final maturity period
            $maxScheduleIds = DB::table('loan_interest_schedule')
                ->select('loan_id', DB::raw('MAX(id) as max_id'))
                ->groupBy('loan_id')
                ->pluck('max_id', 'loan_id')
                ->toArray();

            foreach ($schedules as $s) {
                $dueDateFormatted = date('Y-m-d', strtotime($s->due_date));
                $isMaturitySchedule = isset($maxScheduleIds[$s->loan_id]) && ($maxScheduleIds[$s->loan_id] == $s->id);

                $interestDue = max(0, $s->interest_amount - ($s->paid_amount ?? 0));
                $principalMaturityDue = 0;

                if ($isMaturitySchedule) {
                    $repayments = DB::table('loan_principal_records')
                        ->where('loan_id', $s->loan_id)
                        ->where('record_type', 'repayment')
                        ->sum('amount');
                    $draws = DB::table('loan_principal_records')
                        ->where('loan_id', $s->loan_id)
                        ->where('record_type', 'draw')
                        ->sum('amount');

                    $outstandingPrincipal = max(0, $s->principal_amount + $draws - $repayments);
                    $principalMaturityDue = $outstandingPrincipal;
                }

                $totalAmountDue = $interestDue + $principalMaturityDue;
                $title = ($isMaturitySchedule && $principalMaturityDue > 0)
                    ? "Loan Maturity & Interest: {$s->lender_name}"
                    : "Loan Interest: {$s->lender_name}";

                $allReminders->push((object)[
                    'id' => 'loan_' . $s->id,
                    'title' => $title,
                    'type' => 'loan',
                    'due_date' => $dueDateFormatted,
                    'amount_formatted' => $s->currency . ' ' . number_format($totalAmountDue, 2),
                    'status' => $s->status === 'paid' ? 'completed' : 'pending',
                    'is_system' => true,
                    'link' => "/loans/{$s->loan_id}",
                ]);
            }

            // Also check active loans for Principal Maturity Due Date if not already covered by pending schedules
            if (Schema::hasTable('loans')) {
                $activeLoans = DB::table('loans')->where('status', 'active')->whereNotNull('maturity_date')->get();
                foreach ($activeLoans as $al) {
                    $hasPendingSched = DB::table('loan_interest_schedule')
                        ->where('loan_id', $al->id)
                        ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                        ->exists();

                    if (!$hasPendingSched) {
                        $repayments = DB::table('loan_principal_records')
                            ->where('loan_id', $al->id)
                            ->where('record_type', 'repayment')
                            ->sum('amount');
                        $draws = DB::table('loan_principal_records')
                            ->where('loan_id', $al->id)
                            ->where('record_type', 'draw')
                            ->sum('amount');
                        $outstandingPrincipal = max(0, $al->principal_amount + $draws - $repayments);

                        if ($outstandingPrincipal > 0) {
                            $allReminders->push((object)[
                                'id' => 'loan_mat_' . $al->id,
                                'title' => "Loan Principal Repayment: {$al->lender_name}",
                                'type' => 'loan',
                                'due_date' => date('Y-m-d', strtotime($al->maturity_date)),
                                'amount_formatted' => $al->currency . ' ' . number_format($outstandingPrincipal, 2),
                                'status' => 'pending',
                                'is_system' => true,
                                'link' => "/loans/{$al->id}",
                            ]);
                        }
                    }
                }
            }
        }

        // 3. Cheque Deposit / Clearance Dates
        if (Schema::hasTable('cheques')) {
            $cheqQuery = DB::table('cheques');
            if ($statusFilter === 'pending') {
                $cheqQuery->whereIn('status', ['pending_deposit', 'received', 'issued']);
            } elseif ($statusFilter === 'completed') {
                $cheqQuery->whereIn('status', ['deposited', 'cleared']);
            }
            $cheques = $cheqQuery->get();
            foreach ($cheques as $c) {
                $cDate = date('Y-m-d', strtotime($c->cheque_date ?? $c->created_at));
                $allReminders->push((object)[
                    'id' => 'cheque_' . $c->id,
                    'title' => "Cheque #{$c->cheque_number} ({$c->bank_name})",
                    'type' => 'cheque',
                    'due_date' => $cDate,
                    'amount_formatted' => $c->currency . ' ' . number_format($c->amount, 2),
                    'status' => in_array($c->status, ['deposited', 'cleared']) ? 'completed' : 'pending',
                    'is_system' => true,
                    'link' => '/cheques',
                ]);
            }
        }

        // 4. Payment Milestones
        if (Schema::hasTable('payment_milestones')) {
            $pmQuery = DB::table('payment_milestones')
                ->leftJoin('projects', 'payment_milestones.project_id', '=', 'projects.id')
                ->select('payment_milestones.*', 'projects.name as project_name', 'projects.currency');

            if ($statusFilter === 'pending') {
                $pmQuery->whereNotIn('payment_milestones.status', ['paid', 'completed']);
            } elseif ($statusFilter === 'completed') {
                $pmQuery->whereIn('payment_milestones.status', ['paid', 'completed']);
            }

            $milestones = $pmQuery->get();
            foreach ($milestones as $m) {
                $rawDate = $m->due_date ?? $m->created_at;
                if (!$rawDate) continue;
                $dueDateFormatted = date('Y-m-d', strtotime($rawDate));
                $allReminders->push((object)[
                    'id' => 'milestone_' . $m->id,
                    'title' => "Payment Milestone: {$m->name}" . ($m->project_name ? " ({$m->project_name})" : ''),
                    'type' => 'milestone',
                    'due_date' => $dueDateFormatted,
                    'amount_formatted' => ($m->currency ?? 'LKR') . ' ' . number_format($m->amount, 2),
                    'status' => in_array($m->status, ['paid', 'completed']) ? 'completed' : 'pending',
                    'is_system' => true,
                    'link' => $m->project_id ? "/projects/{$m->project_id}#milestones" : "/projects",
                ]);
            }
        }

        // 5. Custom Reminders
        $custQuery = DB::table('reminders');
        if ($statusFilter && $statusFilter !== 'all') {
            $custQuery->where('status', $statusFilter);
        }
        $customs = $custQuery->get();
        foreach ($customs as $cm) {
            $allReminders->push((object)[
                'id' => 'custom_' . $cm->id,
                'title' => $cm->title,
                'type' => 'custom',
                'due_date' => date('Y-m-d', strtotime($cm->due_date)),
                'amount_formatted' => '-',
                'status' => $cm->status,
                'is_system' => false,
                'link' => null,
                'notes' => $cm->notes,
            ]);
        }


        // Apply Type Filter
        if ($typeFilter && $typeFilter !== 'all') {
            $allReminders = $allReminders->where('type', $typeFilter);
        }

        $allReminders = $allReminders->sortBy('due_date')->values();

        // Resolve active calendar month
        $defaultMonth = date('Y-m');
        if (!$requestedMonth) {
            $currentMonthEvents = $allReminders->filter(function($r) use ($defaultMonth) {
                return str_starts_with($r->due_date, $defaultMonth);
            });
            if ($currentMonthEvents->isEmpty() && $allReminders->isNotEmpty()) {
                // Auto jump to month of first upcoming reminder
                $firstUpcoming = $allReminders->first(function($r) {
                    return strtotime($r->due_date) >= strtotime(date('Y-m-01'));
                }) ?? $allReminders->first();
                
                if ($firstUpcoming) {
                    $defaultMonth = date('Y-m', strtotime($firstUpcoming->due_date));
                }
            }
        }

        $monthParam = $requestedMonth ?: $defaultMonth;
        try {
            $carbonMonth = Carbon::parse($monthParam . '-01');
        } catch (\Exception $e) {
            $carbonMonth = Carbon::parse(date('Y-m-01'));
        }

        $year = (int)$carbonMonth->format('Y');
        $month = (int)$carbonMonth->format('m');
        $monthKey = $carbonMonth->format('Y-m');
        $prevMonth = $carbonMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $carbonMonth->copy()->addMonth()->format('Y-m');
        $monthTitle = $carbonMonth->format('F Y');


        return view('reminders', compact(
            'allReminders',
            'viewMode',
            'typeFilter',
            'statusFilter',
            'monthKey',
            'prevMonth',
            'nextMonth',
            'monthTitle',
            'year',
            'month',
            'carbonMonth'
        ));
    }

    public function store(Request $request)
    {
        $this->ensureTableExists();

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
        $this->ensureTableExists();

        $status = $request->input('status', 'settled');

        DB::table('reminders')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Reminder updated successfully!');
    }

    public function destroy($id)
    {
        $this->ensureTableExists();
        DB::table('reminders')->where('id', $id)->delete();
        return back()->with('success', 'Reminder deleted!');
    }
}
