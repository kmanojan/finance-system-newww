<?php

namespace App\Services;

use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class YearEndCloseService
{
    public static function close(int $id): void
    {
        DB::transaction(function () use ($id) {
            $year = FinancialYear::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($year->is_closed || ! $year->periods()->exists() || $year->periods()->where('status', '!=', 'hard_closed')->exists()) {
                throw ValidationException::withMessages(['year' => 'Hard-close every period before closing an open financial year.']);
            }
            $balances = DB::table('journal_entry_lines as lines')->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')->join('accounts', 'accounts.id', '=', 'lines.account_id')
                ->where('accounts.company_id', $year->company_id)->whereIn('accounts.type', ['revenue', 'expense'])
                ->whereDate('entries.entry_date', '>=', $year->start_date->toDateString())->whereDate('entries.entry_date', '<=', $year->end_date->toDateString())
                ->select('lines.account_id', 'lines.currency', DB::raw('SUM(lines.debit-lines.credit) as balance'))->groupBy('lines.account_id', 'lines.currency')->get()->groupBy('currency');
            foreach ($balances as $currency => $accounts) {
                $lines = [];
                $net = 0;
                foreach ($accounts as $account) {
                    $balance = round($account->balance, 2);
                    if (abs($balance) < 0.005) {
                        continue;
                    }
                    $lines[] = ['account_id' => $account->account_id, 'currency' => $currency, 'debit' => max(0, -$balance), 'credit' => max(0, $balance)];
                    $net -= $balance;
                }
                if (! $lines) {
                    continue;
                }
                if (abs($net) > 0.005) {
                    $lines[] = ['account_code' => '3100', 'account_name' => 'Retained earnings', 'account_type' => 'equity', 'currency' => $currency, 'debit' => max(0, -$net), 'credit' => max(0, $net)];
                }
                LedgerPostingService::postJournal($year->end_date->toDateString(), 'Year-end close: '.$year->title, $lines, $year->id, 'YearEndClose', $year->company_id, true);
            }
            $year->update(['is_closed' => true, 'closed_at' => now(), 'closed_by' => auth()->id()]);
            ActivityLogService::logUpdate('FinancialYear', $year->id, ['is_closed' => false], ['is_closed' => true]);
        });
    }
}
