<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** A read-only, dated subledger schedule. Amounts stay in original currency, in cents. */
class AuditPayablesService
{
    public static function build(string $asOf, bool $remainingOnly = false): array
    {
        return DB::transaction(function () use ($asOf, $remainingOnly) {
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }

            $report = self::snapshot($asOf);
            $report['remainingOnly'] = $remainingOnly;
            if ($remainingOnly) {
                // Filter after dated reconciliation: current status alone cannot prove settlement.
                $report['loans'] = array_values(array_filter($report['loans'], fn ($row) => $row['included'] && $row['balance'] + $row['futureInterest'] + $row['unscheduledInterest'] > 0));
                $loanIds = array_column($report['loans'], 'id');
                $report['interestSchedule'] = array_values(array_filter($report['interestSchedule'], fn ($row) => in_array($row['loan_id'], $loanIds) && $row['balance'] > 0 && $row['classification'] !== 'Skipped / review waiver'));
                foreach (['payrollMonths', 'salaries', 'statutory'] as $section) {
                    $report[$section] = array_values(array_filter($report[$section], fn ($row) => $row['balance'] > 0));
                }
                $report['loanActivity'] = $report['payments'] = $report['salaryPayments'] = [];
            }

            return $report;
        });
    }

    private static function snapshot(string $asOf): array
    {
        $loans = $loanActivity = $interestSchedule = $salaries = $payrollMonths = $statutory = $payments = $salaryPayments = $drafts = $exceptions = $coverage = [];
        $summary = [];
        $add = function (string $currency, string $field, int $amount) use (&$summary) {
            $summary[$currency] ??= array_fill_keys(['principal', 'interest_due', 'future_interest', 'unscheduled_interest', 'salary', 'epf', 'etf', 'apit', 'other', 'total'], 0);
            $summary[$currency][$field] += $amount;
        };
        $issue = function (string $source, string $detail) use (&$exceptions) {
            $exceptions[] = compact('source', 'detail');
        };
        $records = DB::table('loan_principal_records')->orderBy('record_date')->orderBy('id')->get()->groupBy('loan_id');
        $schedules = DB::table('loan_interest_schedule')->orderBy('due_date')->orderBy('id')->get()->groupBy('loan_id');
        $interestTransactions = DB::table('transactions')->where('type', 'expense')->where('reference_no', 'like', 'LOAN-INT-%')
            ->where('payment_status', 'completed')->orderBy('transaction_date')->orderBy('id')->get()->groupBy('reference_no');
        foreach (DB::table('loans')->orderBy('claimed_date')->orderBy('id')->get() as $loan) {
            $date = self::date($loan->claimed_date ?: $loan->start_date);
            if ($date && $date > $asOf) {
                continue;
            }
            $code = ($loan->loan_code ?: 'LN-'.$loan->id).' (#'.$loan->id.')';
            $currency = $loan->currency;
            $included = (bool) $date && $loan->status !== 'pending';
            $reason = ! $date ? 'Borrowing date missing; excluded from totals' : ($loan->status === 'pending' ? 'Not activated; excluded from totals' : '');
            if ($reason) {
                $issue($code, $reason.'. Review the source loan.');
            }
            $original = self::cents($loan->principal_amount);
            $borrowed = $original;
            $repaid = $paidInterest = $dueInterest = $futureInterest = $unscheduledInterest = 0;
            if ($included) {
                $loanActivity[] = ['loan_id' => $loan->id, 'code' => $code, 'lender' => $loan->lender_name, 'currency' => $currency,
                    'date' => $date, 'kind' => 'Original principal', 'amount' => $original, 'reference' => '',
                    'notes' => ! empty($loan->is_upfront_interest) ? 'Gross principal; upfront interest may be withheld from cash received.' : ''];
            }
            foreach ($records->get($loan->id, collect()) as $record) {
                $record->record_date = self::date($record->record_date);
                if (! $record->record_date) {
                    $issue($code.' / principal record '.$record->id, 'Payment/draw date missing. Amount excluded from dated balances.');

                    continue;
                }
                if ($record->record_date > $asOf || ! $included) {
                    continue;
                }
                $amount = self::cents($record->amount);
                if ($record->record_type === 'draw') {
                    $borrowed += $amount;
                } elseif ($record->record_type === 'repayment') {
                    $repaid += $amount;
                } else {
                    $issue($code.' / principal record '.$record->id, 'Unknown principal record type; excluded.');

                    continue;
                }
                $loanActivity[] = ['loan_id' => $loan->id, 'code' => $code, 'lender' => $loan->lender_name, 'currency' => $currency,
                    'date' => $record->record_date, 'kind' => $record->record_type === 'draw' ? 'Additional borrowing' : 'Principal repayment',
                    'amount' => $amount, 'reference' => $record->reference_no, 'notes' => $record->notes];
            }
            foreach ($schedules->get($loan->id, collect()) as $period) {
                $period->due_date = self::date($period->due_date);
                $period->paid_date = self::date($period->paid_date);
                if (! $included) {
                    continue;
                }
                $transactions = $interestTransactions->get("LOAN-INT-{$loan->id}-{$period->id}", collect());
                $paid = 0;
                foreach ($transactions as $tx) {
                    $tx->transaction_date = self::date($tx->transaction_date);
                    if ($tx->currency !== $currency) {
                        $issue($code.' / interest payment '.$tx->id, 'Currency differs from loan. Payment excluded; review before relying on balance.');

                        continue;
                    }
                    if ($tx->transaction_date <= $asOf) {
                        $amount = self::cents($tx->amount);
                        $paid += $amount;
                        $loanActivity[] = ['loan_id' => $loan->id, 'code' => $code, 'lender' => $loan->lender_name, 'currency' => $currency,
                            'date' => $tx->transaction_date, 'kind' => 'Interest payment', 'amount' => $amount,
                            'reference' => $tx->reference_no, 'notes' => $tx->description];
                    }
                }
                $txTotal = $transactions->where('currency', $currency)->sum(fn ($tx) => self::cents($tx->amount));
                $legacy = self::cents($period->paid_amount) - $txTotal;
                if ($legacy > 0) {
                    $withheld = ! empty($loan->is_upfront_interest) && $period->due_date === $date && $period->paid_date === $date;
                    if (! $withheld) {
                        $issue($code.' / interest period '.$period->id, 'Legacy paid amount lacks individual ledger payment detail. The dated schedule amount is used; earlier partial-payment dates cannot be reconstructed.');
                    }
                    if ($period->paid_date && $period->paid_date <= $asOf) {
                        $paid += $legacy;
                        $loanActivity[] = ['loan_id' => $loan->id, 'code' => $code, 'lender' => $loan->lender_name, 'currency' => $currency,
                            'date' => $period->paid_date, 'kind' => $withheld ? 'Interest withheld at borrowing' : 'Legacy interest settlement',
                            'amount' => $legacy, 'reference' => 'Schedule #'.$period->id, 'notes' => $withheld ? 'Non-cash deduction from loan proceeds.' : 'Source: dated schedule; individual bank transaction unavailable.'];
                    } elseif (! $period->paid_date) {
                        $issue($code.' / interest period '.$period->id, 'Paid amount has no date; excluded from payments at cutoff.');
                    }
                } elseif ($legacy < 0) {
                    $issue($code.' / interest period '.$period->id, 'Ledger payments exceed schedule paid total. Dated ledger detail used; reconcile source records.');
                }
                $charge = self::cents($period->interest_amount);
                $remaining = max(0, $charge - $paid);
                $paidInterest += $paid;
                if ($period->status === 'skipped') {
                    $issue($code.' / interest period '.$period->id, 'Interest is currently skipped. No dated waiver is stored; remaining charge excluded from totals pending review.');
                } elseif ($period->due_date <= $asOf) {
                    $dueInterest += $remaining;
                } else {
                    $futureInterest += $remaining;
                }
                if ($paid > $charge) {
                    $issue($code.' / interest period '.$period->id, 'Interest payments exceed charge; no credit is offset against other obligations.');
                }
                $interestSchedule[] = ['loan_id' => $loan->id, 'code' => $code, 'currency' => $currency, 'due_on' => $period->due_date,
                    'charge' => $charge, 'paid' => $paid, 'balance' => $remaining,
                    'classification' => $period->status === 'skipped' ? 'Skipped / review waiver' : ($period->due_date <= $asOf ? 'Due by cutoff' : 'Future commitment')];
            }
            if ($included && $schedules->get($loan->id, collect())->isEmpty() && $loan->interest_method !== 'no_interest') {
                $unscheduledInterest = self::cents(LoanBalanceService::calculate($loan, collect(), collect())['contracted_interest']);
                $issue($code, 'Interest schedule is missing. Contract estimate shown separately, outside dated liabilities; review terms and due dates.');
            }
            $principal = max(0, $borrowed - $repaid);
            $balance = $principal + $dueInterest;
            if ($repaid > $borrowed) {
                $issue($code, 'Principal repayments exceed borrowing. Balance floored at zero; excess is not offset against other loans.');
            }
            $currentBalance = LoanBalanceService::calculate($loan, $schedules->get($loan->id, collect()), $records->get($loan->id, collect()));
            if ($included && in_array($loan->status, ['settled', 'closed']) && $principal + $dueInterest + $futureInterest > 0 && $currentBalance['full_settlement_amount'] > 0) {
                $issue($code, 'Current loan status is '.$loan->status.' but dated records leave a balance. Status alone is not evidence of payment.');
            }
            $loans[] = compact('code', 'currency', 'date', 'included', 'reason', 'original', 'borrowed', 'repaid', 'principal', 'paidInterest', 'dueInterest', 'futureInterest', 'unscheduledInterest', 'balance') +
                ['id' => $loan->id, 'lender' => $loan->lender_name, 'source_status' => $loan->status,
                    'balance_status' => ! $included ? 'Review' : ($balance + $futureInterest + $unscheduledInterest === 0 ? 'Settled by recorded payments' : 'Outstanding')];
            if ($included) {
                foreach (['principal' => $principal, 'interest_due' => $dueInterest, 'future_interest' => $futureInterest, 'unscheduled_interest' => $unscheduledInterest] as $key => $amount) {
                    $add($currency, $key, $amount);
                }
            }
        }

        $periodEnd = CarbonImmutable::parse($asOf)->endOfMonth()->toDateString() <= $asOf
            ? $asOf : CarbonImmutable::parse($asOf)->startOfMonth()->subDay()->toDateString();
        $runs = DB::table('payroll_runs as r')->join('companies as c', 'c.id', '=', 'r.company_id')
            ->leftJoin('journal_entries as j', 'j.id', '=', 'r.journal_entry_id')->leftJoin('journal_entries as v', 'v.id', '=', 'r.reversal_journal_id')
            ->where('r.month', '<=', substr($periodEnd, 0, 7).'-01')
            ->select('r.*', 'c.name as company', 'j.entry_date as posting_date', 'v.entry_date as void_date')->orderBy('r.month')->orderBy('r.id')->get();
        $runIds = $runs->pluck('id');
        $items = DB::table('payroll_items')->whereIn('payroll_run_id', $runIds)->orderBy('id')->get()->groupBy('payroll_run_id');
        $allPayments = DB::table('payroll_payments')->whereIn('payroll_run_id', $runIds)->whereDate('paid_on', '<=', $asOf)->orderBy('paid_on')->orderBy('id')->get()->groupBy('payroll_run_id');
        $allocations = DB::table('payroll_payment_allocations')->whereIn('payroll_payment_id', $allPayments->flatten()->pluck('id'))->get()->groupBy('payroll_payment_id');
        $events = DB::table('payroll_statutory_events')->whereIn('payroll_run_id', $runIds)->whereDate('event_on', '<=', $asOf)->orderByDesc('id')->get()->groupBy('payroll_run_id');
        foreach ($runs as $run) {
            $run->posting_date = self::date($run->posting_date);
            $run->void_date = self::date($run->void_date);
            // A later reversal must not erase a liability which existed at the reporting date.
            $posted = (in_array($run->status, PayrollService::POSTED_STATUSES) || ($run->status === 'void' && $run->void_date > $asOf))
                && (! $run->posting_date || $run->posting_date <= $asOf);
            $draft = in_array($run->status, ['draft', 'review']);
            $run->report_status = $posted && $run->status === 'void' ? 'Approved at cutoff; voided later' : ucfirst($run->status);
            $run->report_included = $posted || $draft;
            $rows = $items->get($run->id, collect());
            if (! $posted && ! $draft) {
                continue;
            }
            $paidByItem = [];
            $paidByKind = array_fill_keys(array_keys(PayrollService::KINDS), 0);
            foreach ($allPayments->get($run->id, collect()) as $payment) {
                $payment->paid_on = self::date($payment->paid_on);
                $payment->reversed_on = self::date($payment->reversed_on);
                $effective = $payment->status === 'posted' || ($payment->status === 'reversed' && $payment->reversed_on && $payment->reversed_on > $asOf);
                if (! array_key_exists($payment->kind, $paidByKind)) {
                    continue;
                }
                if ($payment->currency !== $run->currency) {
                    $issue('Payroll payment #'.$payment->id, 'Currency mismatch; excluded from balances.');

                    continue;
                }
                if ($effective) {
                    $paidByKind[$payment->kind] += (int) $payment->amount_cents;
                    foreach ($allocations->get($payment->id, collect()) as $allocation) {
                        $paidByItem[$allocation->payroll_item_id][$payment->kind] = ($paidByItem[$allocation->payroll_item_id][$payment->kind] ?? 0) + (int) $allocation->amount_cents;
                    }
                }
                if ($posted) {
                    $payments[] = ['run_id' => $run->id, 'month' => $run->month, 'company' => $run->company, 'currency' => $run->currency,
                        'kind' => PayrollService::KINDS[$payment->kind], 'date' => $payment->paid_on, 'amount' => (int) $payment->amount_cents,
                        'reference' => $payment->reference, 'id' => $payment->id, 'effective' => $effective,
                        'status' => $effective ? 'Counts at cutoff' : 'Reversed by cutoff', 'reversed_on' => $payment->reversed_on];
                    if ($payment->kind === 'salary') {
                        foreach ($allocations->get($payment->id, collect()) as $allocation) {
                            $item = $rows->firstWhere('id', $allocation->payroll_item_id);
                            $snapshot = $item ? json_decode($item->snapshot, true) : [];
                            $salaryPayments[] = ['run_id' => $run->id, 'month' => $run->month, 'company' => $run->company, 'currency' => $run->currency,
                                'name' => $snapshot['name'] ?? 'Unknown employee / review allocation', 'code' => $snapshot['code'] ?? '',
                                'date' => $payment->paid_on, 'amount' => (int) $allocation->amount_cents, 'reference' => $payment->reference,
                                'payment_id' => $payment->id, 'effective' => $effective, 'reversed_on' => $payment->reversed_on];
                        }
                    }
                }
            }
            $base = ['run_id' => $run->id, 'month' => $run->month, 'company' => $run->company, 'currency' => $run->currency, 'employees' => $rows->count(), 'status' => $run->report_status];
            if ($draft) {
                $drafts[] = $base + ['net' => (int) $rows->sum('net_cents'), 'epf' => (int) $rows->sum('epf_cents'), 'etf' => (int) $rows->sum('etf_cents'),
                    'apit' => (int) $rows->sum('apit_cents'), 'other' => (int) $rows->sum('other_cents'), 'notes' => $run->notes];

                continue;
            }
            $salaryDue = $salaryPaid = $salaryBalance = 0;
            foreach ($rows as $item) {
                $snapshot = json_decode($item->snapshot, true) ?? [];
                $paid = $paidByItem[$item->id]['salary'] ?? 0;
                $due = (int) $item->net_cents;
                $balance = max(0, $due - $paid);
                $salaryDue += $due;
                $salaryPaid += $paid;
                $salaryBalance += $balance;
                if ($balance > 0) {
                    $salaries[] = $base + ['item_id' => $item->id, 'employee_id' => $item->employee_id, 'name' => $snapshot['name'] ?? 'Employee #'.$item->employee_id,
                        'code' => $snapshot['code'] ?? '', 'due_on' => $run->salary_due_date, 'due' => $due, 'paid' => $paid, 'balance' => $balance];
                }
                if ($paid > $due) {
                    $issue('Payroll #'.$run->id.' / employee #'.$item->employee_id, 'Salary paid exceeds net pay. Excess not offset against other employees.');
                }
            }
            if ($salaryPaid !== $paidByKind['salary']) {
                $issue('Payroll #'.$run->id, 'Salary payment total differs from employee allocations. Employee balances use allocations; reconcile payment evidence.');
            }
            $payrollMonths[] = $base + ['due' => $salaryDue, 'paid' => $salaryPaid, 'balance' => $salaryBalance, 'due_on' => $run->salary_due_date];
            $add($run->currency, 'salary', $salaryBalance);
            foreach (['epf', 'etf', 'apit', 'other'] as $kind) {
                $due = (int) $rows->sum($kind.'_cents');
                if ($due === 0) {
                    continue;
                }
                $paid = $paidByKind[$kind];
                $balance = max(0, $due - $paid);
                $event = $events->get($run->id, collect())->firstWhere('kind', $kind);
                $dueOn = $event?->payment_due_on ?? ($kind === 'apit' ? $run->tax_due_date : ($kind === 'other' ? null : $run->statutory_due_date));
                $statutory[] = $base + ['kind' => $kind, 'label' => PayrollService::KINDS[$kind], 'due' => $due, 'paid' => $paid, 'balance' => $balance, 'due_on' => $dueOn,
                    'prepared' => $kind === 'other' ? 'Not applicable' : ($event ? 'Prepared '.$event->prepared_on : 'Not recorded'),
                    'submission' => $kind === 'other' ? 'Not applicable' : ($event?->action === 'submitted' ? 'Submitted '.$event->event_on : ($event?->action === 'reopened' ? 'Reopened '.$event->event_on : 'Not recorded')),
                    'reference' => $event?->reference, 'overdue' => $balance > 0 && $dueOn && $dueOn < $asOf];
                $add($run->currency, $kind, $balance);
                if ($paid > $due) {
                    $issue('Payroll #'.$run->id.' / '.$kind, 'Payments exceed payroll obligation; excess not offset against other months.');
                }
            }
        }
        foreach ($runs->groupBy('company_id') as $companyRuns) {
            $start = CarbonImmutable::parse($companyRuns->min('month'))->startOfMonth();
            $end = CarbonImmutable::parse($periodEnd)->startOfMonth();
            for ($month = $start; $month <= $end; $month = $month->addMonth()) {
                $matching = $companyRuns->where('month', $month->toDateString())->where('report_included', true);
                $coverage[] = ['company' => $companyRuns->first()->company, 'month' => $month->format('Y-m'),
                    'status' => $matching->isEmpty() ? 'No valid payroll in scope; amount unknown' : $matching->map(fn ($r) => '#'.$r->id.' '.$r->report_status)->implode('; ')];
            }
        }
        foreach (DB::table('companies')->whereNotIn('id', $runs->pluck('company_id')->unique())->get(['name']) as $company) {
            $coverage[] = ['company' => $company->name, 'month' => 'Not available', 'status' => 'No payroll recorded; salary and statutory amounts unknown'];
        }
        foreach ($summary as &$totals) {
            $totals['total'] = $totals['principal'] + $totals['interest_due'] + $totals['salary'] + $totals['epf'] + $totals['etf'] + $totals['apit'] + $totals['other'];
        }
        unset($totals);
        $draftSummary = [];
        foreach ($drafts as $draft) {
            $draftSummary[$draft['currency']] ??= array_fill_keys(['net', 'epf', 'etf', 'apit', 'other'], 0);
            foreach (array_keys($draftSummary[$draft['currency']]) as $kind) {
                $draftSummary[$draft['currency']][$kind] += $draft[$kind];
            }
        }
        ksort($summary);
        usort($loanActivity, fn ($a, $b) => [$a['date'], $a['loan_id']] <=> [$b['date'], $b['loan_id']]);

        return compact('asOf', 'periodEnd', 'summary', 'loans', 'loanActivity', 'interestSchedule', 'salaries', 'payrollMonths', 'statutory', 'payments', 'salaryPayments', 'drafts', 'draftSummary', 'exceptions', 'coverage') +
            ['generatedAt' => now('Asia/Colombo')->format('Y-m-d H:i T')];
    }

    private static function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private static function date(?string $date): ?string
    {
        return $date ? substr($date, 0, 10) : null;
    }
}
