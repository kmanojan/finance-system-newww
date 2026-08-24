<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('transactions')
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('departments', 'transactions.department_id', '=', 'departments.id')
            ->select(
                'transactions.*',
                'categories.name as category_name',
                'departments.name as department_name'
            )
            ->orderBy('transactions.transaction_date', 'desc')
            ->orderBy('transactions.id', 'desc');

        if ($request->filled('tag_id')) {
            $query->join('taggables', function($join) use ($request) {
                $join->on('transactions.id', '=', 'taggables.taggable_id')
                     ->where('taggables.taggable_type', '=', 'transaction')
                     ->where('taggables.tag_id', '=', $request->tag_id);
            });
        }

        $startDate = $request->input('start_date') ?: $request->input('from');
        $endDate = $request->input('end_date') ?: $request->input('to');

        if (!empty($startDate)) {
            $query->where('transactions.transaction_date', '>=', $startDate);
        }
        
        if (!empty($endDate)) {
            $query->where('transactions.transaction_date', '<=', $endDate);
        }
        
        if ($request->filled('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('transactions.type', '=', $request->type);
        }

        if ($request->filled('category_id')) {
            $query->where('transactions.category_id', '=', $request->category_id);
        }

        if ($request->filled('department_id')) {
            $query->where('transactions.department_id', '=', $request->department_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('transactions.payment_method', '=', $request->payment_method);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function($q) use ($search) {
                $q->where('transactions.description', 'LIKE', $search)
                  ->orWhere('transactions.reference_no', 'LIKE', $search)
                  ->orWhere('categories.name', 'LIKE', $search);
            });
        }

        $transactions = $query->get();

        // Calculate KPI summary figures
        $totalIncome = $transactions->where('type', 'income')->sum('amount');
        $totalExpense = $transactions->where('type', 'expense')->sum('amount');
        $netCashFlow = $totalIncome - $totalExpense;
        $incomeCount = $transactions->where('type', 'income')->count();
        $expenseCount = $transactions->where('type', 'expense')->count();
        $totalCount = $transactions->count();

        // Get categories and related entities for dropdowns
        $categories = DB::table('categories')->orderBy('name')->get();
        $companies = DB::table('companies')->get();
        $departments = DB::table('departments')->orderBy('name')->get();
        $budgetItems = DB::table('budget_items')
            ->join('budget_groups', 'budget_items.budget_group_id', '=', 'budget_groups.id')
            ->join('budgets', 'budget_groups.budget_id', '=', 'budgets.id')
            ->select('budget_items.id', 'budget_items.name as item_name', 'budget_groups.name as group_name', 'budgets.name as budget_name')
            ->get();
        $tags = DB::table('tags')->get();
        $parties = DB::table('parties')->orderBy('name')->get();

        return view('transactions', compact(
            'transactions', 
            'categories', 
            'companies', 
            'departments', 
            'budgetItems', 
            'tags', 
            'parties',
            'totalIncome',
            'totalExpense',
            'netCashFlow',
            'incomeCount',
            'expenseCount',
            'totalCount'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->except('_token', 'budget_item_id', 'tag_ids');
        $data['created_at'] = now();
        $data['updated_at'] = now();
        // default missing fields
        if(empty($data['currency'])) $data['currency'] = DB::table('companies')->value('base_currency') ?? 'LKR';
        if(empty($data['department_id'])) $data['department_id'] = DB::table('departments')->value('id') ?? 1;

        if ($request->filled('budget_item_id')) {
            $itemId = $request->budget_item_id;
            $budgetItem = DB::table('budget_items')
                ->join('budget_groups', 'budget_items.budget_group_id', '=', 'budget_groups.id')
                ->join('budgets', 'budget_groups.budget_id', '=', 'budgets.id')
                ->where('budget_items.id', $itemId)
                ->select('budget_items.name as item_name', 'budget_groups.name as group_name', 'budgets.name as budget_name')
                ->first();
                
            if ($budgetItem) {
                $data['description'] = $data['description'] . " ({$budgetItem->budget_name} > {$budgetItem->group_name} > {$budgetItem->item_name})";
            }
        }
        
        $transactionId = DB::table('transactions')->insertGetId($data);

        if ($request->filled('budget_item_id')) {
            DB::table('budget_transactions')->insert([
                'budget_item_id' => $request->budget_item_id,
                'transaction_id' => $transactionId
            ]);
        }

        if ($request->has('tag_ids') && is_array($request->tag_ids)) {
            $taggables = [];
            foreach ($request->tag_ids as $tagId) {
                $taggables[] = [
                    'tag_id' => $tagId,
                    'taggable_id' => $transactionId,
                    'taggable_type' => 'transaction'
                ];
            }
            if (!empty($taggables)) {
                DB::table('taggables')->insert($taggables);
            }
        }

        \App\Services\ActivityLogService::logCreate('Transaction', $transactionId, [
            'type' => $data['type'] ?? null,
            'amount' => $data['amount'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Transaction created successfully!');
    }
    public function update(Request $request, $id)
    {
        $oldTx = DB::table('transactions')->where('id', $id)->first();
        $data = $request->only(['amount', 'description', 'category_id', 'type', 'payment_method', 'transaction_date', 'reference_no', 'department_id']);
        $data['updated_at'] = now();
        
        DB::table('transactions')->where('id', $id)->update($data);

        \App\Services\ActivityLogService::logUpdate('Transaction', $id, $oldTx, $data);
        
        return back()->with('success', 'Transaction updated successfully!');
    }

    public function destroy($id)
    {
        $oldTx = DB::table('transactions')->where('id', $id)->first();
        DB::table('transactions')->where('id', $id)->delete();

        \App\Services\ActivityLogService::logDelete('Transaction', $id, $oldTx);

        return back()->with('success', 'Transaction deleted successfully!');
    }
}

