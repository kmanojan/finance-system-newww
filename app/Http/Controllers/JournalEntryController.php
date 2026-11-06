<?php

namespace App\Http\Controllers;

use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    public function index()
    {

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

        $validated = $request->validate([
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_name' => 'required|string',
            'lines.*.account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string',
        ]);

        $lines = array_map(fn ($line) => array_merge($line, ['currency' => $request->input('currency', 'LKR')]), $validated['lines']);
        LedgerPostingService::postJournal($validated['entry_date'], $validated['description'], $lines);

        return back()->with('success', 'Journal entry posted successfully!');
    }
}
