<?php

namespace App\Http\Controllers;

use App\Models\BankStatementImport;
use App\Services\BankReconciliationService;
use Illuminate\Http\Request;

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
        return response()->json(['success' => true, 'data' => $statements]);
    }

    public function autoMatch(Request $request)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
        ]);

        $res = BankReconciliationService::autoMatch($validated['bank_account_id']);

        return response()->json([
            'success' => true,
            'message' => "Auto-matching completed. Processed: {$res['total_processed']}, Matched: {$res['matched_count']}.",
            'data' => $res
        ]);
    }
}
