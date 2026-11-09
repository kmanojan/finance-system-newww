<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanInterestPaymentService
{
    public static function note(object $payment): string
    {
        $description = (string) $payment->description;
        $parts = explode("\nNote: ", $description, 2);

        return $parts[1] ?? (str_starts_with($description, 'Interest Payment to ') ? '' : $description);
    }

    /** A null change deletes one payment; the scheduled obligation remains. */
    public static function change(int $loanId, int $scheduleId, int $paymentId, ?array $change): void
    {
        DB::transaction(function () use ($loanId, $scheduleId, $paymentId, $change) {
            $loan = DB::table('loans')->where('id', $loanId)->lockForUpdate()->first();
            abort_unless($loan, 404);
            $schedule = DB::table('loan_interest_schedule')->where('loan_id', $loanId)->where('id', $scheduleId)->lockForUpdate()->first();
            abort_unless($schedule, 404);
            $reference = "LOAN-INT-{$loanId}-{$scheduleId}";
            $payments = DB::table('transactions')->where('reference_no', $reference)->where('type', 'expense')->lockForUpdate()->get();
            $payment = $payments->firstWhere('id', $paymentId);
            abort_unless($payment, 404);
            $fail = fn ($message) => throw ValidationException::withMessages(['payment' => $message]);
            $cents = fn ($amount) => (int) round((float) $amount * 100);
            if ($payment->reconciled || DB::table('bank_statement_imports')->where('matched_transaction_id', $paymentId)->exists()) {
                $fail('Unreconcile this interest payment before editing or deleting it.');
            }
            if (DB::table('budget_transactions')->where('transaction_id', $paymentId)->exists()
                || DB::table('cheques')->where('transaction_id', $paymentId)->exists()) {
                $fail('This payment has a linked budget or cheque record. Resolve that link before editing or deleting it.');
            }
            if ($payments->contains(fn ($row) => $row->currency !== $loan->currency || ($row->payment_status ?? 'completed') !== 'completed')
                || $payments->sum(fn ($row) => $cents($row->amount)) !== $cents($schedule->paid_amount)) {
                $fail('The linked payments do not match the interest schedule. Reconcile the payment history before changing it.');
            }
            $companyId = DB::table('departments')->where('id', $payment->department_id)->value('company_id');
            PeriodLockService::checkLockedDate($payment->transaction_date, $companyId);
            $newPaid = $cents($schedule->paid_amount) - $cents($payment->amount) + ($change ? $cents($change['paid_amount']) : 0);
            if ($newPaid < 0 || $newPaid > $cents($schedule->interest_amount)) {
                $fail('The edited payment cannot exceed the scheduled interest after other payments.');
            }
            if ($change) {
                PeriodLockService::checkLockedDate($change['paid_date'], $companyId);
                if (! empty($change['bank_account_id']) && ! DB::table('bank_accounts')->where('id', $change['bank_account_id'])
                    ->where('company_id', $companyId)->where('currency', $loan->currency)->exists()) {
                    $fail('Choose a bank account in the payment company and currency.');
                }
            }
            $posted = DB::table('journal_entries')->where('reference_type', 'Transaction')->where('reference_id', $paymentId)->where('status', 'posted')->exists();
            if ($posted) {
                TransactionPostingService::reverse($payment);
            }
            if ($change) {
                $notes = LoanSettlementNoteService::clean($change['notes'] ?? null);
                $data = ['amount' => $cents($change['paid_amount']) / 100, 'transaction_date' => $change['paid_date'],
                    'bank_account_id' => $change['bank_account_id'] ?: null,
                    'description' => "Interest Payment to {$loan->lender_name}".($notes !== '' ? "\nNote: {$notes}" : ''), 'updated_at' => now()];
                DB::table('transactions')->where('id', $paymentId)->update($data);
                if ($posted) {
                    TransactionPostingService::post(DB::table('transactions')->find($paymentId));
                }
                ActivityLogService::logUpdate('Transaction', $paymentId, $payment, $data);
            } else {
                DB::table('taggables')->where('taggable_type', 'transaction')->where('taggable_id', $paymentId)->delete();
                DB::table('transactions')->where('id', $paymentId)->delete();
                ActivityLogService::logDelete('Transaction', $paymentId, $payment);
            }
            $scheduleChange = ['paid_amount' => $newPaid / 100,
                'paid_date' => DB::table('transactions')->where('reference_no', $reference)->where('type', 'expense')->max('transaction_date'),
                'status' => $newPaid >= $cents($schedule->interest_amount) ? 'paid' : ($newPaid > 0 ? 'partially_paid' : 'pending'),
                'updated_at' => now()];
            DB::table('loan_interest_schedule')->where('id', $scheduleId)->update($scheduleChange);
            ActivityLogService::logUpdate('LoanInterestSchedule', $scheduleId, $schedule, $scheduleChange);
            if ($newPaid < $cents($schedule->interest_amount) && in_array($loan->status, ['settled', 'closed'])) {
                DB::table('loans')->where('id', $loanId)->update(['status' => 'active', 'updated_at' => now()]);
                ActivityLogService::logUpdate('Loan', $loanId, ['status' => $loan->status], ['status' => 'active']);
            }
        });
    }
}
