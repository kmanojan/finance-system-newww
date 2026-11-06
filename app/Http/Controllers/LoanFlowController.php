<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\LoanFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanFlowController extends Controller
{
    public function store(Request $request, int $id)
    {
        $data = $request->validate([
            'principal_record_id' => 'required|integer', 'source_loan_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01|decimal:0,2', 'notes' => 'nullable|string|max:1000',
        ]);
        LoanFlowService::link($id, $data['principal_record_id'], $data['source_loan_id'], $data['amount'], $data['notes'] ?? null);

        return redirect('/loans/'.$id.'#loan-flow')->with('success', 'Funding loan linked to the existing repayment. No new payment was posted.');
    }

    public function destroy(int $id, int $linkId)
    {
        $link = DB::table('loan_refinancing_links as links')->join('loan_principal_records as records', 'records.id', '=', 'links.principal_record_id')
            ->where('links.id', $linkId)->where('records.loan_id', $id)->select('links.*')->first();
        abort_unless($link, 404);
        ActivityLogService::logDelete('LoanRefinancingLink', $linkId, $link);
        DB::table('loan_refinancing_links')->where('id', $linkId)->delete();

        return redirect('/loans/'.$id.'#loan-flow')->with('success', 'Funding link removed. The recorded repayment is unchanged.');
    }
}
