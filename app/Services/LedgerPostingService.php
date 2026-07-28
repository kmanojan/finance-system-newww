<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;

class LedgerPostingService
{
    /**
     * Post balanced double-entry journal lines.
     */
    public static function postJournal(
        string $entryDate,
        string $description,
        array $lines,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): JournalEntry {
        PeriodLockService::checkLockedDate($entryDate);

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit += $line['debit'] ?? 0;
            $totalCredit += $line['credit'] ?? 0;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception("Journal entry is not balanced. Total Debit: {$totalDebit}, Total Credit: {$totalCredit}");
        }

        $entry = JournalEntry::create([
            'entry_date' => $entryDate,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
        ]);

        foreach ($lines as $line) {
            $account = null;
            if (!empty($line['account_code'])) {
                $account = Account::where('code', $line['account_code'])->first();
            }

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account?->id,
                'account_name' => $account?->name ?? $line['account_name'] ?? 'General Account',
                'account_type' => $account?->type ?? $line['account_type'] ?? 'asset',
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'currency' => $line['currency'] ?? 'LKR',
            ]);
        }

        return $entry;
    }
}
