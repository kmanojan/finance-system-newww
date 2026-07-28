<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        // 1. Draft Invoices pending approval
        $pendingInvoices = collect();
        if (Schema::hasTable('invoices')) {
            $pendingInvoices = DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->where('invoices.status', 'pending_approval')
                ->select('invoices.*', 'projects.name as project_name')
                ->get();
        }

        // 2. Over-budget Cost Allocations
        $pendingCostAllocations = collect();
        if (Schema::hasTable('cost_allocations')) {
            $pendingCostAllocations = DB::table('cost_allocations')
                ->join('projects', 'cost_allocations.project_id', '=', 'projects.id')
                ->where('cost_allocations.amount', '>', 500000)
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

        $totalPendingCount = $pendingInvoices->count() + $pendingCostAllocations->count() + $pendingLoans->count();

        return view('approvals', compact('pendingInvoices', 'pendingCostAllocations', 'pendingLoans', 'totalPendingCount'));
    }

    public function approve(Request $request, $type, $id)
    {
        if ($type === 'invoice') {
            DB::table('invoices')->where('id', $id)->update([
                'status' => 'sent',
                'updated_at' => now(),
            ]);
            return back()->with('success', 'Invoice approved and marked as Sent!');
        } elseif ($type === 'loan') {
            DB::table('loans')->where('id', $id)->update([
                'status' => 'settled',
                'updated_at' => now(),
            ]);
            return back()->with('success', 'Loan closure approved and marked as Settled!');
        }

        return back()->with('error', 'Unknown approval request type.');
    }

    public function reject(Request $request, $type, $id)
    {
        if ($type === 'invoice') {
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

        return back()->with('error', 'Unknown rejection request type.');
    }
}
