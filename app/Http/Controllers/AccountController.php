<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $query = Account::with('children');

        if ($request->has('type') && ! empty($request->type)) {
            $query->where('type', $request->type);
        }

        $accounts = $query->whereNull('parent_id')->orderBy('code')->get();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $accounts]);
        }

        return view('masters.accounts', ['accounts' => Account::orderBy('code')->get(), 'companies' => DB::table('companies')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|unique:accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:accounts,id',
        ]);

        $account = Account::create($validated);

        ActivityLogService::logCreate('Account', $account->id, $account);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Account created.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => $account,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);
        $data = $request->validate(['name' => 'required|string|max:255', 'is_active' => 'required|boolean']);
        $old = $account->toArray();
        $account->update($data);
        ActivityLogService::logUpdate('Account', $id, $old, $data);

        return back()->with('success', 'Account updated.');
    }
}
