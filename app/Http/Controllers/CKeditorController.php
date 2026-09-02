<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CKeditorController extends Controller
{
    /**
     * Fetch mentions data on demand (Loans, Parties, Employees, Transactions)
     * strictly paginated to 10 records per request.
     */
    public function mentions(Request $request)
    {
        $type = $request->input('type', 'loan'); // 'loan', 'party', 'employee', 'transaction', 'all'
        $q = trim($request->input('q', ''));
        $limit = min(50, max(1, (int)$request->input('limit', 10)));

        $data = [
            'type' => $type,
            'items' => [],
            'loans' => [],
            'parties' => [],
            'employees' => [],
            'transactions' => [],
        ];

        // 1. Loans (/loan)
        if (($type === 'loan' || $type === 'all') && Schema::hasTable('loans')) {
            $hasLoanCode = Schema::hasColumn('loans', 'loan_code');
            $selects = [
                'loans.id', 
                'loans.lender_name', 
                'loans.party_id',
                'parties.name as party_name',
                'loans.principal_amount', 
                'loans.currency', 
                'loans.status',
                'loans.purpose'
            ];
            if ($hasLoanCode) {
                $selects[] = 'loans.loan_code';
            }

            $loanQuery = DB::table('loans')
                ->leftJoin('parties', 'loans.party_id', '=', 'parties.id');

            if (!empty($q)) {
                $search = '%' . $q . '%';
                $loanQuery->where(function ($sub) use ($search, $hasLoanCode, $q) {
                    $sub->where('loans.lender_name', 'LIKE', $search)
                        ->orWhere('loans.purpose', 'LIKE', $search)
                        ->orWhere('parties.name', 'LIKE', $search);
                    if ($hasLoanCode) {
                        $sub->orWhere('loans.loan_code', 'LIKE', $search);
                    }
                    if (is_numeric($q)) {
                        $sub->orWhere('loans.id', '=', (int)$q)
                            ->orWhere('loans.principal_amount', 'LIKE', $search);
                    }
                });
            }

            $loans = $loanQuery
                ->select($selects)
                ->orderBy('loans.id', 'desc')
                ->limit($type === 'all' ? 4 : $limit)
                ->get()
                ->map(function ($l) {
                    return [
                        'id' => $l->id,
                        'loan_code' => ($l->loan_code ?? null) ?: ('LN-' . str_pad($l->id, 4, '0', STR_PAD_LEFT)),
                        'lender_name' => $l->lender_name,
                        'party_name' => $l->party_name,
                        'party_id' => $l->party_id,
                        'principal_amount' => (float)$l->principal_amount,
                        'currency' => $l->currency ?: 'LKR',
                        'status' => $l->status,
                        'purpose' => $l->purpose ? trim(strip_tags($l->purpose)) : '',
                    ];
                });

            $data['loans'] = $loans;
            if ($type === 'loan') {
                $data['items'] = $loans;
            }
        }

        // 2. Parties (/party)
        if (($type === 'party' || $type === 'all') && Schema::hasTable('parties')) {
            $partyQuery = DB::table('parties');
            if (!empty($q)) {
                $search = '%' . $q . '%';
                $partyQuery->where(function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', $search)
                        ->orWhere('contact_person', 'LIKE', $search)
                        ->orWhere('phone', 'LIKE', $search)
                        ->orWhere('email', 'LIKE', $search);
                });
            }

            $parties = $partyQuery
                ->select('id', 'name', 'contact_person', 'phone', 'email', 'currency', 'party_types')
                ->orderBy('name', 'asc')
                ->limit($type === 'all' ? 3 : $limit)
                ->get();

            $data['parties'] = $parties;
            if ($type === 'party') {
                $data['items'] = $parties;
            }
        }

        // 3. Employees (/employee)
        if (($type === 'employee' || $type === 'all') && Schema::hasTable('employees')) {
            $empQuery = DB::table('employees');
            if (!empty($q)) {
                $search = '%' . $q . '%';
                $empQuery->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'LIKE', $search)
                        ->orWhere('last_name', 'LIKE', $search)
                        ->orWhere('full_name', 'LIKE', $search)
                        ->orWhere('employee_code', 'LIKE', $search)
                        ->orWhere('job_position', 'LIKE', $search);
                });
            }

            $employees = $empQuery
                ->select('id', 'full_name', 'first_name', 'last_name', 'employee_code', 'job_position')
                ->orderBy('first_name', 'asc')
                ->limit($type === 'all' ? 3 : $limit)
                ->get()
                ->map(function ($e) {
                    $name = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? ''));
                    if (empty($name)) $name = $e->full_name ?? 'Employee #' . $e->id;
                    return [
                        'id' => $e->id,
                        'name' => $name,
                        'code' => $e->employee_code,
                        'job_position' => $e->job_position,
                    ];
                });

            $data['employees'] = $employees;
            if ($type === 'employee') {
                $data['items'] = $employees;
            }
        }

        // 4. Transactions (/transaction)
        if (($type === 'transaction' || $type === 'all') && Schema::hasTable('transactions')) {
            $txQuery = DB::table('transactions')
                ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id');

            if (!empty($q)) {
                $search = '%' . $q . '%';
                $txQuery->where(function ($sub) use ($search, $q) {
                    $sub->where('transactions.description', 'LIKE', $search)
                        ->orWhere('transactions.reference_no', 'LIKE', $search)
                        ->orWhere('transactions.type', 'LIKE', $search)
                        ->orWhere('categories.name', 'LIKE', $search);
                    if (is_numeric($q)) {
                        $sub->orWhere('transactions.id', '=', (int)$q)
                            ->orWhere('transactions.amount', 'LIKE', $search);
                    }
                });
            }

            $transactions = $txQuery
                ->select(
                    'transactions.id',
                    'transactions.type',
                    'transactions.amount',
                    'transactions.currency',
                    'transactions.transaction_date',
                    'transactions.description',
                    'transactions.reference_no',
                    'transactions.payment_method',
                    'categories.name as category_name'
                )
                ->orderBy('transactions.transaction_date', 'desc')
                ->orderBy('transactions.id', 'desc')
                ->limit($type === 'all' ? 3 : $limit)
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'type' => $t->type,
                        'amount' => (float)$t->amount,
                        'currency' => $t->currency ?: 'LKR',
                        'transaction_date' => $t->transaction_date,
                        'description' => $t->description ?: 'Transaction #' . $t->id,
                        'reference_no' => $t->reference_no,
                        'payment_method' => $t->payment_method,
                        'category_name' => $t->category_name,
                    ];
                });

            $data['transactions'] = $transactions;
            if ($type === 'transaction') {
                $data['items'] = $transactions;
            }
        }

        return response()->json($data);
    }
}
