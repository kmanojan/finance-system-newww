<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanPrincipalCorrectionService
{
    /** Correct the original borrowing, never manufacture a draw or erase a repayment. */
    public static function correct(object $loan, float $amount, string $reason): void
    {
        $cents = fn ($value) => (int) round((float) ($value ?? 0) * 100);
        $fail = fn ($message) => throw ValidationException::withMessages(['principal_amount' => $message]);
        $records = DB::table('loan_principal_records')->where('loan_id', $loan->id)->get();
        $draws = $records->where('record_type', 'draw')->sum(fn ($row) => $cents($row->amount));
        $repaid = $records->where('record_type', 'repayment')->sum(fn ($row) => $cents($row->amount));
        $outstanding = $cents($amount) + $draws - $repaid;
        if ($outstanding < 0) {
            $fail('The corrected principal plus additional draws cannot be less than recorded principal repayments.');
        }
        if (in_array($loan->status, ['settled', 'closed']) && $outstanding > 0) {
            $fail('Reopen this loan before making a correction that leaves principal outstanding.');
        }
        $withheld = ! empty($loan->is_upfront_interest) ? $cents($loan->upfront_interest_amount ?: $loan->interest_amount) : 0;
        if ($cents($amount) <= $withheld) {
            $fail('The corrected principal must exceed the interest withheld upfront.');
        }
        // Existing repayment funding must still fit the proceeds available on each repayment date.
        $links = DB::table('loan_refinancing_links as links')
            ->join('loan_principal_records as records', 'records.id', '=', 'links.principal_record_id')
            ->where('links.source_loan_id', $loan->id)->orderBy('records.record_date')
            ->select('links.amount', 'records.record_date')->get();
        $allocated = 0;
        foreach ($links as $link) {
            $allocated += $cents($link->amount);
            $availableDraws = $records->where('record_type', 'draw')->where('record_date', '<=', $link->record_date)->sum(fn ($row) => $cents($row->amount));
            if ($allocated > $cents($amount) - $withheld + $availableDraws) {
                $fail('This correction would exceed the proceeds already linked to repayments. Correct those funding links first.');
            }
        }

        $transactions = DB::table('transactions')->where('reference_no', 'LOAN-ACT-'.$loan->id)->lockForUpdate()->get();
        if ($transactions->count() > 1) {
            $fail('Multiple original disbursements were found. Reconcile them before correcting the principal.');
        }
        $transaction = $transactions->first();
        $companyId = $transaction
            ? DB::table('departments')->where('id', $transaction->department_id)->value('company_id')
            : DB::table('bank_accounts')->where('id', $loan->bank_account_id)->value('company_id');
        PeriodLockService::checkLockedDate($loan->claimed_date ?: $loan->start_date ?: $loan->created_at ?: now()->toDateString(), $companyId);

        if ($transaction) {
            PeriodLockService::checkLockedDate($transaction->transaction_date, $companyId);
            if ($transaction->reconciled) {
                $fail('Unreconcile the original disbursement before correcting its principal.');
            }
            if ($transaction->type !== 'income' || $transaction->currency !== $loan->currency
                || $cents($transaction->amount) !== $cents($loan->principal_amount) - $withheld) {
                $fail('The original disbursement does not match this loan. Review it before correcting the principal.');
            }
            $posted = DB::table('journal_entries')->where('reference_type', 'Transaction')
                ->where('reference_id', $transaction->id)->where('status', 'posted')->exists();
            if ($posted) {
                TransactionPostingService::reverse($transaction);
            }
            $change = ['amount' => ($cents($amount) - $withheld) / 100, 'updated_at' => now()];
            DB::table('transactions')->where('id', $transaction->id)->update($change);
            if ($posted) {
                TransactionPostingService::post(DB::table('transactions')->find($transaction->id));
            }
            ActivityLogService::logUpdate('Transaction', $transaction->id, $transaction, $change + ['correction_reason' => $reason]);
        }
        // Imported loans may have no linked cash posting. Do not invent a historic bank movement.
        ActivityLogService::log('principal_correction', 'Loan', $loan->id,
            ['principal_amount' => $loan->principal_amount],
            ['principal_amount' => $amount, 'correction_reason' => $reason, 'disbursement_id' => $transaction?->id]);
    }
}
