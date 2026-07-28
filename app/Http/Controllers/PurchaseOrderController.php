<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\PeriodLockService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = PurchaseOrder::with(['vendor', 'department'])->orderBy('issue_date', 'desc')->get();
        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'po_number' => 'required|string|unique:purchase_orders,po_number',
            'vendor_id' => 'required|exists:parties,id',
            'department_id' => 'required|exists:departments,id',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'issue_date' => 'required|date',
        ]);

        PeriodLockService::checkLockedDate($validated['issue_date']);

        $po = PurchaseOrder::create(array_merge($validated, ['currency' => $validated['currency'] ?? 'LKR']));

        return response()->json(['success' => true, 'message' => 'Purchase order created.', 'data' => $po], 201);
    }
}
