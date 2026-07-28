<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JournalEntryController extends Controller
{
    private function ensureTablesExist()
    {
        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function ($table) {
                $table->id();
                $table->integer('reference_id')->nullable();
                $table->string('reference_type', 255)->nullable();
                $table->date('entry_date');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function ($table) {
                $table->id();
                $table->integer('journal_entry_id');
                $table->string('account_name', 255);
                $table->string('account_type', 50); // asset, liability, equity, revenue, expense
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->string('currency', 10)->default('LKR');
                $table->timestamps();
            });
        }
    }

    public function index()
    {
        $this->ensureTablesExist();

        $entries = DB::table('journal_entries')
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($entries as $entry) {
            $entry->lines = DB::table('journal_entry_lines')
                ->where('journal_entry_id', $entry->id)
                ->get();
        }

        return view('journal-entries', compact('entries'));
    }

    public function store(Request $request)
    {
        $this->ensureTablesExist();

        $validated = $request->validate([
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_name' => 'required|string',
            'lines.*.account_type' => 'required|string',
            'lines.*.debit' => 'nullable|numeric',
            'lines.*.credit' => 'nullable|numeric',
            'currency' => 'nullable|string',
        ]);

        $entryId = DB::table('journal_entries')->insertGetId([
            'entry_date' => $validated['entry_date'],
            'description' => $validated['description'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($validated['lines'] as $line) {
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $entryId,
                'account_name' => $line['account_name'],
                'account_type' => $line['account_type'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'currency' => $request->input('currency', 'LKR'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Journal entry posted successfully!');
    }

    public static function postDoubleEntry($entryDate, $description, $debitAccount, $debitType, $debitAmount, $creditAccount, $creditType, $creditAmount, $currency = 'LKR', $refType = null, $refId = null)
    {
        if (!Schema::hasTable('journal_entries')) return;

        $entryId = DB::table('journal_entries')->insertGetId([
            'reference_type' => $refType,
            'reference_id' => $refId,
            'entry_date' => $entryDate,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $entryId,
                'account_name' => $debitAccount,
                'account_type' => $debitType,
                'debit' => $debitAmount,
                'credit' => 0,
                'currency' => $currency,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $entryId,
                'account_name' => $creditAccount,
                'account_type' => $creditType,
                'debit' => 0,
                'credit' => $creditAmount,
                'currency' => $currency,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
