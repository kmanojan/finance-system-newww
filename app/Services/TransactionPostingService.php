<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionPostingService
{
    public static function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            validator($data, ['amount' => 'required|numeric|gt:0', 'type' => 'required|in:income,expense', 'transaction_date' => 'required|date_format:Y-m-d', 'category_id' => 'required|exists:categories,id', 'department_id' => 'required|exists:departments,id', 'currency' => 'required|string|size:3'])->validate();
            $companyId = DB::table('departments')->where('id', $data['department_id'])->value('company_id');
            if (! empty($data['bank_account_id']) && ! DB::table('bank_accounts')->where('id', $data['bank_account_id'])->where('company_id', $companyId)->where('currency', $data['currency'])->exists()) {
                throw ValidationException::withMessages(['bank_account_id' => 'Choose a bank account in the transaction company and currency.']);
            }
            PeriodLockService::checkLockedDate($data['transaction_date'], $companyId);
            $id = DB::table('transactions')->insertGetId($data);
            self::post((object) array_merge($data, ['id' => $id]));

            return $id;
        });
    }

    public static function post(object $transaction): void
    {
        if (($transaction->payment_status ?? 'completed') !== 'completed') {
            return;
        }
        $category = DB::table('categories')->find($transaction->category_id);
        $companyId = DB::table('departments')->where('id', $transaction->department_id)->value('company_id');
        if (! empty($transaction->bank_account_id) && ! DB::table('bank_accounts')->where('id', $transaction->bank_account_id)->where('currency', $transaction->currency)->where('company_id', $companyId)->exists()) {
            throw ValidationException::withMessages(['bank_account_id' => 'Bank account must match the transaction company and currency.']);
        }
        $principal = in_array($category->name, ['Loan Disbursement', 'Loan Principal Repayment']);
        $counter = $principal ? '2200' : ($transaction->type === 'income' ? '4100' : '5200');
        $cash = ['account_code' => '1010', 'currency' => $transaction->currency];
        $other = ['account_code' => $counter, 'currency' => $transaction->currency];
        $cash[$transaction->type === 'income' ? 'debit' : 'credit'] = $transaction->amount;
        $other[$transaction->type === 'income' ? 'credit' : 'debit'] = $transaction->amount;
        LedgerPostingService::postJournal($transaction->transaction_date, $transaction->description ?? 'Cash transaction', [$cash, $other], $transaction->id, 'Transaction', $companyId);
    }

    public static function reverse(object $transaction): void
    {
        $entries = JournalEntry::with('lines')->where('reference_type', 'Transaction')->where('reference_id', $transaction->id)->where('status', 'posted')->get();
        foreach ($entries as $entry) {
            LedgerPostingService::postJournal($transaction->transaction_date, 'Reversal: '.$entry->description, $entry->lines->map(fn ($line) => ['account_id' => $line->account_id, 'currency' => $line->currency, 'debit' => $line->credit, 'credit' => $line->debit])->all(), $entry->id, 'Reversal', DB::table('departments')->where('id', $transaction->department_id)->value('company_id'));
            $entry->update(['status' => 'reversed']);
        }
    }
}
