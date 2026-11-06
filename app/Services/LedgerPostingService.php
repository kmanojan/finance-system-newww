<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FinancialYear;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LedgerPostingService
{
    public static function postJournal(string $entryDate, string $description, array $lines, ?int $referenceId = null, ?string $referenceType = null, ?int $companyId = null, bool $yearEndClose = false): JournalEntry
    {
        $companyId ??= DB::table('companies')->value('id');
        if ($yearEndClose) {
            $year = FinancialYear::whereKey($referenceId)->where('company_id', $companyId)->whereDate('end_date', $entryDate)->where('is_closed', false)->first();
            if ($referenceType !== 'YearEndClose' || ! $year || ! $year->periods()->exists() || $year->periods()->where('status', '!=', 'hard_closed')->exists()) {
                throw ValidationException::withMessages(['year' => 'Invalid year-end closing request.']);
            }
        } else {
            PeriodLockService::checkLockedDate($entryDate, $companyId);
        }
        $debit = $credit = 0;
        $currencies = [];
        foreach ($lines as $line) {
            $d = round((float) ($line['debit'] ?? 0) * 100);
            $c = round((float) ($line['credit'] ?? 0) * 100);
            if ($d < 0 || $c < 0 || ($d > 0 && $c > 0)) {
                throw ValidationException::withMessages(['lines' => 'Each line must have one positive debit or credit.']);
            }
            $debit += $d;
            $credit += $c;
            $currencies[] = $line['currency'] ?? 'LKR';
        }
        if (count($lines) < 2 || $debit <= 0 || $debit !== $credit || count(array_unique($currencies)) !== 1) {
            throw ValidationException::withMessages(['lines' => 'Debits and credits must balance in the same currency.']);
        }

        return DB::transaction(function () use ($entryDate, $description, $lines, $referenceId, $referenceType, $companyId) {
            $entry = JournalEntry::create([
                'entry_no' => 'JE-'.Str::ulid(), 'entry_date' => $entryDate,
                'description' => $description, 'reference_id' => $referenceId,
                'reference_type' => $referenceType, 'created_at' => now(),
            ]);
            foreach ($lines as $line) {
                $account = self::account($line, $companyId);
                $entry->lines()->create([
                    'account_id' => $account->id, 'account_name' => $account->name,
                    'account_type' => $account->type, 'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0, 'currency' => $line['currency'] ?? 'LKR',
                    'created_at' => now(),
                ]);
            }
            ActivityLogService::logCreate('JournalEntry', $entry->id, ['entry_no' => $entry->entry_no, 'description' => $description]);

            return $entry;
        });
    }

    private static function account(array $line, ?int $companyId): Account
    {
        $companyId ??= DB::table('companies')->value('id');
        if (! $companyId) {
            throw ValidationException::withMessages(['company' => 'Create a company before posting journals.']);
        }
        if (! empty($line['account_id'])) {
            $account = Account::where('company_id', $companyId)->where('is_active', true)->find($line['account_id']);
            if (! $account) {
                throw ValidationException::withMessages(['lines' => 'Choose an active account in this company.']);
            }

            return $account;
        }
        $defaults = [
            '1010' => ['Cash and bank', 'asset'], '1200' => ['Accounts receivable', 'asset'],
            '1800' => ['Accumulated depreciation', 'asset'], '2100' => ['Accounts payable', 'liability'],
            '2200' => ['Loan principal', 'liability'], '4100' => ['Revenue', 'revenue'],
            '5200' => ['Operating expenses', 'expense'], '5800' => ['Depreciation expense', 'expense'],
        ];
        $code = $line['account_code'] ?? 'MAN-'.substr(hash('sha256', ($line['account_name'] ?? '').($line['account_type'] ?? '')), 0, 10);
        [$name, $type] = $defaults[$code] ?? [$line['account_name'] ?? 'General account', $line['account_type'] ?? 'asset'];
        $existing = Account::where('company_id', $companyId)->where('code', $code)->first();
        if ($existing) {
            if (! $existing->is_active) {
                throw ValidationException::withMessages(['lines' => "Account {$code} is inactive."]);
            }

            return $existing;
        }
        if (Account::where('code', $code)->exists()) {
            $code = $companyId.'-'.$code;
        }

        return Account::firstOrCreate(['company_id' => $companyId, 'code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true]);
    }
}
