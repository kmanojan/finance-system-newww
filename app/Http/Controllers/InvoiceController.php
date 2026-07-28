<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('invoices')
            ->select('invoices.*', DB::raw('(SELECT COALESCE(SUM(amount), 0) FROM payment_allocations WHERE invoice_id = invoices.id) as paid_amount'))
            ->orderBy('invoices.created_at', 'desc');
        
        $clientMetrics = null;
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
            
            // Calculate metrics for the selected client
            $clientInvoices = DB::table('invoices')->where('client_id', $request->client_id)->get();
            $clientMetrics = (object)[
                'total_sent' => $clientInvoices->where('status', '!=', 'draft')->sum('grand_total'),
                'total_draft' => $clientInvoices->where('status', 'draft')->sum('grand_total'),
                'total_paid' => 0,
                'balance' => 0,
            ];
            
            // For total paid, we sum the allocated payments to this client's invoices
            $clientMetrics->total_paid = DB::table('payment_allocations')
                ->whereIn('invoice_id', $clientInvoices->pluck('id'))
                ->sum('amount');
                
            $clientMetrics->balance = max(0, $clientMetrics->total_sent - $clientMetrics->total_paid);
        }
        
        $invoices = $query->get();
        $clients = DB::table('parties')->where('types', 'LIKE', '%client%')->get();
        
        // Fetch all payments for the invoices hub
        $payments = DB::table('payments')
            ->leftJoin('payment_allocations', 'payments.id', '=', 'payment_allocations.payment_id')
            ->leftJoin('invoices', 'payment_allocations.invoice_id', '=', 'invoices.id')
            ->select('payments.*', 'invoices.invoice_no', 'payment_allocations.amount as allocated_amount')
            ->orderBy('payments.payment_date', 'desc')
            ->get();
            
        // Fetch reminders
        $reminders = DB::table('reminders')
            ->orderBy('due_date', 'desc')
            ->get();
            
        return view('invoices', compact('invoices', 'clients', 'payments', 'reminders', 'clientMetrics'));
    }

    public function store(Request $request)
    {
        // 1. Extract core invoice data
        $invoiceData = $request->except(['_token', 'item_type_id', 'item_description', 'item_qty', 'item_price', 'item_tax', 'payment_milestone_id', 'ignore_total']);
        $invoiceData['created_at'] = now();
        $invoiceData['updated_at'] = now();
        
        // Fetch missing fields from project
        $project = DB::table('projects')->where('id', $invoiceData['project_id'])->first();
        if ($project) {
            $invoiceData['department_id'] = $project->department_id;
            $invoiceData['currency'] = $project->currency ?? (DB::table('companies')->value('base_currency') ?? 'LKR');
        }
        
        // Default missing fields
        if(empty($invoiceData['department_id'])) {
            // Fallback to first available department
            $firstDept = DB::table('departments')->first();
            $invoiceData['department_id'] = $firstDept ? $firstDept->id : 1;
        }
        if(empty($invoiceData['status'])) $invoiceData['status'] = 'draft';
        
        // Auto-generate invoice number if empty
        if(empty($invoiceData['invoice_no'])) {
            $invoiceData['invoice_no'] = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        // Fetch template snapshot
        $template = DB::table('document_templates')
            ->where('department_id', $invoiceData['department_id'])
            ->where('is_default', true)
            ->first();

        if (!$template) {
            $template = DB::table('document_templates')->where('is_default', true)->first();
            if (!$template) $template = DB::table('document_templates')->first();
        }

        $invoiceData['template_snapshot'] = $template ? json_encode([
            'header_image_url' => $template->header_image_url,
            'footer_image_url' => $template->footer_image_url,
            'background_image_url' => $template->background_image_url ?? null,
            'company_details' => $template->company_details,
            'bank_details' => $template->bank_details,
            'description' => $template->description,
            'terms_conditions' => $template->terms_conditions,
            'language' => $template->language ?? 'English'
        ]) : null;

        $invoiceData['amount'] = 0;
        $invoiceData['subtotal'] = 0;
        $invoiceData['advance_paid'] = 0;
        $invoiceData['grand_total'] = 0;

        // Insert into invoices table and get ID
        $invoiceId = DB::table('invoices')->insertGetId($invoiceData);

        // 2. Handle Invoice Items (Repeatable Rows)
        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);
        $typeIds = $request->input('item_type_id', []);

        $itemsToInsert = [];
        $subtotal = 0;
        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i])) {
                $qty = isset($qtys[$i]) ? (float)$qtys[$i] : 1;
                $price = isset($prices[$i]) ? (float)$prices[$i] : 0;
                $lineTotal = round($qty * $price, 2);
                $subtotal += $lineTotal;

                $itemsToInsert[] = [
                    'invoice_id' => $invoiceId,
                    'invoice_type_id' => $typeIds[$i] ?? 1,
                    'description' => $descriptions[$i],
                    'qty' => $qty,
                    'unit_price' => $price,
                    'currency' => $invoiceData['currency'],
                    'tax_percentage' => 0,
                    'total' => $lineTotal,
                    'created_at' => now(),
                ];
            }
        }

        if (!empty($itemsToInsert)) {
            DB::table('invoice_items')->insert($itemsToInsert);
        }

        // Calculate overall invoice tax rate and tax amount
        $taxTypeId = $request->input('tax_type_id');
        $taxRate = 0.0;
        if ($taxTypeId) {
            $taxType = DB::table('tax_types')->where('id', $taxTypeId)->first();
            $taxRate = $taxType ? (float)$taxType->rate : 0.0;
        }

        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $grandTotal = round($subtotal + $taxAmount, 2);

        DB::table('invoices')->where('id', $invoiceId)->update([
            'subtotal' => $subtotal,
            'tax_type_id' => $taxTypeId,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'amount' => $grandTotal,
            'grand_total' => $grandTotal
        ]);


        if ($request->filled('payment_milestone_id')) {
            DB::table('payment_milestones')
                ->where('id', $request->input('payment_milestone_id'))
                ->update(['status' => 'invoiced', 'updated_at' => now()]);
        }

        return back()->with('success', 'Invoice and line items created successfully!');
    }

    public function updateStatus(Request $request, $id)
    {
        $status = $request->input('status');
        DB::table('invoices')->where('id', $id)->update(['status' => $status]);
        return back()->with('success', 'Invoice status updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('invoices')->where('id', $id)->delete();
        return back()->with('success', 'Invoice deleted successfully!');
    }

    public function downloadPdf($id)
    {
        $invoice = DB::table('invoices')->where('id', $id)->first();
        if (!$invoice) {
            abort(404, 'Invoice not found');
        }

        $items = DB::table('invoice_items')->where('invoice_id', $id)->get();
        $project = DB::table('projects')->where('id', $invoice->project_id)->first();
        $client = DB::table('parties')->where('id', $invoice->client_id)->first();
        $taxType = $invoice->tax_type_id ? DB::table('tax_types')->where('id', $invoice->tax_type_id)->first() : null;

        // Pass snapshot
        $snapshot = $invoice->template_snapshot ? json_decode($invoice->template_snapshot, true) : null;

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'items', 'project', 'client', 'snapshot', 'taxType'));
        return $pdf->download("invoice-{$invoice->invoice_no}.pdf");
    }

    public function viewPdf($id)
    {
        $invoice = DB::table('invoices')->where('id', $id)->first();
        if (!$invoice) {
            abort(404, 'Invoice not found');
        }

        $items = DB::table('invoice_items')->where('invoice_id', $id)->get();
        $project = DB::table('projects')->where('id', $invoice->project_id)->first();
        $client = DB::table('parties')->where('id', $invoice->client_id)->first();
        $taxType = $invoice->tax_type_id ? DB::table('tax_types')->where('id', $invoice->tax_type_id)->first() : null;
        $snapshot = $invoice->template_snapshot ? json_decode($invoice->template_snapshot, true) : null;

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'items', 'project', 'client', 'snapshot', 'taxType'));
        return $pdf->stream("invoice-{$invoice->invoice_no}.pdf");
    }

}
