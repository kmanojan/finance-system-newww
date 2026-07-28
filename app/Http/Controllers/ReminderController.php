<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status', 'pending');

        // Custom Reminders
        $customQuery = DB::table('reminders');
        if ($statusFilter) {
            $customQuery->where('status', $statusFilter);
        }
        if ($typeFilter && $typeFilter !== 'all') {
            $customQuery->where('type', $typeFilter);
        }
        $customReminders = $customQuery->orderBy('due_date', 'asc')->get();

        // System Generated Reminders:
        $systemReminders = collect();

        // 1. Cheque Deposits Pending
        if (Schema::hasTable('cheques')) {
            $cheques = DB::table('cheques')->where('status', 'pending_deposit')->get();
            foreach ($cheques as $c) {
                $systemReminders->push((object)[
                    'id' => 'cheque_' . $c->id,
                    'title' => "Deposit Cheque #{$c->cheque_number} ({$c->bank_name})",
                    'type' => 'cheque',
                    'due_date' => $c->cheque_date,
                    'notify_before_days' => 2,
                    'amount_formatted' => $c->currency . ' ' . number_format($c->amount, 2),
                    'status' => 'pending',
                    'is_system' => true,
                    'link' => '/cheques',
                ]);
            }
        }

        // 2. Loan Interest Due
        if (Schema::hasTable('loan_interest_schedules')) {
            $schedules = DB::table('loan_interest_schedules')
                ->join('loans', 'loan_interest_schedules.loan_id', '=', 'loans.id')
                ->where('loan_interest_schedules.status', 'pending')
                ->select('loan_interest_schedules.*', 'loans.lender_name', 'loans.currency')
                ->get();

            foreach ($schedules as $s) {
                $systemReminders->push((object)[
                    'id' => 'loan_sched_' . $s->id,
                    'title' => "Loan Interest Due: {$s->lender_name} (" . date('M Y', strtotime($s->period_date)) . ")",
                    'type' => 'loan',
                    'due_date' => $s->period_date,
                    'notify_before_days' => 3,
                    'amount_formatted' => $s->currency . ' ' . number_format($s->interest_amount, 2),
                    'status' => 'pending',
                    'is_system' => true,
                    'link' => "/loans/{$s->loan_id}",
                ]);
            }
        }

        // 3. Invoice Schedules (Recurring)
        if (Schema::hasTable('project_invoice_schedules')) {
            $invScheds = DB::table('project_invoice_schedules')
                ->join('projects', 'project_invoice_schedules.project_id', '=', 'projects.id')
                ->where('project_invoice_schedules.status', 'active')
                ->whereNotNull('project_invoice_schedules.next_run_date')
                ->select('project_invoice_schedules.*', 'projects.name as project_name', 'projects.currency')
                ->get();

            foreach ($invScheds as $is) {
                $systemReminders->push((object)[
                    'id' => 'inv_sched_' . $is->id,
                    'title' => "Recurring Invoice Generation: {$is->title} ({$is->project_name})",
                    'type' => 'invoice_schedule',
                    'due_date' => $is->next_run_date,
                    'notify_before_days' => 2,
                    'amount_formatted' => $is->currency . ' ' . number_format($is->amount, 2),
                    'status' => 'pending',
                    'is_system' => true,
                    'link' => "/projects/{$is->project_id}",
                ]);
            }
        }

        // Merge Custom + System
        $allReminders = $customReminders->map(function($r) {
            $r->is_system = false;
            $r->amount_formatted = '-';
            $r->link = null;
            return $r;
        })->concat($systemReminders);

        if ($typeFilter && $typeFilter !== 'all') {
            $allReminders = $allReminders->where('type', $typeFilter);
        }

        $allReminders = $allReminders->sortBy('due_date')->values();

        return view('reminders', compact('allReminders', 'viewMode', 'typeFilter', 'statusFilter'));
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

        $status = $request->input('status', 'settled'); // 'settled', 'snoozed', 'dismissed'

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
