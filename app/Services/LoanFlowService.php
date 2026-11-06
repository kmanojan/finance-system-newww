<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanFlowService
{
    public static function link(int $loanId, int $recordId, int $sourceId, float $amount, ?string $notes = null): int
    {
        return DB::transaction(function () use ($loanId, $recordId, $sourceId, $amount, $notes) {
            $loans = DB::table('loans')->whereIn('id', [$loanId, $sourceId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $loans->get($loanId);
            $source = $loans->get($sourceId);
            $record = DB::table('loan_principal_records')->where('id', $recordId)->where('loan_id', $loanId)->where('record_type', 'repayment')->lockForUpdate()->first();
            $fail = fn ($message) => throw ValidationException::withMessages(['source_loan_id' => $message]);
            if (! $target || ! $source || ! $record || $loanId === $sourceId) {
                $fail('Choose another loan and a principal repayment belonging to this loan.');
            }
            if ($source->currency !== $target->currency || ! in_array($source->status, ['active', 'defaulted', 'settled', 'closed'])) {
                $fail('The funding loan must be activated and use the same currency.');
            }
            if (! $record->record_date || ! $source->claimed_date || $source->claimed_date > $record->record_date) {
                $fail('The funding loan must have been received on or before the repayment date.');
            }
            $cents = fn ($value) => (int) round((float) $value * 100);
            $allocated = DB::table('loan_refinancing_links')->where('principal_record_id', $recordId)->sum('amount');
            if ($cents($amount) <= 0 || $cents($amount) + $cents($allocated) > $cents($record->amount)) {
                $fail('The linked amount must fit within the unallocated principal repayment.');
            }
            if (DB::table('loan_refinancing_links')->where('principal_record_id', $recordId)->where('source_loan_id', $sourceId)->exists()) {
                $fail('This funding loan is already linked to that repayment. Remove the link before correcting it.');
            }
            // A loan's proceeds may fund several repayments, but cannot be allocated twice.
            $available = $cents($source->principal_amount);
            if (! empty($source->is_upfront_interest)) {
                $available -= $cents($source->upfront_interest_amount ?: $source->interest_amount);
            }
            $draws = DB::table('loan_principal_records')->where('loan_id', $sourceId)->where('record_type', 'draw')->where('record_date', '<=', $record->record_date)->sum('amount');
            $used = DB::table('loan_refinancing_links')->where('source_loan_id', $sourceId)->sum('amount');
            if ($cents($amount) + $cents($used) > $available + $cents($draws)) {
                $fail('The linked amount exceeds this loan’s unallocated proceeds available by the repayment date.');
            }
            self::assertAcyclic($loanId, $sourceId, 'source_loan_id');
            $id = DB::table('loan_refinancing_links')->insertGetId([
                'principal_record_id' => $recordId, 'source_loan_id' => $sourceId,
                'amount' => round($amount, 2), 'notes' => $notes, 'created_at' => now(), 'updated_at' => now(),
            ]);
            ActivityLogService::logCreate('LoanRefinancingLink', $id, ['loan_id' => $loanId, 'source_loan_id' => $sourceId, 'principal_record_id' => $recordId, 'amount' => round($amount, 2)]);

            return $id;
        });
    }

    private static function assertAcyclic(int $loanId, int $nextId, string $field): void
    {
        $edges = self::edges()->where('kind', 'repayment');
        $queue = [$nextId];
        $seen = [];
        while ($queue) {
            $node = array_pop($queue);
            if ($node === $loanId) {
                throw ValidationException::withMessages([$field => 'This link would create a circular loan chain.']);
            }
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            foreach ($edges->where('loan_id', $node) as $edge) {
                $queue[] = (int) $edge->source_loan_id;
            }
        }
    }

    public static function edges()
    {
        $payments = DB::table('loan_refinancing_links as links')
            ->join('loan_principal_records as records', 'records.id', '=', 'links.principal_record_id')
            ->select('links.*', 'records.loan_id', 'records.record_date')->get()
            ->map(function ($edge) {
                $edge->kind = 'repayment';

                return $edge;
            });

        return $payments->concat(self::descriptionEdges())->sortBy([['record_date', 'asc'], ['id', 'asc']])->values();
    }

    public static function mentionedLoanIds(?string $html): array
    {
        if (! $html || ! str_contains($html, '<')) {
            return [];
        }
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $ids = [];
            foreach ($dom->getElementsByTagName('a') as $anchor) {
                $href = trim($anchor->getAttribute('href'));
                $url = parse_url($href);
                if ($url === false || isset($url['user']) || isset($url['pass'])) {
                    continue;
                }
                if (isset($url['scheme']) || isset($url['host'])) {
                    if (! in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'])) {
                        continue;
                    }
                    $hosts = array_filter([parse_url(config('app.url'), PHP_URL_HOST), request()->getHost()]);
                    if (! in_array(strtolower($url['host'] ?? ''), array_map('strtolower', $hosts))) {
                        continue;
                    }
                }
                if (preg_match('~^/loans/([1-9][0-9]*)/?$~', $url['path'] ?? '', $match)) {
                    $ids[(int) $match[1]] = true;
                }
            }

            return array_keys($ids);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function descriptionEdges()
    {
        $loans = DB::table('loans')->select('id', 'purpose', 'claimed_date')->get()->keyBy('id');
        $edges = [];
        foreach ($loans as $owner) {
            foreach (self::mentionedLoanIds($owner->purpose) as $mentionedId) {
                $mentioned = $loans->get($mentionedId);
                if (! $mentioned || $mentionedId === (int) $owner->id) {
                    continue;
                }
                // Mentions are relationships, not cash movements. Order the cards by borrowing date.
                $pair = collect([$owner, $mentioned])->sortBy([['claimed_date', 'asc'], ['id', 'asc']])->values();
                $key = $pair[0]->id.':'.$pair[1]->id;
                if (! isset($edges[$key])) {
                    $edges[$key] = (object) ['id' => 'description-'.$key, 'loan_id' => $pair[0]->id,
                        'source_loan_id' => $pair[1]->id, 'record_date' => $pair[1]->claimed_date,
                        'kind' => 'description', 'description_loan_ids' => [], 'notes' => null];
                }
                $edges[$key]->description_loan_ids[] = $owner->id;
            }
        }

        return collect(array_values($edges));
    }

    public static function graph(int $loanId): array
    {
        $all = self::edges();
        $ids = [$loanId => true];
        do {
            $count = count($ids);
            foreach ($all as $edge) {
                if (isset($ids[$edge->loan_id]) || isset($ids[$edge->source_loan_id])) {
                    $ids[$edge->loan_id] = $ids[$edge->source_loan_id] = true;
                }
            }
        } while (count($ids) !== $count);
        $edges = $all->filter(fn ($edge) => isset($ids[$edge->loan_id]))->values();
        $loans = DB::table('loans')->whereIn('id', array_keys($ids))->orderBy('claimed_date')->orderBy('id')->get();
        $records = DB::table('loan_principal_records')->whereIn('loan_id', array_keys($ids))->get()->groupBy('loan_id');
        $schedules = DB::table('loan_interest_schedule')->whereIn('loan_id', array_keys($ids))->get()->groupBy('loan_id');
        $levels = array_fill_keys(array_keys($ids), 0);
        for ($i = 0; $i < count($ids); $i++) {
            $changed = false;
            foreach ($edges as $edge) {
                if ($levels[$edge->source_loan_id] <= $levels[$edge->loan_id]) {
                    $levels[$edge->source_loan_id] = $levels[$edge->loan_id] + 1;
                    $changed = true;
                }
            }
            if (! $changed) {
                break;
            }
        }
        foreach ($loans as $loan) {
            $loan->flow_balance = LoanBalanceService::calculate($loan, $schedules->get($loan->id, collect()), $records->get($loan->id, collect()));
            $loan->flow_level = $levels[$loan->id];
        }

        return ['loans' => $loans->keyBy('id'), 'columns' => $loans->groupBy('flow_level')->sortKeys(), 'edges' => $edges];
    }
}
