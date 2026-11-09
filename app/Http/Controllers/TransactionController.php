<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\FinancialReportingService;
use App\Services\PeriodLockService;
use App\Services\TransactionPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('transactions')
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('departments', 'transactions.department_id', '=', 'departments.id')
            ->leftJoin('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->select(
                'transactions.*',
                'categories.name as category_name',
                'departments.name as department_name',
                'bank_accounts.bank_name as bank_name',
                'bank_accounts.account_no as bank_account_no'
            )
            ->orderBy('transactions.transaction_date', 'desc')
            ->orderBy('transactions.id', 'desc');

        if ($request->filled('transaction_id')) {
            $request->validate(['transaction_id' => 'required|integer|min:1']);
            $query->where('transactions.id', $request->integer('transaction_id'));
        }

        if ($request->filled('tag_id')) {
            $query->join('taggables', function ($join) use ($request) {
                $join->on('transactions.id', '=', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', '=', 'transaction')
                    ->where('taggables.tag_id', '=', $request->tag_id);
            });
        }

        $startDate = $request->input('start_date') ?: $request->input('from');
        $endDate = $request->input('end_date') ?: $request->input('to');

        if (! empty($startDate)) {
            $query->where('transactions.transaction_date', '>=', $startDate);
        }

        if (! empty($endDate)) {
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

        if ($request->filled('bank_account_id')) {
            $query->where('transactions.bank_account_id', '=', $request->bank_account_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('transactions.payment_method', '=', $request->payment_method);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('transactions.description', 'LIKE', $search)
                    ->orWhere('transactions.reference_no', 'LIKE', $search)
                    ->orWhere('categories.name', 'LIKE', $search)
                    ->orWhere('bank_accounts.bank_name', 'LIKE', $search)
                    ->orWhere('bank_accounts.account_no', 'LIKE', $search);
            });
        }

        // Calculate KPI summary figures on complete filtered set
        $summary = (clone $query)->reorder()->where('transactions.payment_status', 'completed')->select('transactions.type', 'transactions.currency', 'transactions.transaction_date', DB::raw('SUM(transactions.amount) as total'))->groupBy('transactions.type', 'transactions.currency', 'transactions.transaction_date')->get();
        foreach ($summary as $row) {
            $row->base_amount = FinancialReportingService::convert($row->total, $row->currency, $row->transaction_date);
        }
        $totalIncome = $summary->where('type', 'income')->sum('base_amount');
        $totalExpense = $summary->where('type', 'expense')->sum('base_amount');
        $netCashFlow = $totalIncome - $totalExpense;
        $incomeCount = (clone $query)->where('transactions.type', 'income')->count();
        $expenseCount = (clone $query)->where('transactions.type', 'expense')->count();
        $totalCount = $incomeCount + $expenseCount;

        // Paginate transactions
        $transactions = $query->paginate(20)->withQueryString();

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

        // Load bank accounts with live balances
        $bankAccounts = DB::table('bank_accounts')->get();
        foreach ($bankAccounts as $acc) {
            $inflow = DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'income')->sum('amount');
            $outflow = DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'expense')->sum('amount');
            $acc->current_balance = ($acc->opening_balance ?? 0) + $inflow - $outflow;
        }

        return view('transactions', compact(
            'transactions',
            'categories',
            'companies',
            'departments',
            'budgetItems',
            'tags',
            'parties',
            'bankAccounts',
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
        $data = $this->validatedData($request);
        PeriodLockService::checkLockedDate($data['transaction_date'], DB::table('departments')->where('id', $data['department_id'] ?? DB::table('departments')->value('id'))->value('company_id'));
        $data['created_at'] = now();
        $data['updated_at'] = now();
        // default missing fields
        if (empty($data['currency'])) {
            $data['currency'] = DB::table('companies')->value('base_currency') ?? 'LKR';
        }
        if (empty($data['department_id'])) {
            $data['department_id'] = DB::table('departments')->value('id') ?: null;
        }

        if ($request->filled('bank_account_id')) {
            $data['bank_account_id'] = (int) $request->input('bank_account_id');
        } else {
            $data['bank_account_id'] = null;
        }

        if ($request->filled('budget_item_id')) {
            $itemId = $request->budget_item_id;
            $budgetItem = DB::table('budget_items')
                ->join('budget_groups', 'budget_items.budget_group_id', '=', 'budget_groups.id')
                ->join('budgets', 'budget_groups.budget_id', '=', 'budgets.id')
                ->where('budget_items.id', $itemId)
                ->select('budget_items.name as item_name', 'budget_groups.name as group_name', 'budgets.name as budget_name')
                ->first();

            if ($budgetItem) {
                $data['description'] = $data['description']." ({$budgetItem->budget_name} > {$budgetItem->group_name} > {$budgetItem->item_name})";
            }
        }

        $transactionId = TransactionPostingService::create($data);

        if ($request->filled('budget_item_id')) {
            DB::table('budget_transactions')->insert([
                'budget_item_id' => $request->budget_item_id,
                'transaction_id' => $transactionId,
            ]);
        }

        if ($request->has('tag_ids') && is_array($request->tag_ids)) {
            $taggables = [];
            foreach ($request->tag_ids as $tagId) {
                $taggables[] = [
                    'tag_id' => $tagId,
                    'taggable_id' => $transactionId,
                    'taggable_type' => 'transaction',
                ];
            }
            if (! empty($taggables)) {
                DB::table('taggables')->insert($taggables);
            }
        }

        ActivityLogService::logCreate('Transaction', $transactionId, [
            'type' => $data['type'] ?? null,
            'amount' => $data['amount'] ?? null,
            'description' => $data['description'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully!',
                'transaction_id' => $transactionId,
            ]);
        }

        return back()->with('success', 'Transaction created successfully!');
    }

    public function update(Request $request, $id)
    {
        $oldTx = DB::table('transactions')->where('id', $id)->first();
        $this->assertEditable($oldTx);
        $data = $this->validatedData($request, true);
        PeriodLockService::checkLockedDate($data['transaction_date'] ?? $oldTx->transaction_date, DB::table('departments')->where('id', $data['department_id'] ?? $oldTx->department_id)->value('company_id'));
        $data['updated_at'] = now();

        if ($request->filled('bank_account_id')) {
            $data['bank_account_id'] = (int) $request->input('bank_account_id');
        } else {
            $data['bank_account_id'] = null;
        }

        TransactionPostingService::reverse($oldTx);
        DB::table('transactions')->where('id', $id)->update($data);
        TransactionPostingService::post(DB::table('transactions')->find($id));

        ActivityLogService::logUpdate('Transaction', $id, $oldTx, $data);

        return back()->with('success', 'Transaction updated successfully!');
    }

    public function destroy($id)
    {
        $oldTx = DB::table('transactions')->where('id', $id)->first();
        $this->assertEditable($oldTx);
        DB::table('budget_transactions')->where('transaction_id', $id)->delete();
        DB::table('taggables')->where('taggable_type', 'transaction')->where('taggable_id', $id)->delete();
        TransactionPostingService::reverse($oldTx);
        DB::table('transactions')->where('id', $id)->delete();

        ActivityLogService::logDelete('Transaction', $id, $oldTx);

        return back()->with('success', 'Transaction deleted successfully!');
    }

    private function validatedData(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';
        $data = $request->validate([
            'amount' => "$required|numeric|gt:0|max:9999999999999",
            'type' => "$required|in:income,expense",
            'transaction_date' => "$required|date_format:Y-m-d",
            'category_id' => "$required|exists:categories,id",
            'description' => 'nullable|string|max:5000', 'currency' => 'nullable|string|size:3',
            'department_id' => 'nullable|exists:departments,id', 'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'project_id' => 'nullable|exists:projects,id', 'party_id' => 'nullable|exists:parties,id',
            'payment_method' => 'nullable|string|max:50', 'reference_no' => 'nullable|string|max:255',
            'payment_status' => 'nullable|in:pending,completed', 'due_date' => 'nullable|date_format:Y-m-d',
            'budget_item_id' => 'nullable|exists:budget_items,id', 'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ]);
        unset($data['budget_item_id'], $data['tag_ids']);
        if (isset($data['type'],$data['category_id']) && DB::table('categories')->where('id', $data['category_id'])->value('type') !== $data['type']) {
            throw ValidationException::withMessages(['category_id' => 'Choose a category matching the income or expense type.']);
        }
        if (! empty($data['bank_account_id']) && ! empty($data['currency'])) {
            $bank = DB::table('bank_accounts')->find($data['bank_account_id']);
            if ($bank->currency !== $data['currency']) {
                throw ValidationException::withMessages(['bank_account_id' => 'Bank account and transaction currencies must match.']);
            }
        }

        return $data;
    }

    private function assertEditable($transaction): void
    {
        if ($transaction && Schema::hasTable('payroll_payments')) {
            abort_if(DB::table('payroll_payments')->where('transaction_id', $transaction->id)->orWhere('reversal_transaction_id', $transaction->id)->exists(), 422, 'Payroll transactions must be reversed from Payroll to preserve payment allocations.');
        }
        abort_unless($transaction, 404);
        PeriodLockService::checkLockedDate($transaction->transaction_date, DB::table('departments')->where('id', $transaction->department_id)->value('company_id'));
        if ($transaction->reconciled || $transaction->project_id || ! empty($transaction->loan_id) || str_starts_with($transaction->reference_no ?? '', 'LOAN-')) {
            throw ValidationException::withMessages(['transaction' => 'This transaction is linked or reconciled. Correct it through its source document.']);
        }
    }
}
