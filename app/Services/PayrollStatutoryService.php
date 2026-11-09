<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollStatutoryService
{
    public const KINDS = ['epf' => 'EPF', 'etf' => 'ETF', 'apit' => 'APIT'];

    public static function today(): string
    {
        return Carbon::today('Asia/Colombo')->toDateString();
    }

    public static function states(object $run, $items): array
    {
        $balances = PayrollService::balances($run, $items);
        $latest = DB::table('payroll_statutory_events')->where('payroll_run_id', $run->id)->orderByDesc('id')->get()->unique('kind')->keyBy('kind');
        $states = [];
        foreach (self::KINDS as $kind => $label) {
            $event = $latest->get($kind);
            $states[$kind] = self::state($run->status, $balances[$kind]['due'], (int) $balances[$kind]['paid'],
                $event?->payment_due_on ?? ($kind === 'apit' ? $run->tax_due_date : $run->statutory_due_date), $event) + ['label' => $label, 'kind' => $kind];
        }

        return $states;
    }

    public static function state(string $runStatus, int $due, int $paid, string $dueOn, ?object $event): array
    {
        $posted = in_array($runStatus, PayrollService::POSTED_STATUSES);
        $balance = $runStatus === 'void' ? 0 : max(0, $due - $paid);
        $paymentState = $runStatus === 'void' ? 'Void' : (! $posted ? 'Not approved' : ($due === 0 ? 'Not applicable' : ($balance === 0 ? 'Paid' : ($paid > 0 ? 'Partially paid' : 'Unpaid'))));
        $prepared = (bool) $event;
        $submitted = $event?->action === 'submitted';

        return compact('due', 'paid', 'balance', 'dueOn', 'prepared', 'submitted', 'paymentState', 'event', 'posted') + [
            'overdue' => $posted && $balance > 0 && $dueOn < self::today(),
            'preparationLabel' => $due === 0 ? 'Not applicable' : ($prepared ? 'Prepared' : 'Not recorded'),
            'submissionLabel' => $due === 0 ? 'Not applicable' : ($submitted ? 'Submitted' : ($event?->action === 'reopened' ? 'Reopened' : 'Not recorded')),
        ];
    }

    /** Preparation/document events never post a journal or clear an amount payable. */
    public static function record(int $runId, string $kind, string $action, array $input): int
    {
        abort_unless(isset(self::KINDS[$kind]) && in_array($action, ['prepared', 'submitted', 'reopened']), 404);
        $rules = ['event_on' => 'required|date_format:Y-m-d|before_or_equal:'.self::today(),
            'expected_event_id' => 'required|integer|min:0', 'request_key' => 'required|uuid',
            'notes' => $action === 'reopened' ? 'required|string|min:5|max:2000' : 'nullable|string|max:2000'];
        if ($action === 'prepared') {
            $rules += ['payment_due_on' => 'required|date_format:Y-m-d'];
        }
        if ($action === 'submitted') {
            $rules += ['reference' => 'required|string|max:255'];
        }
        $data = validator($input, $rules)->validate();

        return DB::transaction(function () use ($runId, $kind, $action, $data) {
            $run = DB::table('payroll_runs')->where('id', $runId)->lockForUpdate()->first() ?? abort(404);
            self::check(in_array($run->status, PayrollService::POSTED_STATUSES), 'Approve payroll before recording statutory preparation or submission.');
            $state = self::states($run, PayrollService::items($runId))[$kind];
            $latest = $state['event'];
            self::check($state['due'] > 0, 'There is no amount due for this statutory obligation.');
            self::check(! DB::table('payroll_statutory_events')->where('request_key', $data['request_key'])->exists(), 'This statutory update was already recorded. Refresh the page.');
            self::check((int) ($latest?->id ?? 0) === (int) $data['expected_event_id'], 'This statutory record changed. Refresh the page before saving.');
            self::check($data['event_on'] >= $run->month, 'The activity date cannot be before the payroll month.');
            if ($action === 'prepared') {
                self::check(! $state['submitted'], 'Reopen the submission before changing its preparation.');
                self::check($data['payment_due_on'] >= $run->month, 'The payment due date cannot be before the payroll month.');
            } elseif ($action === 'submitted') {
                self::check($latest && ! $state['submitted'], 'Record the preparation before recording a new submission.');
                self::check((int) $latest->prepared_amount_cents === $state['due'], 'The prepared amount differs from payroll. Review and prepare the schedule again.');
                self::check($data['event_on'] >= $latest->prepared_on, 'Submission cannot be dated before the preparation.');
            } else {
                self::check($state['submitted'], 'Only a submitted record can be reopened.');
                self::check($data['event_on'] >= $latest->event_on, 'Reopening cannot be dated before submission.');
            }
            $values = ['payroll_run_id' => $runId, 'kind' => $kind, 'action' => $action, 'event_on' => $data['event_on'],
                'prepared_on' => $action === 'prepared' ? $data['event_on'] : $latest->prepared_on,
                'payment_due_on' => $action === 'prepared' ? $data['payment_due_on'] : $latest->payment_due_on,
                'prepared_amount_cents' => $state['due'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'request_key' => $data['request_key'], 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()];
            $id = DB::table('payroll_statutory_events')->insertGetId($values);
            ActivityLogService::logCreate('PayrollStatutoryEvent', $id, $values);

            return $id;
        });
    }

    /** One row per payroll month/authority, with a shared outstanding-balance calculation. */
    public static function register(array $filters = [])
    {
        $totals = DB::table('payroll_items')->select('payroll_run_id')->selectRaw('SUM(epf_cents) as epf, SUM(etf_cents) as etf, SUM(apit_cents) as apit')->groupBy('payroll_run_id');
        $paid = DB::table('payroll_payments')->where('status', 'posted')->whereIn('kind', array_keys(self::KINDS))
            ->select('payroll_run_id', 'kind')->selectRaw('SUM(amount_cents) as paid_cents')->groupBy('payroll_run_id', 'kind');
        $latest = DB::table('payroll_statutory_events')->select('payroll_run_id', 'kind')->selectRaw('MAX(id) as id')->groupBy('payroll_run_id', 'kind');
        $union = null;
        foreach (self::KINDS as $kind => $label) {
            $deadline = $kind === 'apit' ? 'tax_due_date' : 'statutory_due_date';
            $query = DB::table('payroll_runs as r')->join('companies as c', 'c.id', '=', 'r.company_id')
                ->joinSub($totals, 't', fn ($join) => $join->on('t.payroll_run_id', '=', 'r.id'))
                ->leftJoinSub($paid, 'p', fn ($join) => $join->on('p.payroll_run_id', '=', 'r.id')->where('p.kind', $kind))
                ->leftJoinSub($latest, 'latest', fn ($join) => $join->on('latest.payroll_run_id', '=', 'r.id')->where('latest.kind', $kind))
                ->leftJoin('payroll_statutory_events as e', 'e.id', '=', 'latest.id')
                ->whereIn('r.status', PayrollService::POSTED_STATUSES)->where('t.'.$kind, '>', 0)
                ->select('r.id as run_id', 'r.month', 'r.status as run_status', 'r.company_id', 'c.name as company_name', 'r.currency', 'e.id as event_id', 'e.action', 'e.event_on', 'e.prepared_on', 'e.reference')
                ->selectRaw("? as kind, t.{$kind} as due_cents, COALESCE(p.paid_cents, 0) as paid_cents, t.{$kind} - COALESCE(p.paid_cents, 0) as balance_cents, COALESCE(e.payment_due_on, r.{$deadline}) as due_on", [$kind]);
            $union ? $union->unionAll($query) : $union = $query;
        }
        $query = DB::query()->fromSub($union, 's')->when(! empty($filters['company_id']), fn ($q) => $q->where('company_id', $filters['company_id']))
            ->when(! empty($filters['year']), fn ($q) => $q->whereBetween('month', [$filters['year'].'-01-01', $filters['year'].'-12-31']))
            ->when(! empty($filters['month']), fn ($q) => $q->where('month', $filters['month'].'-01'))
            ->when(! empty($filters['kind']), fn ($q) => $q->where('kind', $filters['kind']));
        $notSubmitted = fn ($q) => $q->whereNull('action')->orWhere('action', '!=', 'submitted');
        match ($filters['status'] ?? 'open') {
            'open' => $query->where(fn ($q) => $q->where('balance_cents', '>', 0)->orWhere($notSubmitted)),
            'paid' => $query->where('balance_cents', '<=', 0),
            'partial' => $query->where('balance_cents', '>', 0)->where('paid_cents', '>', 0),
            'unpaid' => $query->where('balance_cents', '>', 0),
            'prepared_unpaid' => $query->whereNotNull('event_id')->where('balance_cents', '>', 0),
            'paid_unsubmitted' => $query->where('balance_cents', '<=', 0)->where($notSubmitted),
            'overdue' => $query->where('balance_cents', '>', 0)->where('due_on', '<', self::today()),
            default => $query,
        };

        return $query;
    }

    /** A batch groups existing monthly payments without changing their ledger or audit semantics.
     * The unique request key stores a namespaced batch UUID plus its payroll ID.
     */
    public static function batch(array $input): int
    {
        $data = validator($input, [
            'company_id' => 'required|integer|exists:companies,id',
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'entries' => 'required|array|min:1|max:240',
            'entries.*.run_id' => 'required|integer|distinct',
            'entries.*.amount' => 'required|numeric|min:0.01|max:999999999.99|decimal:0,2',
            'paid_on' => 'required|date_format:Y-m-d|before_or_equal:'.self::today(),
            'reference' => 'required|string|max:255', 'request_key' => 'required|uuid',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ])->validate();

        return DB::transaction(function () use ($data) {
            // Serialise batches in this company, including repeated requests with different selections.
            DB::table('companies')->where('id', $data['company_id'])->lockForUpdate()->first();
            $prefix = 'statbatch:'.strtolower($data['request_key']).':';
            self::check(! DB::table('payroll_payments')->where('request_key', 'like', $prefix.'%')->exists(), 'This batch was already recorded. Check payment batch history before retrying.');
            $entries = collect($data['entries'])->sortBy('run_id');
            $runs = DB::table('payroll_runs')->whereIn('id', $entries->pluck('run_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            self::check($runs->count() === $entries->count(), 'A selected payroll no longer exists. Refresh the months.');
            self::check($entries->sum(fn ($entry) => PayrollCalculationService::cents($entry['amount'])) <= 99999999999, 'The batch exceeds the maximum total of LKR 999,999,999.99.');
            $ids = [];
            foreach ($entries as $entry) {
                $run = $runs[$entry['run_id']];
                self::check((int) $run->company_id === (int) $data['company_id'] && $run->currency === 'LKR', 'Every selected payroll must belong to this company and use LKR.');
                $ids[] = PayrollService::payment($run->id, [
                    'kind' => $data['kind'], 'amount' => $entry['amount'], 'paid_on' => $data['paid_on'],
                    'reference' => $data['reference'], 'bank_account_id' => $data['bank_account_id'] ?? null,
                    'request_key' => $prefix.$run->id,
                ]);
            }
            ActivityLogService::logCreate('PayrollStatutoryBatch', $ids[0], ['payment_ids' => $ids, 'kind' => $data['kind'], 'company_id' => $data['company_id']]);

            return $ids[0];
        });
    }

    public static function batchPayments(int $payment)
    {
        $first = DB::table('payroll_payments')->find($payment) ?? abort(404);
        abort_unless(preg_match('/^statbatch:([0-9a-f-]{36}):[0-9]+$/', $first->request_key, $match), 404);

        return DB::table('payroll_payments as p')->join('payroll_runs as r', 'r.id', '=', 'p.payroll_run_id')
            ->where('p.company_id', $first->company_id)->where('p.kind', $first->kind)
            ->where('p.request_key', 'like', 'statbatch:'.$match[1].':%')->select('p.*', 'r.month')->orderBy('r.month')->orderBy('p.id')->get();
    }

    public static function batches(array $filters = [])
    {
        return DB::table('payroll_payments as p')->join('companies as c', 'c.id', '=', 'p.company_id')
            ->leftJoin('bank_accounts as b', 'b.id', '=', 'p.bank_account_id')
            ->where('p.request_key', 'like', 'statbatch:%')
            ->when(! empty($filters['company_id']), fn ($q) => $q->where('p.company_id', $filters['company_id']))
            ->when(! empty($filters['kind']), fn ($q) => $q->where('p.kind', $filters['kind']))
            ->select('p.company_id', 'c.name as company_name', 'p.kind', 'p.paid_on', 'p.reference', 'p.currency', 'b.bank_name', 'b.account_no')
            ->selectRaw("MIN(p.id) as id, COUNT(*) as month_count, SUM(p.amount_cents) as total_cents, SUM(CASE WHEN p.status = 'posted' THEN p.amount_cents ELSE 0 END) as posted_cents")
            ->groupByRaw('SUBSTR(p.request_key, 1, 46), p.company_id, c.name, p.kind, p.paid_on, p.reference, p.currency, b.bank_name, b.account_no');
    }

    private static function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['statutory' => $message]);
        }
    }
}
