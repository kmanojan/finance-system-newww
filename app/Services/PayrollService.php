<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public const KINDS = ['salary' => 'Salary', 'epf' => 'EPF', 'etf' => 'ETF', 'apit' => 'APIT', 'other' => 'Other deductions & contributions'];

    private const ACCOUNTS = [
        'salary' => ['2310', 'Salaries payable', 'liability'], 'epf' => ['2320', 'EPF payable', 'liability'],
        'etf' => ['2330', 'ETF payable', 'liability'], 'apit' => ['2340', 'APIT payable', 'liability'],
        'other' => ['2350', 'Payroll other deductions payable', 'liability'],
        'advance' => ['1250', 'Employee advances', 'asset'], 'expense' => ['5210', 'Payroll expense', 'expense'],
        'cash' => ['1010', 'Cash and bank', 'asset'],
    ];

    public static function run(int $id): object
    {
        return DB::table('payroll_runs')->find($id) ?? abort(404);
    }

    public static function items(int $id)
    {
        $paid = DB::table('payroll_payment_allocations as a')->join('payroll_payments as p', 'p.id', '=', 'a.payroll_payment_id')
            ->where('p.payroll_run_id', $id)->where('p.status', 'posted')->select('a.payroll_item_id', 'p.kind')
            ->selectRaw('SUM(a.amount_cents) as total')->groupBy('a.payroll_item_id', 'p.kind')->get()->groupBy('payroll_item_id');

        return DB::table('payroll_items')->where('payroll_run_id', $id)->orderBy('id')->get()->map(function ($i) use ($paid) {
            $i->paid = ($paid[$i->id] ?? collect())->pluck('total', 'kind')->map(fn ($v) => (int) $v)->all();
            foreach (['snapshot', 'adjustments', 'calculation'] as $field) {
                $i->$field = json_decode($i->$field, true);
            }

            return $i;
        });
    }

    public static function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $month = $data['month'].'-01';
            $department = DB::table('departments')->find($data['department_id']);
            self::check($department && (int) $department->company_id === (int) $data['company_id'], 'Choose a department belonging to this company.');
            $key = $data['company_id'].':'.$month.':'.$data['currency'];
            self::check(! DB::table('payroll_runs')->where('active_key', $key)->exists(), 'This company already has a payroll for that month and currency. Open the existing payroll.');
            $employees = Employee::with('salaryComponents.component')->whereIn('id', $data['employee_ids'])->orderBy('id')->lockForUpdate()->get();
            self::check($employees->count() === count(array_unique($data['employee_ids'])), 'Choose valid employees.');
            $id = DB::table('payroll_runs')->insertGetId([
                'company_id' => $data['company_id'], 'department_id' => $data['department_id'], 'month' => $month,
                'currency' => $data['currency'], 'active_key' => $key, 'status' => 'draft',
                'salary_due_date' => $data['salary_due_date'], 'statutory_due_date' => $data['statutory_due_date'],
                'tax_due_date' => $data['tax_due_date'], 'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $scheduled = SalaryAdvanceService::scheduledTotals($employees->pluck('id')->all(), (int) $data['company_id'], $month);
            foreach ($employees as $e) {
                self::check($e->currency === $data['currency'], "{$e->full_name} has a different salary currency.");
                self::check($e->basic_salary > 0, "Set a basic salary for {$e->full_name} first.");
                self::check(! DB::table('payroll_items')->where('active_key', $e->id.':'.$month)->exists(), "{$e->full_name} already belongs to payroll for this month.");
                $s = PayrollCalculationService::snapshot($e);
                $a = ['component_amounts' => $data['component_amounts'][$e->id] ?? []];
                $proposal = SalaryAdvanceService::proposal($s, $a, $scheduled[$e->id] ?? 0);
                $a = $proposal['adjustments'];
                $c = $proposal['calculation'];
                $item = DB::table('payroll_items')->insertGetId([
                    'payroll_run_id' => $id, 'employee_id' => $e->id, 'active_key' => $e->id.':'.$month,
                    'snapshot' => json_encode($s), 'adjustments' => '{}', 'calculation' => '{}', 'created_at' => now(), 'updated_at' => now(),
                ]);
                self::saveCalculation($item, $a, $c, false);
            }
            ActivityLogService::logCreate('Payroll', $id, ['month' => $month, 'employees' => $employees->count()]);

            return $id;
        });
    }

    public static function edit(int $runId, int $itemId, array $data): void
    {
        DB::transaction(function () use ($runId, $itemId, $data) {
            $run = DB::table('payroll_runs')->where('id', $runId)->lockForUpdate()->first() ?? abort(404);
            self::check($run->status === 'draft', 'Only draft payroll can be edited. Return it to draft first.');
            $item = DB::table('payroll_items')->where('payroll_run_id', $runId)->find($itemId) ?? abort(404);
            $data['component_amounts'] ??= json_decode($item->adjustments, true)['component_amounts'] ?? [];
            $c = PayrollCalculationService::calculate(json_decode($item->snapshot, true), $data);
            $historicalAdvance = json_decode($item->snapshot, true)['historical_source']['advance'] ?? 0;
            self::check($c['advance'] <= $historicalAdvance + (SalaryAdvanceService::eligible([$item->employee_id], $run->company_id, $run->currency, $run->month)[$item->employee_id] ?? collect())->sum('remaining_cents'), 'Advance recovery exceeds the balance eligible for this payroll month.');
            self::saveCalculation($itemId, $data, $c, (bool) ($data['reviewed'] ?? false));
            ActivityLogService::logUpdate('PayrollItem', $itemId, ['adjustments' => json_decode($item->adjustments, true)], ['adjustments' => $data]);
        });
    }

    private static function saveCalculation(int $id, array $a, array $c, bool $reviewed): void
    {
        $values = ['adjustments' => json_encode($a), 'calculation' => json_encode($c), 'reviewed' => $reviewed, 'updated_at' => now()];
        foreach (['gross', 'net', 'epf', 'etf', 'apit', 'other', 'advance', 'cost'] as $field) {
            $values[$field.'_cents'] = $c[$field === 'other' ? 'other_payable' : $field];
        }
        DB::table('payroll_items')->where('id', $id)->update($values);
    }

    public static function transition(int $id, string $action, array $data = []): void
    {
        DB::transaction(function () use ($id, $action, $data) {
            $r = DB::table('payroll_runs')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            $items = self::items($id);
            $update = ['updated_at' => now()];
            if ($action === 'submit') {
                self::check($r->status === 'draft' && $items->isNotEmpty() && $items->every(fn ($i) => $i->reviewed), 'Review every employee’s salary, contribution bases and tax before submitting.');
                $update['status'] = 'review';
            } elseif ($action === 'return') {
                self::check($r->status === 'review', 'Only payroll awaiting approval can be returned.');
                $update['status'] = 'draft';
            } elseif ($action === 'approve') {
                self::check($r->status === 'review', 'Submit this payroll for review before approval.');
                self::check(! $items->contains(fn ($item) => isset($item->snapshot['historical_source'])
                    && (($item->snapshot['historical_source']['loan'] ?? 0) > 0 || ($item->snapshot['historical_source']['advance'] ?? 0) > 0)),
                    'Historical loan and salary advance deductions need opening-balance reconciliation before approval. These drafts are available for comparison and review.');
                foreach ($items as $i) {
                    Employee::whereKey($i->employee_id)->lockForUpdate()->first();
                    SalaryAdvanceService::allocate($r, $i);
                }
                $lines = [self::line('expense', $items->sum('cost_cents'), true, $r->currency)];
                foreach (array_keys(self::KINDS) as $kind) {
                    $lines[] = self::line($kind, $items->sum(self::column($kind)), false, $r->currency);
                }
                $lines[] = self::line('advance', $items->sum('advance_cents'), false, $r->currency);
                PeriodLockService::checkLockedDate(Carbon::parse($r->month)->endOfMonth()->toDateString(), $r->company_id);
                $journal = $items->sum('cost_cents') > 0 ? LedgerPostingService::postJournal(Carbon::parse($r->month)->endOfMonth()->toDateString(), 'Payroll '.$r->month, self::nonzero($lines), $id, 'Payroll', $r->company_id) : null;
                $update += ['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'journal_entry_id' => $journal?->id];
            } elseif ($action === 'void') {
                self::check(in_array($r->status, ['draft', 'review', 'approved']), 'This payroll is already void.');
                self::check(! DB::table('payroll_payments')->where('payroll_run_id', $id)->where('status', 'posted')->exists(), 'Reverse all payments before voiding this payroll.');
                $update += ['status' => 'void', 'active_key' => null, 'void_reason' => $data['reason']];
                if ($r->journal_entry_id) {
                    self::check($data['date'] >= Carbon::parse($r->month)->endOfMonth()->toDateString(), 'Reversal date cannot precede payroll posting.');
                    $latestReversal = DB::table('payroll_payments')->where('payroll_run_id', $id)->max('reversed_on');
                    self::check(! $latestReversal || $data['date'] >= $latestReversal, 'Void date must be on or after the last payment reversal.');
                    $update['reversal_journal_id'] = self::reverseJournal($r->journal_entry_id, $data['date'], 'Void payroll: '.$data['reason'], $id, $r->company_id);
                }
                DB::table('payroll_items')->where('payroll_run_id', $id)->update(['active_key' => null]);
            }
            DB::table('payroll_runs')->where('id', $id)->update($update);
            ActivityLogService::logUpdate('Payroll', $id, ['status' => $r->status], $update);
        });
    }

    public static function paid(int $itemId, string $kind): int
    {
        return (int) DB::table('payroll_payment_allocations as a')->join('payroll_payments as p', 'p.id', '=', 'a.payroll_payment_id')->where('a.payroll_item_id', $itemId)->where('p.kind', $kind)->where('p.status', 'posted')->sum('a.amount_cents');
    }

    public static function balances(object $run, $items): array
    {
        $result = [];
        foreach (self::KINDS as $kind => $label) {
            $due = (int) $items->sum(self::column($kind));
            $paid = $items->sum(fn ($i) => ($i->paid[$kind] ?? 0));
            $result[$kind] = ['label' => $label, 'due' => $due, 'paid' => $paid, 'balance' => $run->status === 'void' ? 0 : $due - $paid];
        }

        return $result;
    }

    public static function payment(int $runId, array $data): int
    {
        return DB::transaction(function () use ($runId, $data) {
            $r = DB::table('payroll_runs')->where('id', $runId)->lockForUpdate()->first() ?? abort(404);
            self::check($r->status === 'approved', 'Approve payroll before recording a payment.');
            self::check($data['paid_on'] >= Carbon::parse($r->month)->endOfMonth()->toDateString(), 'Payroll payments must be on or after month end. Record earlier salary payments as employee advances.');
            self::check(! DB::table('payroll_payments')->where('request_key', $data['request_key'])->exists(), 'This payment was already recorded. Refresh the page.');
            $items = self::items($runId);
            if (! empty($data['item_id'])) {
                $items = $items->where('id', $data['item_id']);
            }
            self::check($items->isNotEmpty(), 'Choose an employee in this payroll.');
            $remaining = PayrollCalculationService::cents($data['amount']);
            $allocations = [];
            foreach ($items as $i) {
                $balance = $i->{self::column($data['kind'])} - ($i->paid[$data['kind']] ?? 0);
                $amount = min($remaining, $balance);
                if ($amount > 0) {
                    $allocations[] = ['payroll_item_id' => $i->id, 'amount_cents' => $amount];
                }
                $remaining -= $amount;
            }
            self::check($remaining === 0 && count($allocations) > 0, 'Payment exceeds the selected outstanding balance.');
            $id = self::cashPayment($r->company_id, $r->department_id, $r->currency, $data, $runId);
            foreach ($allocations as $a) {
                DB::table('payroll_payment_allocations')->insert($a + ['payroll_payment_id' => $id]);
            }

            return $id;
        });
    }

    public static function advance(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $e = Employee::whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            self::check($e->currency === $data['currency'], 'Advance currency must match the employee salary currency.');
            SalaryAdvanceService::validateMonth($data['recovery_month'], $data['paid_on']);
            $dept = DB::table('departments')->find($data['department_id']);
            self::check($dept && (int) $dept->company_id === (int) $data['company_id'], 'Department must belong to the selected company.');
            self::check(! DB::table('payroll_payments')->where('request_key', $data['request_key'])->exists(), 'This advance has already been recorded.');

            return self::cashPayment($data['company_id'], $data['department_id'], $data['currency'], $data + ['kind' => 'advance'], null, $e->id);
        });
    }

    private static function cashPayment(int $company, int $department, string $currency, array $data, ?int $run = null, ?int $employee = null): int
    {
        $bankId = $data['bank_account_id'] ?? null;
        if ($bankId) {
            $bank = DB::table('bank_accounts')->find($bankId);
            self::check($bank && (int) $bank->company_id === $company && $bank->currency === $currency, 'Choose a bank account in the payroll company and currency.');
        }
        $amount = PayrollCalculationService::cents($data['amount']);
        $tx = self::transaction($department, $bankId, $currency, $amount, $data['paid_on'], 'expense', 'Payroll '.$data['kind'], $data['reference']);
        $j = LedgerPostingService::postJournal($data['paid_on'], 'Payroll '.$data['kind'].' • '.$data['reference'], [self::line($data['kind'], $amount, true, $currency), self::line('cash', $amount, false, $currency)], $tx, 'PayrollPayment', $company);
        $id = DB::table('payroll_payments')->insertGetId([
            'payroll_run_id' => $run, 'employee_id' => $employee, 'company_id' => $company, 'department_id' => $department,
            'currency' => $currency, 'kind' => $data['kind'], 'amount_cents' => $amount,
            'paid_on' => $data['paid_on'], 'reference' => $data['reference'], 'request_key' => $data['request_key'],
            'bank_account_id' => $bankId, 'transaction_id' => $tx, 'journal_entry_id' => $j->id,
            'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            'recovery_month' => $data['kind'] === 'advance' ? $data['recovery_month'].'-01' : null,
        ]);
        ActivityLogService::logCreate('PayrollPayment', $id, ['amount' => $amount / 100, 'kind' => $data['kind']]);

        return $id;
    }

    public static function advanceBalance(int $employee, int $company, string $currency, ?string $asOf = null): int
    {
        $issued = DB::table('payroll_payments')->where('kind', 'advance')->where('employee_id', $employee)->where('company_id', $company)->where('currency', $currency)->where('status', 'posted')->when($asOf, fn ($q) => $q->where('paid_on', '<=', $asOf))->sum('amount_cents');
        // All approved recoveries reserve this balance, including later payroll, to prevent double recovery.
        $recovered = DB::table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->where('i.employee_id', $employee)->where('r.company_id', $company)->where('r.currency', $currency)->where('r.status', 'approved')->sum('i.advance_cents');

        return max(0, (int) $issued - (int) $recovered);
    }

    public static function reversePayment(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $p = DB::table('payroll_payments')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            if ($p->payroll_run_id) {
                DB::table('payroll_runs')->where('id', $p->payroll_run_id)->lockForUpdate()->first();
            }
            self::check($p->status === 'posted', 'Payment has already been reversed.');
            self::check($data['date'] >= $p->paid_on, 'Reversal date cannot precede the payment.');
            $tx = DB::table('transactions')->find($p->transaction_id);
            self::check(! $tx->reconciled, 'Unreconcile this payment in bank reconciliation before reversing it.');
            if ($p->kind === 'advance') {
                Employee::whereKey($p->employee_id)->lockForUpdate()->first();
                self::check((int) SalaryAdvanceService::payments()->where('p.id', $p->id)->first()->recovered_cents === 0, 'This advance has been recovered by payroll. Void that payroll before reversing the advance.');
            }
            $journal = self::reverseJournal($p->journal_entry_id, $data['date'], 'Reverse payroll payment: '.$data['reason'], $id, $p->company_id);
            $reversal = self::transaction($p->department_id, $p->bank_account_id, $p->currency, $p->amount_cents, $data['date'], 'income', 'Payroll payment reversal', $p->reference);
            DB::table('payroll_payments')->where('id', $id)->update(['status' => 'reversed', 'reversed_on' => $data['date'], 'reversal_reason' => $data['reason'], 'reversal_journal_id' => $journal, 'reversal_transaction_id' => $reversal, 'updated_at' => now()]);
            ActivityLogService::logUpdate('PayrollPayment', $id, ['status' => 'posted'], ['status' => 'reversed', 'reason' => $data['reason']]);
        });
    }

    private static function transaction(int $department, ?int $bank, string $currency, int $amount, string $date, string $type, string $description, string $reference): int
    {
        $category = DB::table('categories')->where('name', 'Payroll settlements')->where('type', $type)->value('id');
        if (! $category) {
            $category = DB::table('categories')->insertGetId(['name' => 'Payroll settlements', 'type' => $type]);
        }

        return DB::table('transactions')->insertGetId([
            'department_id' => $department, 'bank_account_id' => $bank, 'category_id' => $category, 'type' => $type,
            'amount' => $amount / 100, 'currency' => $currency, 'transaction_date' => $date,
            'description' => $description, 'reference_no' => $reference, 'payment_method' => $bank ? 'bank_transfer' : 'cash',
            'reconciled' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private static function reverseJournal(int $id, string $date, string $description, int $reference, int $company): int
    {
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $id)->get()->map(fn ($l) => ['account_id' => $l->account_id, 'currency' => $l->currency, 'debit' => $l->credit, 'credit' => $l->debit])->all();

        return LedgerPostingService::postJournal($date, $description, $lines, $reference, 'PayrollReversal', $company)->id;
    }

    public static function column(string $kind): string
    {
        return ($kind === 'salary' ? 'net' : $kind).'_cents';
    }

    private static function line(string $kind, int $cents, bool $debit, string $currency): array
    {
        [$code,$name,$type] = self::ACCOUNTS[$kind];

        return ['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'currency' => $currency, 'debit' => $debit ? $cents / 100 : 0, 'credit' => $debit ? 0 : $cents / 100];
    }

    private static function nonzero(array $lines): array
    {
        return array_values(array_filter($lines, fn ($l) => $l['debit'] || $l['credit']));
    }

    private static function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['payroll' => $message]);
        }
    }
}
