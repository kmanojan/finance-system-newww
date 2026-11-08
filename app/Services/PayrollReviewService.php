<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollReviewService
{
    /** Bind the review to the figures displayed, including edits made within the same second. */
    public static function version(object $item): string
    {
        $values = [(int) $item->id, (int) $item->payroll_run_id, (bool) $item->reviewed, $item->updated_at];
        foreach (['snapshot', 'adjustments', 'calculation'] as $field) {
            $values[] = is_string($item->$field) ? json_decode($item->$field, true) : $item->$field;
        }
        foreach (['gross', 'net', 'epf', 'etf', 'apit', 'other', 'advance', 'cost'] as $field) {
            $values[] = (int) $item->{$field.'_cents'};
        }

        return hash_hmac('sha256', json_encode($values, JSON_THROW_ON_ERROR), config('app.key'));
    }

    public static function update(int $runId, array $data): int
    {
        $input = validator($data, ['selection_json' => 'required|string|max:250000', 'reviewed' => 'required|boolean', 'review_note' => 'nullable|string|max:2000'])->validate();
        try {
            $entries = json_decode($input['selection_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['selection_json' => 'Select employees from this payroll before saving.']);
        }
        if (! is_array($entries) || ! array_is_list($entries)) {
            throw ValidationException::withMessages(['selection_json' => 'Use the employee selection list.']);
        }
        validator(['entries' => $entries], ['entries' => 'required|array|min:1|max:1000', 'entries.*.item_id' => 'required|integer|distinct',
            'entries.*.version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();

        return DB::transaction(function () use ($runId, $input, $entries) {
            $run = DB::table('payroll_runs')->where('id', $runId)->lockForUpdate()->first() ?? abort(404);
            if ($run->status !== 'draft') {
                throw ValidationException::withMessages(['reviewed' => 'Only draft payroll can be reviewed. Return this payroll to draft first.']);
            }
            $ids = array_map(fn ($entry) => (int) $entry['item_id'], $entries);
            $items = DB::table('payroll_items')->where('payroll_run_id', $runId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== count($ids)) {
                throw ValidationException::withMessages(['selection_json' => 'Every selected employee must belong to this payroll. Refresh the page and select again.']);
            }
            foreach ($entries as $entry) {
                if (! hash_equals(self::version($items[$entry['item_id']]), $entry['version'])) {
                    throw ValidationException::withMessages(['selection_json' => 'Selected payroll details have changed. Refresh the page, check the latest figures and select the employees again.']);
                }
            }
            $reviewed = (bool) $input['reviewed'];
            $changed = $items->filter(fn ($item) => (bool) $item->reviewed !== $reviewed)->keys()->all();
            if ($changed) {
                DB::table('payroll_items')->where('payroll_run_id', $runId)->whereIn('id', $changed)->update(['reviewed' => $reviewed, 'updated_at' => now()]);
                ActivityLogService::logUpdate('Payroll', $runId, ['reviewed' => ! $reviewed],
                    ['reviewed' => $reviewed, 'item_ids' => $changed, 'employee_count' => count($changed), 'review_note' => trim($input['review_note'] ?? '')],
                    $reviewed ? 'Marked payroll employees reviewed' : 'Marked payroll employees as needing review');
            }

            return count($changed);
        });
    }
}
