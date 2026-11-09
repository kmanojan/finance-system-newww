<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HistoricalEmployeeLoanService
{
    private const TABLE = 'payroll_historical_loan_reconciliations';

    private const ACCOUNT_NAME = 'employee loans receivable';

    public static function rows(object $run, Collection $items): Collection
    {
        $items = self::deductions($items);
        if ($items->isEmpty()) {
            return $items;
        }

        $saved = DB::table(self::TABLE)->whereIn('payroll_item_id', $items->pluck('id'))->get()->keyBy('payroll_item_id');
        $entries = self::openingEntries($run)->get();
        $recovered = self::recovered($entries->pluck('id'), $run);

        return $items->map(function ($item) use ($run, $saved, $entries, $recovered) {
            $match = $saved->get($item->id);
            $options = $entries->filter(fn ($entry) => self::matchesEmployee($entry, $item));
            $entry = $options->firstWhere('id', $match?->journal_line_id);
            $amount = (int) $item->snapshot['historical_source']['loan'];
            $editable = in_array($run->status, ['draft', 'review']);
            $valid = $match && (! $editable || ($entry
                && self::canRecover($item, $match, $entry, $recovered->get($entry->id))));

            return compact('item', 'match', 'options', 'entry', 'amount', 'valid');
        });
    }

    public static function save(int $runId, int $itemId, array $data): void
    {
        DB::transaction(function () use ($runId, $itemId, $data) {
            $run = DB::table('payroll_runs')->lockForUpdate()->find($runId) ?? abort(404);
            self::check(in_array($run->status, ['draft', 'review']), 'Only payroll awaiting approval can be reconciled.');
            $item = self::deductions(PayrollService::items($runId))->firstWhere('id', $itemId) ?? abort(404);
            self::lockEntries(collect([$data['journal_line_id']]));
            $entry = self::openingEntries($run)->where('l.id', $data['journal_line_id'])->first();
            self::check($entry && self::matchesEmployee($entry, $item), 'Choose a posted employee loan receivable entry for this employee and payroll month.');
            $record = [
                'journal_line_id' => $entry->id,
                'source_reference' => $data['source_reference'],
                'balance_cents' => PayrollCalculationService::cents($data['balance']),
                'deduction_cents' => (int) $item->snapshot['historical_source']['loan'],
                'verified_by' => auth()->id(),
            ];
            $recovered = self::recovered(collect([$entry->id]), $run);
            self::check(self::canRecover($item, (object) $record, $entry, $recovered->get($entry->id)),
                'The balance must cover the deduction and fit within the opening entry’s available balance.');

            $existing = DB::table(self::TABLE)->where('payroll_item_id', $itemId)->first();
            DB::table(self::TABLE)->upsert([
                $record + ['payroll_item_id' => $itemId, 'created_at' => now(), 'updated_at' => now()],
            ], ['payroll_item_id'], [...array_keys($record), 'updated_at']);
            ActivityLogService::log('historical_loan_reconciled', 'PayrollItem', $itemId, $existing, $record);
        });
    }

    /** Called in the payroll approval transaction, after locking its run. */
    public static function allocate(object $run, Collection $items): array
    {
        $items = self::deductions($items);
        if ($items->isEmpty()) {
            return [];
        }

        $ids = DB::table(self::TABLE)->whereIn('payroll_item_id', $items->pluck('id'))->pluck('journal_line_id');
        self::lockEntries($ids);
        $rows = self::rows($run, $items);
        self::check($rows->every(fn ($row) => $row['valid']),
            'Historical employee loan deductions still need opening-balance reconciliation before approval. Open Employee loan reconciliation for the affected rows.', 'payroll');
        self::check($ids->unique()->count() === $ids->count(), 'Use a separate opening entry for each employee.', 'payroll');

        $lines = [];
        foreach ($rows as $row) {
            ['item' => $item, 'entry' => $entry, 'amount' => $amount] = $row;
            $item->other_cents -= $amount;
            DB::table('payroll_items')->where('id', $item->id)->update(['other_cents' => $item->other_cents, 'updated_at' => now()]);
            $lines[] = ['account_id' => $entry->account_id, 'currency' => $run->currency, 'debit' => 0, 'credit' => $amount / 100];
        }

        return $lines;
    }

    private static function deductions(Collection $items): Collection
    {
        return $items->filter(fn ($item) => ($item->snapshot['historical_source']['loan'] ?? 0) > 0);
    }

    private static function openingEntries(object $run)
    {
        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.company_id', $run->company_id)->where('a.type', 'asset')->where('a.is_active', true)
            ->whereRaw('LOWER(a.name) = ?', [self::ACCOUNT_NAME])
            ->where('j.status', 'posted')->where('j.entry_date', '<=', Carbon::parse($run->month)->endOfMonth()->toDateString())
            ->where('l.currency', $run->currency)->where('l.debit', '>', 0)->where('l.credit', 0)
            ->select('l.id', 'l.account_id', 'l.debit', 'j.entry_no', 'j.description', 'j.entry_date')
            ->orderBy('j.entry_date')->orderBy('l.id');
    }

    private static function matchesEmployee(object $entry, object $item): bool
    {
        $code = $item->snapshot['code'] ?? '';

        return $code !== '' && str_starts_with(strtolower(trim($entry->description ?? '')), strtolower('Employee loan opening ['.$code.']'));
    }

    private static function lockEntries(Collection $ids): void
    {
        DB::table('journal_entry_lines')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    private static function recovered(Collection $ids, object $run): Collection
    {
        return DB::table(self::TABLE.' as l')
            ->join('payroll_items as i', 'i.id', '=', 'l.payroll_item_id')
            ->join('payroll_runs as p', 'p.id', '=', 'i.payroll_run_id')
            ->leftJoin('journal_entries as reversal', 'reversal.id', '=', 'p.reversal_journal_id')
            ->whereIn('l.journal_line_id', $ids)->where('p.id', '!=', $run->id)
            ->where(function ($query) use ($run) {
                $query->whereIn('p.status', PayrollService::POSTED_STATUSES)
                    ->orWhere(function ($query) use ($run) {
                        $query->where('p.status', 'void')->where('p.month', '<=', $run->month)
                            ->where('reversal.entry_date', '>', Carbon::parse($run->month)->endOfMonth()->toDateString());
                    });
            })
            ->groupBy('l.journal_line_id')
            ->selectRaw('l.journal_line_id, SUM(l.deduction_cents) AS total, SUM(CASE WHEN p.month < ? THEN l.deduction_cents ELSE 0 END) AS earlier', [$run->month])
            ->get()->keyBy('journal_line_id');
    }

    private static function canRecover(object $item, object $match, object $entry, ?object $recovered): bool
    {
        $amount = (int) $item->snapshot['historical_source']['loan'];
        $postedDeduction = collect($item->calculation['deductions'])->where('name', 'Employee loan deduction (source)')->sum('amount');
        $opening = PayrollCalculationService::cents($entry->debit);

        return (int) $match->deduction_cents === $amount && (int) $postedDeduction === $amount && $item->other_cents >= $amount
            && $match->balance_cents >= $amount && $amount <= $opening - ($recovered->total ?? 0)
            && $match->balance_cents <= $opening - ($recovered->earlier ?? 0);
    }

    private static function check(bool $condition, string $message, string $field = 'historical_loan'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
