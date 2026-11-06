<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChequeController extends Controller
{
    public function index(Request $request)
    {

        $statusFilter = $request->query('status');

        $query = DB::table('cheques');
        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $cheques = $query->orderBy('cheque_date', 'asc')->get();

        $pendingCount = DB::table('cheques')->where('status', 'pending_deposit')->count();
        $depositedCount = DB::table('cheques')->where('status', 'deposited')->count();
        $clearedCount = DB::table('cheques')->where('status', 'cleared')->count();
        $bouncedCount = DB::table('cheques')->where('status', 'bounced')->count();

        return view('cheques', compact('cheques', 'statusFilter', 'pendingCount', 'depositedCount', 'clearedCount', 'bouncedCount'));
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'cheque_number' => 'required|string|max:100',
            'cheque_date' => 'required|date',
            'bank_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
        ]);

        DB::table('cheques')->insert([
            'cheque_number' => $validated['cheque_number'],
            'cheque_date' => $validated['cheque_date'],
            'bank_name' => $validated['bank_name'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'LKR',
            'status' => 'pending_deposit',
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Cheque record added successfully!');
    }

    public function updateStatus(Request $request, $id)
    {

        $status = $request->input('status', 'cleared'); // 'deposited', 'cleared', 'bounced'

        DB::table('cheques')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Cheque status updated to '.ucfirst(str_replace('_', ' ', $status)).'!');
    }

    public function destroy($id)
    {
        DB::table('cheques')->where('id', $id)->delete();

        return back()->with('success', 'Cheque record deleted!');
    }
}
