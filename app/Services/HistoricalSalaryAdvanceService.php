<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reconstruct user-confirmed past advances from the frozen salary-sheet source, once per item. */
class HistoricalSalaryAdvanceService
{
    public static function source(object $item): array
    {
        $snapshot = is_string($item->snapshot) ? json_decode($item->snapshot, true) : $item->snapshot;

        return $snapshot['historical_source'] ?? [];
    }

    public static function candidates(int $runId)
    {
        return DB::table('payroll_items')->where('payroll_run_id', $runId)->orderBy('id')->get()
            ->filter(fn ($item) => (int) (self::source($item)['advance'] ?? 0) > 0)->values();
    }

    public static function version(object $run, $items): string
    {
        return hash_hmac('sha256', json_encode([$run->id, $run->company_id, $run->month, $run->currency,
            $items->map(fn ($i) => [$i->id, $i->employee_id, self::source($i), (int) $i->advance_cents])->all()], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public static function plan(?int $companyId = null): array
    {
        $runs = DB::table('payroll_runs')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->whereIn('status', ['draft', 'review', ...PayrollService::POSTED_STATUSES])->orderBy('month')->get();
        $items = DB::table('payroll_items')->whereIn('payroll_run_id', $runs->pluck('id'))->orderBy('id')->get()->groupBy('payroll_run_id');
        $links = DB::table('payroll_historical_advance_links as h')->join('payroll_payments as p', 'p.id', '=', 'h.payroll_payment_id')->select('h.*', 'p.status as payment_status')->get()->keyBy('payroll_item_id');
        $result = [];
        foreach ($runs as $run) {
            $rows = ($items[$run->id] ?? collect())->filter(fn ($item) => (int) (self::source($item)['advance'] ?? 0) > 0)->values();
            if ($rows->isEmpty()) {
                continue;
            }
            $result[] = ['run' => $run, 'version' => self::version($run, $rows), 'count' => $rows->count(),
                'total_cents' => $rows->sum(fn ($i) => (int) self::source($i)['advance']),
                'linked' => $rows->filter(fn ($i) => $links->get($i->id)?->payment_status === 'posted')->count(),
                'employees' => $rows->map(fn ($i) => ['name' => self::source($i)['name'], 'employee_id' => $i->employee_id,
                    'amount_cents' => (int) self::source($i)['advance'], 'sheet' => self::source($i)['sheet'], 'row' => self::source($i)['row'],
                    'payment_id' => $links->get($i->id)?->payroll_payment_id])->all()];
        }

        return $result;
    }

    /** Create one month's past payments atomically; retrying verifies the original records. */
    public static function import(int $runId, int $bankId, string $version): array
    {
        return DB::transaction(function () use ($runId, $bankId, $version) {
            $run = DB::table('payroll_runs')->where('id', $runId)->lockForUpdate()->first() ?? abort(404);
            $bank = DB::table('bank_accounts')->where('id', $bankId)->first();
            self::check($bank && (int) $bank->company_id === (int) $run->company_id && $bank->currency === $run->currency, 'Choose a bank account in the payroll company and currency.');
            self::check(in_array($run->status, ['draft', 'review', ...PayrollService::POSTED_STATUSES]), 'Voided payroll cannot receive historical advances.');
            $items = self::candidates($runId);
            self::check($items->isNotEmpty() && hash_equals(self::version($run, $items), $version), 'The salary-sheet advances have changed. Refresh and review this month again.');
            $created = $existing = $total = 0;
            foreach ($items as $item) {
                Employee::whereKey($item->employee_id)->lockForUpdate()->firstOrFail();
                $source = self::source($item);
                $amount = (int) $source['advance'];
                $paidOn = substr($run->month, 0, 7).'-10';
                self::check($run->currency === 'LKR' && $source['month'] === substr($run->month, 0, 7) && $amount === (int) $item->advance_cents,
                    'The payroll advance deduction differs from its salary-sheet source. Reconcile it before importing.');
                self::check($paidOn <= PayrollStatutoryService::today(), 'Historical advance dates cannot be in the future.');
                $link = DB::table('payroll_historical_advance_links')->where('payroll_item_id', $item->id)->first();
                if ($link) {
                    $payment = self::linkedPayment($run, $item);
                    self::check((int) $payment->bank_account_id === $bankId, 'This advance was already recorded against a different bank account.');
                    $existing++;
                } else {
                    self::check(in_array($run->status, ['draft', 'review']), 'An approved historical payroll requires an opening-balance reconciliation, not a new advance payment.');
                    $possible = DB::table('payroll_payments')->where('kind', 'advance')->where('status', 'posted')->where('employee_id', $item->employee_id)
                        ->where('company_id', $run->company_id)->where('currency', $run->currency)->where('amount_cents', $amount)->whereDate('paid_on', $paidOn)->first();
                    self::check(! $possible, 'A matching advance already exists for this employee/date/amount. Reconcile it before importing another.');
                    $reference = 'Historical salary advance '.$source['month'].' / employee '.$item->employee_id.' / '.$source['sheet'].' row '.$source['row'].' (sheet reference; date set to 10th)';
                    $paymentId = PayrollService::advance(['employee_id' => $item->employee_id, 'company_id' => $run->company_id, 'department_id' => $run->department_id,
                        'currency' => $run->currency, 'amount' => $amount / 100, 'paid_on' => $paidOn, 'recovery_month' => substr($run->month, 0, 7),
                        'reference' => $reference, 'request_key' => 'historical-salary-advance-'.$item->id, 'bank_account_id' => $bankId]);
                    DB::table('payroll_historical_advance_links')->insert(['payroll_item_id' => $item->id, 'payroll_payment_id' => $paymentId,
                        'source_sha256' => $source['sha256'], 'source_sheet' => $source['sheet'], 'source_row' => $source['row'], 'source_amount_cents' => $amount,
                        'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
                    $created++;
                }
                $total += $amount;
            }
            ActivityLogService::log('Reconciled historical salary advances', 'Payroll', $runId, null,
                ['created' => $created, 'existing' => $existing, 'total_cents' => $total, 'bank_account_id' => $bankId,
                    'date_basis' => '10th of source payroll month, instructed by user', 'deduction_month' => $run->month]);

            return compact('created', 'existing', 'total');
        });
    }

    public static function linkedPayment(object $run, object $item): object
    {
        $source = self::source($item);
        $link = DB::table('payroll_historical_advance_links')->where('payroll_item_id', $item->id)->first();
        $payment = $link ? DB::table('payroll_payments')->find($link->payroll_payment_id) : null;
        self::check($link && $payment && $payment->kind === 'advance' && $payment->status === 'posted'
            && (int) $payment->employee_id === (int) $item->employee_id && (int) $payment->company_id === (int) $run->company_id
            && $payment->currency === $run->currency && $payment->bank_account_id
            && substr($payment->paid_on, 0, 10) === substr($run->month, 0, 7).'-10' && $payment->recovery_month === $run->month
            && (int) $payment->amount_cents === (int) ($source['advance'] ?? 0) && (int) $item->advance_cents === (int) $payment->amount_cents
            && (int) $link->source_amount_cents === (int) $payment->amount_cents && $link->source_sha256 === ($source['sha256'] ?? '')
            && $link->source_sheet === ($source['sheet'] ?? '') && (int) $link->source_row === (int) ($source['row'] ?? 0),
            'Historical salary advances need matching bank-payment records for this payroll. Open Import historical advances to reconcile them.');

        return SalaryAdvanceService::payments()->where('p.id', $payment->id)->first();
    }

    private static function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['historical_advances' => $message]);
        }
    }
}
