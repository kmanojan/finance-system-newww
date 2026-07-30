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
        
        // Fetch missing fields & currency from project
        if (!empty($invoiceData['project_id'])) {
            $project = DB::table('projects')->where('id', $invoiceData['project_id'])->first();
            if ($project) {
                $invoiceData['department_id'] = $project->department_id;
                if (!empty($project->currency)) {
                    $invoiceData['currency'] = $project->currency;
                }
            }
        }

        if (empty($invoiceData['currency'])) {
            $invoiceData['currency'] = DB::table('companies')->value('base_currency') ?? 'LKR';
        }
        
        // Make department_id nullable
        if (empty($invoiceData['department_id'])) {
            $invoiceData['department_id'] = null;
        }

        if(empty($invoiceData['status'])) $invoiceData['status'] = 'draft';
        
        // Auto-generate invoice number if empty
        if(empty($invoiceData['invoice_no'])) {
            $invoiceData['invoice_no'] = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        // Fetch template snapshot
        $template = null;
        if (!empty($invoiceData['department_id'])) {
            $template = DB::table('document_templates')
                ->where('department_id', $invoiceData['department_id'])
                ->where('is_default', true)
                ->first();
        }

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

        // Sanitize numeric & optional fields to prevent NOT NULL constraint errors
        $invoiceData['discount_type'] = !empty($invoiceData['discount_type']) ? $invoiceData['discount_type'] : 'fixed';
        $invoiceData['discount_value'] = !empty($invoiceData['discount_value']) ? (float)str_replace(',', '', (string)$invoiceData['discount_value']) : 0.00;
        $invoiceData['discount_amount'] = !empty($invoiceData['discount_amount']) ? (float)str_replace(',', '', (string)$invoiceData['discount_amount']) : 0.00;
        $invoiceData['tax_rate'] = !empty($invoiceData['tax_rate']) ? (float)$invoiceData['tax_rate'] : 0.00;
        $invoiceData['tax_amount'] = !empty($invoiceData['tax_amount']) ? (float)$invoiceData['tax_amount'] : 0.00;

        if (empty($invoiceData['tax_type_id'])) {
            $invoiceData['tax_type_id'] = null;
        }
        if (empty($invoiceData['due_date'])) {
            $invoiceData['due_date'] = null;
        }

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
                    'invoice_type_id' => !empty($typeIds[$i]) ? $typeIds[$i] : (DB::table('invoice_types')->value('id') ?? 1),
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

        // Calculate overall invoice tax rate, discount, and grand total
        $taxTypeId = $request->input('tax_type_id');
        $taxRate = 0.0;
        if ($taxTypeId) {
            $taxType = DB::table('tax_types')->where('id', $taxTypeId)->first();
            $taxRate = $taxType ? (float)$taxType->rate : 0.0;
        }

        $discountType = $request->input('discount_type', 'fixed');
        $rawDiscVal = $request->input('discount_value', $request->input('discount_amount', 0));
        $discountValue = max(0, (float)str_replace(',', '', (string)$rawDiscVal));

        $discountAmount = 0.0;
        if ($discountType === 'percentage') {
            $discountAmount = round($subtotal * ($discountValue / 100), 2);
        } else {
            $discountAmount = $discountValue;
        }

        $netTaxableAmount = max(0, $subtotal - $discountAmount);
        $taxAmount = round($netTaxableAmount * ($taxRate / 100), 2);
        $grandTotal = round($netTaxableAmount + $taxAmount, 2);


        DB::table('invoices')->where('id', $invoiceId)->update([
            'subtotal' => $subtotal,
            'tax_type_id' => $taxTypeId,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'amount' => $grandTotal,
            'grand_total' => $grandTotal
        ]);




        if ($request->filled('payment_milestone_id')) {
            DB::table('payment_milestones')
                ->where('id', $request->input('payment_milestone_id'))
                ->update(['status' => 'invoiced', 'updated_at' => now()]);
        }

        \App\Services\ActivityLogService::logCreate('Invoice', $invoiceId, [
            'invoice_no' => $invoiceData['invoice_no'],
            'project_id' => $invoiceData['project_id'],
            'grand_total' => $grandTotal,
        ]);

        return redirect()->back()->with('success', 'Invoice generated successfully!');
    }

    public function updateStatus(Request $request, $id)
    {
        $status = $request->input('status');
        $oldInvoice = DB::table('invoices')->where('id', $id)->first();
        DB::table('invoices')->where('id', $id)->update(['status' => $status]);

        \App\Services\ActivityLogService::logUpdate('Invoice', $id, [
            'status' => $oldInvoice->status ?? 'N/A'
        ], [
            'status' => $status
        ]);

        return redirect()->back()->with('success', 'Invoice status updated successfully!');
    }

    public function destroy($id)
    {
        $oldInvoice = DB::table('invoices')->where('id', $id)->first();
        DB::table('invoices')->where('id', $id)->delete();

        \App\Services\ActivityLogService::logDelete('Invoice', $id, [
            'invoice_no' => $oldInvoice->invoice_no ?? 'N/A'
        ]);

        return redirect()->back()->with('success', 'Invoice deleted successfully!');
    }

    private function resolveDepartmentTemplate($project, $invoice)
    {
        $departmentId = $project->department_id ?? ($invoice->department_id ?? null);
        $template = null;
        if ($departmentId) {
            $template = DB::table('document_templates')
                ->where('department_id', $departmentId)
                ->where('is_default', true)
                ->first();
            if (!$template) {
                $template = DB::table('document_templates')
                    ->where('department_id', $departmentId)
                    ->first();
            }
        }
        if (!$template) {
            $template = DB::table('document_templates')->where('is_default', true)->first();
            if (!$template) {
                $template = DB::table('document_templates')->first();
            }
        }
        return $template;
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

        // Dynamically resolve template from project department
        $template = $this->resolveDepartmentTemplate($project, $invoice);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'items', 'project', 'client', 'template', 'taxType'));
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

        // Dynamically resolve template from project department
        $template = $this->resolveDepartmentTemplate($project, $invoice);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'items', 'project', 'client', 'template', 'taxType'));
        return $pdf->stream("invoice-{$invoice->invoice_no}.pdf");
    }

}
