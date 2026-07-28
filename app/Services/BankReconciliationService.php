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
            ->get();

        $matchedCount = 0;

        foreach ($unmatched as $statement) {
            $transaction = Transaction::where('bank_account_id', $bankAccountId)
                ->where('amount', $statement->amount)
                ->where('reconciled', false)
                ->whereBetween('transaction_date', [
                    date('Y-m-d', strtotime($statement->statement_date . ' -3 days')),
                    date('Y-m-d', strtotime($statement->statement_date . ' +3 days'))
                ])
                ->first();

            if ($transaction) {
                $statement->update([
                    'is_matched' => true,
                    'matched_transaction_id' => $transaction->id,
                ]);

                $transaction->update(['reconciled' => true]);
                $matchedCount++;
            }
        }

        return [
            'total_processed' => count($unmatched),
            'matched_count' => $matchedCount,
        ];
    }
}
