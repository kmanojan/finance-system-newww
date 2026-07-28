<?php

namespace App\Http\Controllers;

use App\Models\VendorBill;
use App\Models\Party;
use App\Models\Department;
use App\Services\PeriodLockService;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorBillController extends Controller
{
    public function index(Request $request)
    {
        $bills = VendorBill::with(['vendor', 'department', 'purchaseOrder'])->orderBy('issue_date', 'desc')->get();
        
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'data' => $bills]);
        }

        $vendors = DB::table('parties')->where('types', 'LIKE', '%vendor%')->orWhere('types', 'LIKE', '%supplier%')->get();
        if ($vendors->isEmpty()) {
            $vendors = DB::table('parties')->get();
        }
        $departments = Department::all();

        return view('payables.index', compact('bills', 'vendors', 'departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bill_number' => 'required|string',
            'vendor_id' => 'required|exists:parties,id',
            'po_id' => 'nullable|exists:purchase_orders,id',
            'department_id' => 'required|exists:departments,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
        ]);

        PeriodLockService::checkLockedDate($validated['issue_date']);

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
            VendorBill::class
        );

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'Vendor bill created and GL journal posted.', 'data' => $bill], 201);
        }

        return back()->with('success', 'Vendor bill created and GL journal posted.');
    }
}
