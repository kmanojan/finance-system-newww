<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ActivityLogService;
use App\Services\FinancialReportingService;
use App\Services\InvoicePostingService;
use App\Services\PeriodLockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('invoices')
            ->select('invoices.*', DB::raw('(SELECT COALESCE(SUM(amount), 0) FROM payment_allocations WHERE invoice_id = invoices.id) as paid_amount'))
            ->orderBy('invoices.created_at', 'desc');

        if ($request->filled('search')) {
            $query->where('invoice_no', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->where('issue_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('issue_date', '<=', $request->to);
        }
        $clientMetrics = null;
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);

            $clientInvoices = DB::table('invoices')->where('client_id', $request->client_id)->get();
            $issued = $clientInvoices->whereNotIn('status', ['draft', 'pending_approval', 'rejected', 'cancelled']);
            $date = date('Y-m-d');
            $sumInvoices = fn ($rows) => $rows->sum(fn ($invoice) => FinancialReportingService::convert($invoice->amount, $invoice->currency, $invoice->issue_date ?? $invoice->invoice_date ?? $date));
            $paid = DB::table('payment_allocations')->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')->where('invoices.client_id', $request->client_id)->select('payment_allocations.amount', 'invoices.currency', 'payments.payment_date')->get()->sum(fn ($row) => FinancialReportingService::convert($row->amount, $row->currency, $row->payment_date));
            $clientMetrics = (object) ['total_sent' => $sumInvoices($issued), 'total_draft' => $sumInvoices($clientInvoices->where('status', 'draft')), 'total_paid' => $paid, 'balance' => $issued->sum(fn ($invoice) => FinancialReportingService::convert(FinancialReportingService::outstanding($invoice, $date), $invoice->currency, $date))];

        }

        $invoices = $query->paginate(25)->withQueryString();
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

    public function store(Request $request, $id = null)
    {
        $existing = $id ? DB::table('invoices')->where('id', $id)->lockForUpdate()->first() : null;
        if ($id) {
            abort_unless($existing, 404);
            if ($existing->status !== 'draft' || DB::table('payment_allocations')->where('invoice_id', $id)->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Only unpaid drafts can be edited.']);
            }
            PeriodLockService::checkLockedDate($existing->issue_date ?? $existing->invoice_date, DB::table('departments')->where('id', $existing->department_id)->value('company_id'));
        }
        // 1. Extract core invoice data
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id', 'client_id' => 'required|exists:parties,id',
            'department_id' => 'nullable|exists:departments,id', 'invoice_no' => ['nullable', 'string', 'max:100', Rule::unique('invoices', 'invoice_no')->ignore($id)],
            'issue_date' => 'required|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:issue_date',
            'status' => 'nullable|in:draft,pending_approval,sent', 'currency' => 'nullable|string|size:3',
            'tax_type_id' => 'nullable|exists:tax_types,id', 'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0', 'discount_amount' => 'nullable|numeric|min:0',
            'signee_name' => 'nullable|string|max:255', 'signee_title' => 'nullable|string|max:255',
            'item_description' => 'required|array|min:1', 'item_description.*' => 'required|string|max:5000',
            'item_qty' => 'required|array', 'item_qty.*' => 'required|numeric|gt:0',
            'item_price' => 'required|array', 'item_price.*' => 'required|numeric|min:0',
            'item_type_id' => 'required|array', 'item_type_id.*' => 'required|exists:invoice_types,id',
            'payment_milestone_id' => 'nullable|exists:payment_milestones,id',
        ]);
        if (count($validated['item_description']) !== count($validated['item_qty']) || count($validated['item_qty']) !== count($validated['item_price']) || count($validated['item_price']) !== count($validated['item_type_id'])) {
            throw ValidationException::withMessages(['item_description' => 'Complete every invoice line.']);
        }
        if (($validated['discount_type'] ?? '') === 'percentage' && ($validated['discount_value'] ?? 0) > 100) {
            throw ValidationException::withMessages(['discount_value' => 'A percentage discount cannot exceed 100%.']);
        }
        $invoiceData = Arr::except($validated, ['item_type_id', 'item_description', 'item_qty', 'item_price', 'payment_milestone_id']);
        $invoiceData['invoice_date'] = $validated['issue_date'];
        $invoiceData['created_at'] = $existing->created_at ?? now();
        $invoiceData['updated_at'] = now();

        // Fetch missing fields & currency from project
        if (! empty($invoiceData['project_id'])) {
            $project = DB::table('projects')->where('id', $invoiceData['project_id'])->first();
            if ($project) {
                $invoiceData['department_id'] = $project->department_id;
                if (! empty($project->currency)) {
                    $invoiceData['currency'] = $project->currency;
                }
            }
        }

        if (empty($invoiceData['currency'])) {
            $invoiceData['currency'] = DB::table('companies')->value('base_currency') ?? 'LKR';
        }

        // Make department_id nullable
        if (empty($invoiceData['department_id'])) {
            $invoiceData['department_id'] = DB::table('departments')->value('id');
        }

        PeriodLockService::checkLockedDate($validated['issue_date'], DB::table('departments')->where('id', $invoiceData['department_id'])->value('company_id'));
        if (empty($invoiceData['status'])) {
            $invoiceData['status'] = 'draft';
        }

        // Auto-generate invoice number if empty
        if (empty($invoiceData['invoice_no'])) {
            $invoiceData['invoice_no'] = $existing->invoice_no ?? ('INV-'.date('Ymd').'-'.strtoupper(Str::random(8)));
        }

        // Fetch template snapshot
        $template = null;
        if (! empty($invoiceData['department_id'])) {
            $template = DB::table('document_templates')
                ->where('department_id', $invoiceData['department_id'])
                ->where('is_default', true)
                ->first();
        }

        if (! $template) {
            $template = DB::table('document_templates')->where('is_default', true)->first();
            if (! $template) {
                $template = DB::table('document_templates')->first();
            }
        }

        $invoiceData['template_snapshot'] = $template ? json_encode([
            'header_image_url' => $template->header_image_url,
            'footer_image_url' => $template->footer_image_url,
            'background_image_url' => $template->background_image_url ?? null,
            'company_details' => $template->company_details,
            'bank_details' => $template->bank_details,
            'description' => $template->description,
            'terms_conditions' => $template->terms_conditions,
            'language' => $template->language ?? 'English',
        ]) : null;

        $invoiceData['amount'] = 0;
        $invoiceData['subtotal'] = 0;
        $invoiceData['advance_paid'] = 0;
        $invoiceData['grand_total'] = 0;

        // Sanitize numeric & optional fields to prevent NOT NULL constraint errors
        $invoiceData['discount_type'] = ! empty($invoiceData['discount_type']) ? $invoiceData['discount_type'] : 'fixed';
        $invoiceData['discount_value'] = ! empty($invoiceData['discount_value']) ? (float) str_replace(',', '', (string) $invoiceData['discount_value']) : 0.00;
        $invoiceData['discount_amount'] = ! empty($invoiceData['discount_amount']) ? (float) str_replace(',', '', (string) $invoiceData['discount_amount']) : 0.00;
        $invoiceData['tax_rate'] = ! empty($invoiceData['tax_rate']) ? (float) $invoiceData['tax_rate'] : 0.00;
        $invoiceData['tax_amount'] = ! empty($invoiceData['tax_amount']) ? (float) $invoiceData['tax_amount'] : 0.00;

        if (empty($invoiceData['tax_type_id'])) {
            $invoiceData['tax_type_id'] = null;
        }
        if (empty($invoiceData['due_date'])) {
            $invoiceData['due_date'] = null;
        }

        // Insert into invoices table and get ID
        if ($id) {
            DB::table('invoices')->where('id', $id)->update($invoiceData);
            DB::table('invoice_items')->where('invoice_id', $id)->delete();
            $invoiceId = $id;
        } else {
            $invoiceId = DB::table('invoices')->insertGetId($invoiceData);
        }

        // 2. Handle Invoice Items (Repeatable Rows)
        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);
        $typeIds = $request->input('item_type_id', []);

        $itemsToInsert = [];
        $subtotal = 0;
        for ($i = 0; $i < count($descriptions); $i++) {
            if (! empty($descriptions[$i])) {
                $qty = isset($qtys[$i]) ? (float) $qtys[$i] : 1;
                $price = isset($prices[$i]) ? (float) $prices[$i] : 0;
                $lineTotal = round($qty * $price, 2);
                $subtotal += $lineTotal;

                $itemsToInsert[] = [
                    'invoice_id' => $invoiceId,
                    'invoice_type_id' => ! empty($typeIds[$i]) ? $typeIds[$i] : (DB::table('invoice_types')->value('id') ?? 1),
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

        if (! empty($itemsToInsert)) {
            DB::table('invoice_items')->insert($itemsToInsert);
        }

        // Calculate overall invoice tax rate, discount, and grand total
        $taxTypeId = $request->input('tax_type_id');
        $taxRate = 0.0;
        if ($taxTypeId) {
            $taxType = DB::table('tax_types')->where('id', $taxTypeId)->first();
            $taxRate = $taxType ? (float) $taxType->rate : 0.0;
        }

        $discountType = $request->input('discount_type', 'fixed');
        $rawDiscVal = $request->input('discount_value', $request->input('discount_amount', 0));
        $discountValue = max(0, (float) str_replace(',', '', (string) $rawDiscVal));

        $discountAmount = 0.0;
        if ($discountType === 'percentage') {
            $discountAmount = round($subtotal * ($discountValue / 100), 2);
        } else {
            $discountAmount = $discountValue;
        }

        if ($discountAmount > $subtotal) {
            throw ValidationException::withMessages(['discount_value' => 'Discount cannot exceed the subtotal.']);
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
            'grand_total' => $grandTotal,
        ]);

        if ($request->filled('payment_milestone_id')) {
            DB::table('payment_milestones')
                ->where('id', $request->input('payment_milestone_id'))->where('project_id', $invoiceData['project_id'] ?? null)->where('status', 'pending')
                ->update(['status' => 'invoiced', 'invoice_id' => $invoiceId, 'updated_at' => now()]);
        }

        InvoicePostingService::sync($invoiceId);
        ActivityLogService::logCreate('Invoice', $invoiceId, [
            'invoice_no' => $invoiceData['invoice_no'],
            'project_id' => $invoiceData['project_id'] ?? null,
            'grand_total' => $grandTotal,
        ]);

        return redirect()->back()->with('success', 'Invoice generated successfully!');
    }

    public function edit($id)
    {
        $invoice = DB::table('invoices')->find($id);
        abort_unless($invoice, 404);
        abort_unless($invoice->status === 'draft', 422, 'Only drafts can be edited.');

        return view('invoice-edit', ['invoice' => $invoice, 'items' => DB::table('invoice_items')->where('invoice_id', $id)->get(), 'types' => DB::table('invoice_types')->get(), 'taxTypes' => DB::table('tax_types')->get()]);
    }

    public function duplicate($id)
    {
        $original = DB::table('invoices')->find($id);
        abort_unless($original, 404);
        $copy = (array) $original;
        unset($copy['id']);
        $copy = array_replace($copy, ['invoice_no' => 'INV-'.date('Ymd').'-'.strtoupper(Str::random(8)), 'status' => 'draft', 'issue_date' => date('Y-m-d'), 'invoice_date' => date('Y-m-d'), 'due_date' => null, 'advance_paid' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $newId = DB::table('invoices')->insertGetId($copy);
        foreach (DB::table('invoice_items')->where('invoice_id', $id)->get() as $item) {
            $line = (array) $item;
            unset($line['id']);
            $line['invoice_id'] = $newId;
            $line['created_at'] = now();
            DB::table('invoice_items')->insert($line);
        }
        ActivityLogService::logCreate('Invoice', $newId, ['copied_from' => $id, 'status' => 'draft']);

        return redirect('/invoices/'.$newId.'/edit')->with('success', 'Draft copy created. Review dates and line items before issuing.');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending_approval,sent,cancelled']);
        $status = $request->input('status');
        $oldInvoice = DB::table('invoices')->where('id', $id)->lockForUpdate()->first();
        abort_unless($oldInvoice, 404);
        PeriodLockService::checkLockedDate($oldInvoice->issue_date ?? $oldInvoice->invoice_date ?? substr($oldInvoice->created_at, 0, 10), DB::table('departments')->where('id', $oldInvoice->department_id)->value('company_id'));
        $allowed = ['draft' => ['pending_approval', 'sent', 'cancelled'], 'pending_approval' => ['sent', 'cancelled'], 'sent' => ['cancelled'], 'overdue' => ['cancelled']];
        if (! in_array($status, $allowed[$oldInvoice->status] ?? []) || DB::table('payment_allocations')->where('invoice_id', $id)->exists()) {
            throw ValidationException::withMessages(['status' => 'This invoice cannot make that status transition. Payment status is calculated from receipts.']);
        }
        if ($oldInvoice->status === 'pending_approval') {
            abort_unless($request->user()->isAdmin() || $request->user()->role === 'manager', 403);
        }
        DB::table('invoices')->where('id', $id)->update(['status' => $status]);
        InvoicePostingService::sync($id);

        ActivityLogService::logUpdate('Invoice', $id, [
            'status' => $oldInvoice->status ?? 'N/A',
        ], [
            'status' => $status,
        ]);

        return redirect()->back()->with('success', 'Invoice status updated successfully!');
    }

    public function destroy($id)
    {
        $oldInvoice = DB::table('invoices')->where('id', $id)->first();
        abort_unless($oldInvoice, 404);
        if ($oldInvoice->status !== 'draft' || DB::table('payment_allocations')->where('invoice_id', $id)->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Only unpaid draft invoices can be deleted. Cancel issued invoices to preserve their history.']);
        }
        PeriodLockService::checkLockedDate($oldInvoice->issue_date ?? $oldInvoice->invoice_date ?? substr($oldInvoice->created_at, 0, 10), DB::table('departments')->where('id', $oldInvoice->department_id)->value('company_id'));
        DB::table('invoices')->where('id', $id)->delete();

        ActivityLogService::logDelete('Invoice', $id, [
            'invoice_no' => $oldInvoice->invoice_no ?? 'N/A',
        ]);

        return redirect()->back()->with('success', 'Invoice deleted successfully!');
    }

    private function resolveDepartmentTemplate($project, $invoice)
    {
        if (! empty($invoice->template_snapshot)) {
            return (object) json_decode($invoice->template_snapshot, true);
        }
        $departmentId = $project->department_id ?? ($invoice->department_id ?? null);
        $template = null;
        if ($departmentId) {
            $template = DB::table('document_templates')
                ->where('department_id', $departmentId)
                ->where('is_default', true)
                ->first();
            if (! $template) {
                $template = DB::table('document_templates')
                    ->where('department_id', $departmentId)
                    ->first();
            }
        }
        if (! $template) {
            $template = DB::table('document_templates')->where('is_default', true)->first();
            if (! $template) {
                $template = DB::table('document_templates')->first();
            }
        }

        return $template;
    }

    public function downloadPdf($id)
    {
        $invoice = DB::table('invoices')->where('id', $id)->first();
        if (! $invoice) {
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
        if (! $invoice) {
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
