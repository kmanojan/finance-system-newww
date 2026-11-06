<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\VendorBill;
use App\Services\LedgerPostingService;
use App\Services\PeriodLockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorBillController extends Controller
{
    public function index(Request $request)
    {
        $bills = VendorBill::with(['vendor', 'department', 'purchaseOrder'])->when($request->vendor_id, fn ($q) => $q->where('vendor_id', $request->vendor_id))->orderBy('issue_date', 'desc')->get();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'data' => $bills]);
        }

        $vendors = DB::table('parties')->where('types', 'LIKE', '%vendor%')->orWhere('types', 'LIKE', '%supplier%')->get();
        if ($vendors->isEmpty()) {
            $vendors = DB::table('parties')->get();
        }
        $departments = Department::all();

        foreach ($bills as $bill) {
            $bill->paid_amount = DB::table('vendor_payments')->where('vendor_bill_id', $bill->id)->sum('amount');
        }
        $bankAccounts = DB::table('bank_accounts')->whereNull('deleted_at')->get();

        return view('payables.index', compact('bills', 'vendors', 'departments', 'bankAccounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bill_number' => 'required|string',
            'vendor_id' => 'required|exists:parties,id',
            'po_id' => 'nullable|exists:purchase_orders,id',
            'department_id' => 'required|exists:departments,id',
            'amount' => 'required|numeric|gt:0',
            'currency' => 'nullable|string|max:10',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
        ]);

        PeriodLockService::checkLockedDate($validated['issue_date'], DB::table('departments')->where('id', $validated['department_id'])->value('company_id'));
        if (! empty($validated['po_id'])) {
            $po = PurchaseOrder::whereKey($validated['po_id'])->lockForUpdate()->firstOrFail();
            $billed = VendorBill::where('po_id', $po->id)->sum('amount');
            if ($po->status !== 'approved' || $po->vendor_id != $validated['vendor_id'] || $po->department_id != $validated['department_id'] || $po->currency !== ($validated['currency'] ?? 'LKR') || round($billed + $validated['amount'], 2) > $po->total_amount) {
                throw ValidationException::withMessages(['po_id' => 'Choose an approved purchase order for this vendor, department and currency, within its remaining amount.']);
            }
        }

        $bill = VendorBill::create(array_merge($validated, ['currency' => $validated['currency'] ?? 'LKR']));

        // Automatically post double-entry GL journal for vendor bill:
        // Debit: Expense (5200), Credit: Accounts Payable (2100)
        LedgerPostingService::postJournal(
            $bill->issue_date->format('Y-m-d'),
            "Vendor Bill #{$bill->bill_number} from Vendor ID {$bill->vendor_id}",
            [
                ['account_code' => '5200', 'debit' => $bill->amount, 'credit' => 0, 'currency' => $bill->currency],
                ['account_code' => '2100', 'debit' => 0, 'credit' => $bill->amount, 'currency' => $bill->currency],
            ],
            $bill->id,
            VendorBill::class, DB::table('departments')->where('id', $bill->department_id)->value('company_id')
        );

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'Vendor bill created and GL journal posted.', 'data' => $bill], 201);
        }

        return back()->with('success', 'Vendor bill created and GL journal posted.');
    }

    public function recordPayment(Request $request, $id)
    {
        $data = $request->validate(['amount' => 'required|numeric|gt:0', 'payment_date' => 'required|date_format:Y-m-d', 'bank_account_id' => 'required|exists:bank_accounts,id', 'reference_no' => 'nullable|string|max:255']);
        $bill = VendorBill::whereKey($id)->lockForUpdate()->firstOrFail();
        PeriodLockService::checkLockedDate($data['payment_date'], DB::table('departments')->where('id', $bill->department_id)->value('company_id'));
        $bank = DB::table('bank_accounts')->find($data['bank_account_id']);
        $paid = DB::table('vendor_payments')->where('vendor_bill_id', $id)->sum('amount');
        if ($bank->company_id != DB::table('departments')->where('id', $bill->department_id)->value('company_id') || $bank->currency !== $bill->currency || round($paid + $data['amount'], 2) > $bill->amount || $data['payment_date'] < $bill->issue_date->format('Y-m-d')) {
            throw ValidationException::withMessages(['amount' => 'Payment must be within the outstanding balance, in the bank currency, and on or after the bill date.']);
        }
        $paymentId = DB::table('vendor_payments')->insertGetId(array_merge($data, ['vendor_bill_id' => $id, 'created_at' => now(), 'updated_at' => now()]));
        $bill->update(['status' => round($paid + $data['amount'], 2) >= $bill->amount ? 'paid' : 'partially_paid']);
        $category = DB::table('categories')->where('name', 'Vendor payment')->where('company_id', $bank->company_id)->first();
        $categoryId = $category?->id ?? DB::table('categories')->insertGetId(['company_id' => $bank->company_id, 'name' => 'Vendor payment', 'type' => 'expense']);
        DB::table('transactions')->insert(['category_id' => $categoryId, 'department_id' => $bill->department_id, 'bank_account_id' => $bank->id, 'party_id' => $bill->vendor_id, 'type' => 'expense', 'amount' => $data['amount'], 'currency' => $bill->currency, 'transaction_date' => $data['payment_date'], 'description' => 'Vendor payment '.$bill->bill_number, 'reference_no' => $data['reference_no'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        LedgerPostingService::postJournal($data['payment_date'], 'Payment for vendor bill '.$bill->bill_number, [['account_code' => '2100', 'debit' => $data['amount'], 'currency' => $bill->currency], ['account_code' => '1010', 'credit' => $data['amount'], 'currency' => $bill->currency]], $paymentId, 'VendorPayment', $bank->company_id);

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('success', 'Vendor payment recorded.');
    }
}
