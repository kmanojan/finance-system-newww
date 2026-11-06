<?php

namespace App\Console\Commands;

use App\Services\FinancialReportingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLedger extends Command
{
    protected $signature = 'finance:audit-ledger';

    protected $description = 'Read-only checks for unposted legacy records and unbalanced journals';

    public function handle(): int
    {
        $unposted = FinancialReportingService::unpostedCount();
        $imbalances = DB::table('journal_entry_lines')->select('journal_entry_id', DB::raw('SUM(debit) as debits'), DB::raw('SUM(credit) as credits'))->groupBy('journal_entry_id')->havingRaw('ABS(SUM(debit)-SUM(credit)) > 0.005')->get();
        $missing = DB::table('journal_entry_lines')->leftJoin('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')->whereNull('accounts.id')->count();
        $this->table(['Check', 'Count'], [['Issued invoices / standalone cash records without posting', $unposted], ['Unbalanced journals', $imbalances->count()], ['Lines without an account', $missing]]);
        foreach ($imbalances as $entry) {
            $this->line('Journal '.$entry->journal_entry_id.': debit '.$entry->debits.', credit '.$entry->credits);
        }
        $this->comment('This diagnostic is not a complete historical reconciliation. Review opening bank balances, project receipts, vendor liabilities and previous manual journals before a controlled ledger migration. No records were changed.');

        return ($unposted || $imbalances->isNotEmpty() || $missing) ? self::FAILURE : self::SUCCESS;
    }
}
