<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $query = Account::with('children');

        if ($request->has('type') && !empty($request->type)) {
            $query->where('type', $request->type);
        }

        $accounts = $query->whereNull('parent_id')->orderBy('code')->get();

        return response()->json(['success' => true, 'data' => $accounts]);
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

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => $account
        ], 201);
    }
}
