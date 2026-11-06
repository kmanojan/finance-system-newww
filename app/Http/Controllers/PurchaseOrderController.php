<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Services\ActivityLogService;
use App\Services\PeriodLockService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = PurchaseOrder::with(['vendor', 'department'])->orderBy('issue_date', 'desc')->get();
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $orders]);
        }

        return view('payables.purchase-orders', ['orders' => $orders, 'vendors' => Party::all(), 'departments' => Department::all()]);
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

        if (! $request->expectsJson()) {
            return back()->with('success', 'Purchase order created.');
        }

        return response()->json(['success' => true, 'message' => 'Purchase order created.', 'data' => $po], 201);
    }

    public function approve(Request $request, $id)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->role === 'manager', 403);
        $order = PurchaseOrder::findOrFail($id);
        abort_unless($order->status === 'draft', 422);
        $order->update(['status' => 'approved']);
        ActivityLogService::logUpdate('PurchaseOrder', $id, ['status' => 'draft'], ['status' => 'approved']);

        return back()->with('success', 'Purchase order approved.');
    }
}
