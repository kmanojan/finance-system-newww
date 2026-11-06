<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\Transaction;

class BankReconciliationService
{
    /**
     * Auto-match bank statement lines with internal transactions.
     */
    public static function autoMatch(int $bankAccountId): array
    {
        $unmatched = BankStatementImport::where('bank_account_id', $bankAccountId)
            ->where('is_matched', false)
            ->lockForUpdate()->get();

        $matchedCount = 0;

        foreach ($unmatched as $statement) {
            $transaction = Transaction::where('bank_account_id', $bankAccountId)
                ->where('amount', $statement->amount)
                ->where('type', $statement->type === 'credit' ? 'income' : 'expense')
                ->where('reconciled', false)
                ->whereBetween('transaction_date', [
                    date('Y-m-d', strtotime($statement->statement_date.' -3 days')),
                    date('Y-m-d', strtotime($statement->statement_date.' +3 days')),
                ])
                ->when($statement->reference_no, fn ($q) => $q->where('reference_no', $statement->reference_no))
                ->limit(2)->lockForUpdate()->get();
            $transaction = $transaction->count() === 1 ? $transaction->first() : null;

            if ($transaction) {
                $statement->update([
                    'is_matched' => true,
                    'matched_transaction_id' => $transaction->id,
                ]);

                $transaction->update(['reconciled' => true, 'reconciled_at' => now()]);
                $matchedCount++;
            }
        }

        return [
            'total_processed' => count($unmatched),
            'matched_count' => $matchedCount,
        ];
    }
}
