<?php

namespace App\Http\Controllers;

use App\Models\BankStatementImport;
use App\Models\Transaction;
use App\Services\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $bankAccountId = $request->input('bank_account_id');
        $query = BankStatementImport::with(['bankAccount', 'matchedTransaction']);

        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        $statements = $query->orderBy('statement_date', 'desc')->get();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'data' => $statements]);
        }

        $bankAccounts = DB::table('bank_accounts')->get();

        $transactions = DB::table('transactions')->where('reconciled', false)->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))->orderByDesc('transaction_date')->limit(500)->get();

        return view('treasury.bank_reconciliation', compact('statements', 'bankAccounts', 'transactions'));
    }

    public function autoMatch(Request $request)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
        ]);

        $res = BankReconciliationService::autoMatch($validated['bank_account_id']);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => "Auto-matching completed. Processed: {$res['total_processed']}, Matched: {$res['matched_count']}.",
                'data' => $res,
            ]);
        }

        return back()->with('success', "Auto-matching completed. Processed: {$res['total_processed']}, Matched: {$res['matched_count']}.");
    }

    public function import(Request $request)
    {
        $request->validate(['bank_account_id' => 'required|exists:bank_accounts,id', 'statement' => 'required|file|max:5120']);
        $file = $request->file('statement');
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];
        $content = file_get_contents($file->getRealPath());
        if ($extension === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle, 0, ',', '"', '');
            $header = array_map(fn ($key) => strtolower(trim($key, "\xEF\xBB\xBF \t\n\r")), $header ?: []);
            if (array_diff(['date', 'amount', 'type'], $header)) {
                throw ValidationException::withMessages(['statement' => 'CSV requires date, amount and type columns; reference and description are optional.']);
            }
            while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($line === [null]) {
                    continue;
                }
                if (count($line) !== count($header)) {
                    throw ValidationException::withMessages(['statement' => 'A CSV row does not match its header.']);
                }
                $rows[] = array_combine($header, $line);
            }
            fclose($handle);
        } elseif (in_array($extension, ['ofx', 'qfx'])) {
            preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $content, $blocks);
            foreach ($blocks[1] as $block) {
                $tag = function ($name) use ($block) {
                    preg_match('/<'.$name.'>([^<\r\n]+)/i', $block, $match);

                    return trim($match[1] ?? '');
                };
                $amount = (float) $tag('TRNAMT');
                $date = substr($tag('DTPOSTED'), 0, 8);
                $rows[] = ['date' => substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2), 'amount' => abs($amount), 'type' => $amount >= 0 ? 'credit' : 'debit', 'reference' => $tag('FITID'), 'description' => $tag('MEMO') ?: $tag('NAME')];
            }
        } else {
            throw ValidationException::withMessages(['statement' => 'Upload a CSV, OFX or QFX statement.']);
        }
        if (! $rows || count($rows) > 5000) {
            throw ValidationException::withMessages(['statement' => 'The statement must contain between 1 and 5,000 rows.']);
        }
        $count = 0;
        foreach ($rows as $index => $row) {
            $data = validator($row, ['date' => 'required|date_format:Y-m-d', 'amount' => 'required|numeric|gt:0', 'type' => 'required|in:credit,debit', 'reference' => 'nullable|string|max:255', 'description' => 'nullable|string|max:255'])->validate();
            $fingerprint = hash('sha256', $request->bank_account_id.'|'.hash('sha256', $content).'|'.$index);
            if (DB::table('bank_statement_imports')->where('source_fingerprint', $fingerprint)->exists()) {
                continue;
            }
            DB::table('bank_statement_imports')->insert(['bank_account_id' => $request->bank_account_id, 'statement_date' => $data['date'], 'amount' => $data['amount'], 'type' => $data['type'], 'reference_no' => $data['reference'] ?? null, 'description' => $data['description'] ?? null, 'source_fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            $count++;
        }

        return back()->with('success', "Imported {$count} statement rows. Duplicate imports were skipped.");
    }

    public function match(Request $request, $id)
    {
        $request->validate(['transaction_id' => 'required|exists:transactions,id']);
        $statement = BankStatementImport::whereKey($id)->lockForUpdate()->firstOrFail();
        $transaction = Transaction::whereKey($request->transaction_id)->lockForUpdate()->firstOrFail();
        if ($statement->is_matched || $transaction->reconciled || $statement->bank_account_id != $transaction->bank_account_id || round($statement->amount, 2) !== round($transaction->amount, 2) || ($statement->type === 'credit' ? 'income' : 'expense') !== $transaction->type) {
            throw ValidationException::withMessages(['transaction_id' => 'Choose an unmatched transaction with the same bank account, amount and direction.']);
        }
        $statement->update(['is_matched' => true, 'matched_transaction_id' => $transaction->id]);
        $transaction->update(['reconciled' => true, 'reconciled_at' => now()]);

        return back()->with('success', 'Statement row matched.');
    }

    public function unmatch(Request $request, $id)
    {
        $statement = BankStatementImport::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($statement->matched_transaction_id) {
            Transaction::whereKey($statement->matched_transaction_id)->update(['reconciled' => false, 'reconciled_at' => null]);
        }
        $statement->update(['is_matched' => false, 'matched_transaction_id' => null]);

        return back()->with('success', 'Match removed.');
    }
}
