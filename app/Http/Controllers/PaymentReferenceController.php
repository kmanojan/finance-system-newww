<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class PaymentReferenceController extends Controller
{
    public function show(int $id)
    {
        $payment = DB::table('payments')->find($id) ?? abort(404);
        $invoice = $payment->invoice_id ? DB::table('invoices')->find($payment->invoice_id) : null;
        $projectId = $payment->project_id ?: $invoice?->project_id;

        return view('payment-reference', [
            'payment' => $payment, 'invoice' => $invoice, 'project' => $projectId ? DB::table('projects')->find($projectId) : null,
            'modes' => DB::table('payment_modes')->where('payment_id', $id)->get(),
            'allocations' => DB::table('payment_allocations as a')->join('invoices as i', 'i.id', '=', 'a.invoice_id')->where('a.payment_id', $id)->select('a.amount', 'i.id', 'i.invoice_no')->get(),
        ]);
    }
}
