<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChequeController extends Controller
{
    private function ensureTableExists()
    {
        if (!Schema::hasTable('cheques')) {
            Schema::create('cheques', function ($table) {
                $table->id();
                $table->integer('transaction_id')->nullable();
                $table->string('cheque_number', 100);
                $table->date('cheque_date');
                $table->string('bank_name', 255);
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10)->default('LKR');
                $table->string('status', 50)->default('pending_deposit');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function index(Request $request)
    {
        $this->ensureTableExists();

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
        $this->ensureTableExists();

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
        $this->ensureTableExists();

        $status = $request->input('status', 'cleared'); // 'deposited', 'cleared', 'bounced'

        DB::table('cheques')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        return back()->with('success', "Cheque status updated to " . ucfirst(str_replace('_', ' ', $status)) . "!");
    }

    public function destroy($id)
    {
        $this->ensureTableExists();
        DB::table('cheques')->where('id', $id)->delete();
        return back()->with('success', 'Cheque record deleted!');
    }
}
