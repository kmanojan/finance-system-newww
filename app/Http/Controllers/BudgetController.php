<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetController extends Controller
{
    public function index()
    {
        $budgets = DB::table('budgets')->orderBy('created_at', 'desc')->get();
        
        foreach ($budgets as $budget) {
            // Get all items in this budget
            $items = DB::table('budget_items')
                ->join('budget_groups', 'budget_items.budget_group_id', '=', 'budget_groups.id')
                ->where('budget_groups.budget_id', $budget->id)
                ->get();
                
            $allocated = 0;
            $spent = 0;
            
            foreach ($items as $item) {
                $allocated += $item->allocated_amount;
                $itemSpent = DB::table('budget_transactions')
                    ->join('transactions', 'budget_transactions.transaction_id', '=', 'transactions.id')
                    ->where('budget_transactions.budget_item_id', $item->id)
                    ->sum('transactions.amount');
                $spent += $itemSpent;
            }
            
            $budget->allocated_amount = $allocated;
            $budget->actual_spent = $spent;
            $budget->percent_used = $allocated > 0 ? round(($spent / $allocated) * 100, 2) : 0;
            $budget->remaining = $allocated - $spent;
            
            if ($budget->percent_used >= 100) {
                $budget->status_class = '#fee2e2'; // Red
                $budget->status_text_class = '#991b1b';
                $budget->status_label = 'Over Budget';
            } elseif ($budget->percent_used >= 80) {
                $budget->status_class = '#fef3c7'; // Yellow
                $budget->status_text_class = '#92400e';
                $budget->status_label = 'Near Limit';
            } else {
                $budget->status_class = '#dcfce7'; // Green
                $budget->status_text_class = '#166534';
                $budget->status_label = 'Under Limit';
            }
        }

        return view('budgets', compact('budgets'));
    }

    public function getJson($id)
    {
        $budget = DB::table('budgets')->where('id', $id)->first();
        if (!$budget) return response()->json(['error' => 'Not found'], 404);
        
        $groups = DB::table('budget_groups')->where('budget_id', $id)->get();
        foreach ($groups as $group) {
            $group->items = DB::table('budget_items')->where('budget_group_id', $group->id)->get();
        }
        $budget->groups = $groups;
        
        return response()->json($budget);
    }

    public function show($id)
    {
        $budget = DB::table('budgets')->where('id', $id)->first();
        if (!$budget) abort(404);
        
        $groups = DB::table('budget_groups')->where('budget_id', $id)->get();
        
        $totalAllocated = 0;
        $totalSpent = 0;
        
        foreach ($groups as $group) {
            $items = DB::table('budget_items')->where('budget_group_id', $group->id)->get();
            $groupAllocated = 0;
            $groupSpent = 0;
            
            foreach ($items as $item) {
                $itemExpense = DB::table('budget_transactions')
                    ->join('transactions', 'budget_transactions.transaction_id', '=', 'transactions.id')
                    ->where('budget_transactions.budget_item_id', $item->id)
                    ->where('transactions.type', 'expense')
                    ->sum('transactions.amount');
                
                $itemIncome = DB::table('budget_transactions')
                    ->join('transactions', 'budget_transactions.transaction_id', '=', 'transactions.id')
                    ->where('budget_transactions.budget_item_id', $item->id)
                    ->where('transactions.type', 'income')
                    ->sum('transactions.amount');
                    
                $item->allocated_amount += $itemIncome;
                $item->actual_spent = $itemExpense;
                $item->remaining = $item->allocated_amount - $itemExpense;
                $item->percent_used = $item->allocated_amount > 0 ? max(0, round(($itemExpense / $item->allocated_amount) * 100, 2)) : 0;
                
                $groupAllocated += $item->allocated_amount;
                $groupSpent += $itemExpense;
            }
            
            $group->items = $items;
            $group->allocated_amount = $groupAllocated;
            $group->actual_spent = $groupSpent;
            $group->percent_used = $groupAllocated > 0 ? max(0, round(($groupSpent / $groupAllocated) * 100, 2)) : 0;
            $group->remaining = $groupAllocated - $groupSpent;
            
            $totalAllocated += $groupAllocated;
            $totalSpent += $groupSpent;
        }
        
        // Fetch all transactions linked to this budget
        $transactions = DB::table('budget_transactions')
            ->join('transactions', 'budget_transactions.transaction_id', '=', 'transactions.id')
            ->leftJoin('budget_items', 'budget_transactions.budget_item_id', '=', 'budget_items.id')
            ->leftJoin('budget_groups', 'budget_items.budget_group_id', '=', 'budget_groups.id')
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('budget_transactions.budget_id', $id)
            ->select('transactions.*', 'categories.name as category_name', 'budget_items.name as item_name', 'budget_groups.name as group_name', 'budget_transactions.budget_item_id')
            ->orderBy('transactions.transaction_date', 'desc')
            ->get();
            
        $unspecifiedSpent = 0;
        $unspecifiedIncome = 0;
        foreach ($transactions as $tx) {
            if (!$tx->group_name) $tx->group_name = 'Unspecified';
            if (!$tx->item_name) $tx->item_name = 'General / Unassigned';
            
            if ($tx->budget_item_id === null) {
                if ($tx->type === 'expense') $unspecifiedSpent += $tx->amount;
                elseif ($tx->type === 'income') $unspecifiedIncome += $tx->amount;
            }
        }
        
        if ($unspecifiedSpent != 0 || $unspecifiedIncome != 0 || $transactions->where('budget_item_id', null)->count() > 0) {
            $unspecifiedGroup = (object)[
                'id' => null,
                'name' => 'Unspecified',
                'allocated_amount' => $unspecifiedIncome,
                'actual_spent' => $unspecifiedSpent,
                'percent_used' => $unspecifiedIncome > 0 ? max(0, round(($unspecifiedSpent / $unspecifiedIncome) * 100, 2)) : ($unspecifiedSpent > 0 ? 100 : 0),
                'remaining' => $unspecifiedIncome - $unspecifiedSpent,
                'items' => [
                    (object)[
                        'id' => null,
                        'name' => 'General / Unassigned',
                        'allocated_amount' => $unspecifiedIncome,
                        'actual_spent' => $unspecifiedSpent,
                        'percent_used' => $unspecifiedIncome > 0 ? max(0, round(($unspecifiedSpent / $unspecifiedIncome) * 100, 2)) : ($unspecifiedSpent > 0 ? 100 : 0),
                        'remaining' => $unspecifiedIncome - $unspecifiedSpent
                    ]
                ]
            ];
            $groups->push($unspecifiedGroup);
            $totalAllocated += $unspecifiedIncome;
            $totalSpent += $unspecifiedSpent;
        }

        $budget->allocated_amount = $totalAllocated;
        $budget->actual_spent = $totalSpent;
        $budget->percent_used = $totalAllocated > 0 ? max(0, round(($totalSpent / $totalAllocated) * 100, 2)) : 0;
        $budget->remaining = $totalAllocated - $totalSpent;
        
        if ($budget->percent_used >= 100) {
            $budget->status_class = 'var(--danger)';
            $budget->status_text_class = '#fff';
            $budget->status_label = 'Over Budget';
        } elseif ($budget->percent_used >= 80) {
            $budget->status_class = 'var(--warning)';
            $budget->status_text_class = '#fff';
            $budget->status_label = 'Near Limit';
        } else {
            $budget->status_class = 'var(--success)';
            $budget->status_text_class = '#fff';
            $budget->status_label = 'Under Limit';
        }
        
        $categories = DB::table('categories')->get();
        $bankAccounts = DB::table('bank_accounts')->get();
        $departments = DB::table('departments')->get();
        $tags = DB::table('tags')->get();
        
        return view('budgets-show', compact('budget', 'groups', 'transactions', 'categories', 'bankAccounts', 'departments', 'tags'));
    }

    public function storeTransaction(Request $request, $id)
    {
        $budget = DB::table('budgets')->where('id', $id)->first();
        if (!$budget) abort(404);

        $data = $request->except(['_token', 'tag_ids', 'budget_item_id']);
        $data['type'] = $request->input('type', 'expense');
        $data['created_at'] = now();
        $data['updated_at'] = now();
        if (empty($data['currency'])) $data['currency'] = $budget->currency;
        if (empty($data['department_id'])) {
            if (isset($budget->scope_type) && $budget->scope_type === 'department' && !empty($budget->scope_id)) {
                $data['department_id'] = $budget->scope_id;
            } else {
                $data['department_id'] = DB::table('departments')->value('id') ?? 1;
            }
        }

        $itemId = $request->input('budget_item_id');
        $budgetItem = DB::table('budget_items')
            ->join('budget_groups', 'budget_items.budget_group_id', '=', 'budget_groups.id')
            ->join('budgets', 'budget_groups.budget_id', '=', 'budgets.id')
            ->where('budget_items.id', $itemId)
            ->select('budget_items.name as item_name', 'budget_groups.name as group_name', 'budgets.name as budget_name')
            ->first();
            
        if ($budgetItem) {
            $data['description'] = $data['description'] . " ({$budgetItem->budget_name} > {$budgetItem->group_name} > {$budgetItem->item_name})";
        }

        $transactionId = DB::table('transactions')->insertGetId($data);

        DB::table('budget_transactions')->insert([
            'budget_id' => $budget->id,
            'budget_item_id' => $itemId,
            'transaction_id' => $transactionId
        ]);

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

        return back()->with('success', 'Expense recorded and linked successfully!');
    }

    public function store(Request $request)
    {
        $budgetData = $request->validate([
            'name' => 'required|string|max:255',
            'period' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'currency' => 'required|string|max:10',
        ]);
        
        $budgetData['created_at'] = now();
        $budgetData['updated_at'] = now();
        
        $totalAllocated = 0;
        $groups = $request->input('groups', []);
        foreach ($groups as $groupData) {
            $items = $groupData['items'] ?? [];
            foreach ($items as $itemData) {
                if (!empty($itemData['name'])) {
                    $totalAllocated += (float) ($itemData['allocated_amount'] ?? 0);
                }
            }
        }
        $budgetData['allocated_amount'] = $totalAllocated;
        
        DB::beginTransaction();
        try {
            $budgetId = DB::table('budgets')->insertGetId($budgetData);
            
            $groups = $request->input('groups', []);
            foreach ($groups as $groupData) {
                if (empty($groupData['name'])) continue;
                
                $groupId = DB::table('budget_groups')->insertGetId([
                    'budget_id' => $budgetId,
                    'name' => $groupData['name'],
                    'created_at' => now()
                ]);
                
                $items = $groupData['items'] ?? [];
                foreach ($items as $itemData) {
                    if (empty($itemData['name'])) continue;
                    
                    DB::table('budget_items')->insert([
                        'budget_group_id' => $groupId,
                        'name' => $itemData['name'],
                        'allocated_amount' => $itemData['allocated_amount'] ?? 0,
                        'created_at' => now()
                    ]);
                }
            }
            
            DB::commit();
            return back()->with('success', 'Hierarchical Budget created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create budget: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        DB::table('budgets')->where('id', $id)->delete();
        return back()->with('success', 'Budget deleted successfully!');
    }
}
