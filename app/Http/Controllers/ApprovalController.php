<?php

namespace App\Http\Controllers;

use App\Services\InvoicePostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        // 1. Draft Invoices pending approval
        $pendingInvoices = collect();
        if (Schema::hasTable('invoices')) {
            $pendingInvoices = DB::table('invoices')
                ->leftJoin('projects', 'invoices.project_id', '=', 'projects.id')
                ->where('invoices.status', 'pending_approval')
                ->select('invoices.*', 'projects.name as project_name')
                ->get();
        }

        // 2. Over-budget Cost Allocations
        $pendingCostAllocations = collect();
        if (Schema::hasTable('cost_allocations')) {
            $pendingCostAllocations = DB::table('cost_allocations')
                ->join('projects', 'cost_allocations.project_id', '=', 'projects.id')
                ->whereNull('cost_allocations.deleted_at')->where('cost_allocations.status', 'pending_approval')
                ->select('cost_allocations.*', 'projects.name as project_name')
                ->get();
        }

        // 3. Pending Loan Closures / Settlements
        $pendingLoans = collect();
        if (Schema::hasTable('loans')) {
            $pendingLoans = DB::table('loans')
                ->where('status', 'pending_settlement')
                ->get();
        }

        foreach ($pendingLoans as $loan) {
            $loan->outstanding_principal = $loan->principal_amount + DB::table('loan_principal_records')->where('loan_id', $loan->id)->where('record_type', 'draw')->sum('amount') - DB::table('loan_principal_records')->where('loan_id', $loan->id)->where('record_type', 'repayment')->sum('amount');
        }
        $totalPendingCount = $pendingInvoices->count() + $pendingCostAllocations->count() + $pendingLoans->count();

        return view('approvals', compact('pendingInvoices', 'pendingCostAllocations', 'pendingLoans', 'totalPendingCount'));
    }

    public function approve(Request $request, $type, $id)
    {
        if ($type === 'invoice') {
            abort_unless(DB::table('invoices')->where('id', $id)->where('status', 'pending_approval')->exists(), 422);
            DB::table('invoices')->where('id', $id)->update([
                'status' => 'sent',
                'updated_at' => now(),
            ]);
            InvoicePostingService::sync($id);

            return back()->with('success', 'Invoice approved and marked as Sent!');
        } elseif ($type === 'loan') {
            $loan = DB::table('loans')->where('id', $id)->where('status', 'pending_settlement')->lockForUpdate()->first();
            abort_unless($loan, 422);
            $principal = $loan->principal_amount + DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'draw')->sum('amount') - DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'repayment')->sum('amount');
            $interest = DB::table('loan_interest_schedule')->where('loan_id', $id)->whereNotIn('status', ['paid', 'skipped'])->sum(DB::raw('interest_amount - paid_amount'));
            if ($principal > 0.005 || $interest > 0.005) {
                throw ValidationException::withMessages(['loan' => 'Outstanding principal and interest must be paid before closure approval.']);
            }
            DB::table('loans')->where('id', $id)->update([
                'status' => 'settled',
                'updated_at' => now(),
            ]);

            return back()->with('success', 'Loan closure approved and marked as Settled!');
        }

        if ($type === 'cost_allocation') {
            DB::table('cost_allocations')->where('id', $id)->where('status', 'pending_approval')->update(['status' => 'approved']);

            return back()->with('success', 'Cost allocation approved.');
        }

        return back()->with('error', 'Unknown approval request type.');
    }

    public function reject(Request $request, $type, $id)
    {
        if ($type === 'invoice') {
            abort_unless(DB::table('invoices')->where('id', $id)->where('status', 'pending_approval')->exists(), 422);
            DB::table('invoices')->where('id', $id)->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);

            return back()->with('success', 'Invoice rejected.');
        } elseif ($type === 'loan') {
            DB::table('loans')->where('id', $id)->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);

            return back()->with('success', 'Loan settlement request rejected.');
        }

        if ($type === 'cost_allocation') {
            DB::table('cost_allocations')->where('id', $id)->where('status', 'pending_approval')->update(['status' => 'rejected']);

            return back()->with('success', 'Cost allocation rejected.');
        }

        return back()->with('error', 'Unknown rejection request type.');
    }
}
