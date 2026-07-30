<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $deptId = $request->get('department_id');
        $departments = DB::table('departments')->get();
        
        // Fetch projects along with company details, client name, and department name
        $query = DB::table('projects')
            ->leftJoin('project_party', function($join) {
                $join->on('projects.id', '=', 'project_party.project_id')
                    ->where('project_party.role', '=', 'client');
            })
            ->leftJoin('parties', 'project_party.party_id', '=', 'parties.id')
            ->leftJoin('departments', 'projects.department_id', '=', 'departments.id')
            ->select('projects.*', 'parties.name as client_name', 'departments.name as department_name');
            
        if (!empty($deptId)) {
            $deptIds = [$deptId];
            $getDescendants = function($parentId) use (&$getDescendants, $departments, &$deptIds) {
                foreach ($departments as $dept) {
                    if ($dept->parent_id == $parentId) {
                        $deptIds[] = $dept->id;
                        $getDescendants($dept->id);
                    }
                }
            };
            $getDescendants($deptId);
            
            $query->whereIn('projects.department_id', $deptIds);
        }
        
        $projects = $query->get();
            
        // Get milestone counts for today
        foreach ($projects as $project) {
            $project->milestones_due_today_count = DB::table('payment_milestones')
                ->where('project_id', $project->id)
                ->where('status', 'pending')
                ->where('due_date', date('Y-m-d'))
                ->count();
        }
        
        // For the dropdowns in the creation form
        $companies = DB::table('companies')->get();
        $clients = DB::table('parties')->where('types', 'LIKE', '%client%')->where('status', 'active')->get();
        $partners = DB::table('parties')->where('types', 'LIKE', '%partner%')->where('status', 'active')->get();
        
        return view('projects', compact('projects', 'companies', 'clients', 'partners', 'departments', 'deptId'));
    }

    public function store(Request $request)
    {
        // Extract pivot data before inserting into projects table
        $clientId = $request->input('client_id');
        $partnerId = $request->input('partner_id');
        $partnerShare = $request->input('partner_share_percentage', 0);
        
        $projectData = $request->except(['_token', 'client_id', 'partner_id', 'partner_share_percentage']);
        if (!isset($projectData['company_id'])) {
            $projectData['company_id'] = DB::table('companies')->value('id') ?? 1;
        }
        $projectData['created_at'] = now();
        $projectData['updated_at'] = now();

        // Insert project and get the generated ID
        $projectId = DB::table('projects')->insertGetId($projectData);

        // Insert into project_party pivot table if a client was selected
        if (!empty($clientId)) {
            DB::table('project_party')->insert([
                'project_id' => $projectId,
                'party_id' => $clientId,
                'role' => 'client'
            ]);
        }

        // Insert into project_party pivot table if a partner was selected
        if (!empty($partnerId)) {
            DB::table('project_party')->insert([
                'project_id' => $projectId,
                'party_id' => $partnerId,
                'role' => 'partner',
                'share_percentage' => $partnerShare
            ]);
        }

        \App\Services\ActivityLogService::logCreate('Project', $projectId, [
            'name' => $projectData['name'] ?? null,
            'budget_limit' => $projectData['budget_limit'] ?? null,
        ]);

        return back()->with('success', 'Project created and linked successfully!');
    }

    public function update(Request $request, $id)
    {
        $oldProject = DB::table('projects')->where('id', $id)->first();
        $clientId = $request->input('client_id');
        $partnerId = $request->input('partner_id');
        $partnerShare = $request->input('partner_share_percentage', 0);
        
        $projectData = $request->except(['_token', '_method', 'client_id', 'partner_id', 'partner_share_percentage']);
        $projectData['updated_at'] = now();

        DB::table('projects')->where('id', $id)->update($projectData);

        // Update Client
        DB::table('project_party')->where('project_id', $id)->where('role', 'client')->delete();
        if (!empty($clientId)) {
            DB::table('project_party')->insert([
                'project_id' => $id,
                'party_id' => $clientId,
                'role' => 'client'
            ]);
        }

        // Update Partner
        DB::table('project_party')->where('project_id', $id)->where('role', 'partner')->delete();
        if (!empty($partnerId)) {
            DB::table('project_party')->insert([
                'project_id' => $id,
                'party_id' => $partnerId,
                'role' => 'partner',
                'share_percentage' => $partnerShare
            ]);
        }

        \App\Services\ActivityLogService::logUpdate('Project', $id, $oldProject, $projectData);

        return back()->with('success', 'Project updated successfully!');
    }


    public function show($id)
    {
        $project = DB::table('projects')->where('id', $id)->first();
        if (!$project) abort(404);

        $company = DB::table('companies')->where('id', $project->company_id)->first();
        
        $clients = DB::table('project_party')
            ->join('parties', 'project_party.party_id', '=', 'parties.id')
            ->where('project_party.project_id', $id)
            ->where('project_party.role', 'client')
            ->select('parties.*')
            ->get();

        $partners = DB::table('project_party')
            ->join('parties', 'project_party.party_id', '=', 'parties.id')
            ->where('project_party.project_id', $id)
            ->where('project_party.role', 'partner')
            ->select('parties.*', 'project_party.share_percentage')
            ->get();

        $invoices = DB::table('invoices')->where('project_id', $id)->get();
        $invoiceItems = DB::table('invoice_items')->whereIn('invoice_id', $invoices->pluck('id'))->get();
        
        $payments = DB::table('payments')->where('project_id', $id)->get();
        $paymentModes = DB::table('payment_modes')->whereIn('payment_id', $payments->pluck('id'))->get();
        
        $timesheets = DB::table('timesheets')->where('project_id', $id)->orderBy('logged_date', 'desc')->get();
        
        $change_requests = DB::table('change_requests')->where('project_id', $id)->orderBy('created_at', 'desc')->get();
        foreach ($change_requests as $cr) {
            $cr->attachments = DB::table('attachments')
                ->where('model_type', 'ChangeRequest')
                ->where('model_id', $cr->id)
                ->get();
            $cr->links = !empty($cr->external_links) ? json_decode($cr->external_links, true) : [];
        }
        $notes = DB::table('notes')->where('notable_type', 'project')->where('notable_id', $id)->orderBy('created_at', 'desc')->get();
        $interactions = DB::table('interactions')->where('interactionable_type', 'project')->where('interactionable_id', $id)->orderBy('interaction_date', 'desc')->get();
        
        // References for dropdowns
        $invoiceTypes = DB::table('invoice_types')->get();
        $allClients = DB::table('parties')->where('types', 'LIKE', '%client%')->where('status', 'active')->get();
        $allPartners = DB::table('parties')->where('types', 'LIKE', '%partner%')->where('status', 'active')->get();
        
        // Load Commissions
        $commissions = $this->calculateCommissionDetails($id);
        $bankAccounts = DB::table('bank_accounts')->get();
        $allRecipients = DB::table('parties')
            ->where(function($q) {
                $q->where('types', 'LIKE', '%partner%')
                  ->orWhere('types', 'LIKE', '%vendor%');
            })
            ->where('status', 'active')
            ->get();

        // Load Invoice Schedules (CR-2)
        $schedules = DB::table('invoice_schedules')
            ->where('project_id', $id)
            ->get();
        foreach ($schedules as $s) {
            $s->invoices_count = DB::table('invoices')->where('schedule_id', $s->id)->count();
            $s->last_invoice = DB::table('invoices')->where('schedule_id', $s->id)->orderBy('created_at', 'desc')->first();
            $s->items = DB::table('invoice_schedule_items')->where('schedule_id', $s->id)->get();
            $s->history = DB::table('invoices')->where('schedule_id', $s->id)->orderBy('created_at', 'desc')->get();
        }
        $documentTemplates = DB::table('document_templates')->get();
        $allInvoiceTypes = DB::table('invoice_types')->get();

        // Load Draft/Pending Approval Invoices (CR-2 sidebar)
        $draftInvoices = DB::table('invoices')
            ->where('project_id', $id)
            ->whereIn('status', ['draft', 'pending_approval'])
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($draftInvoices as $draft) {
            $draft->items = DB::table('invoice_items')->where('invoice_id', $draft->id)->get();
            $draft->source_name = $draft->schedule_id 
                ? DB::table('invoice_schedules')->where('id', $draft->schedule_id)->value('name') 
                : 'Manual';
        }
        
        // Calculations
        $totalInvoiced = $invoices->whereNotIn('status', ['draft', 'pending_approval'])->sum('amount');
        $totalCollected = $payments->sum('total_amount');
        $allocatedAmount = (float) DB::table('payment_allocations')->whereIn('payment_id', $payments->pluck('id'))->sum('amount');
        $invoiceCollected = max($totalCollected, $allocatedAmount);
        $outstandingBalance = max(0, $totalInvoiced - $invoiceCollected);
        $totalHours = $timesheets->sum('hours');

        // Profit Calculations
        $totalApprovedCR = $change_requests->whereIn('status', ['approved', 'invoiced'])->sum('amount');
        $totalCommission = $commissions->sum('total_commission');

        $costAllocationsList = DB::table('cost_allocations')
            ->leftJoin('employees', 'cost_allocations.employee_id', '=', 'employees.id')
            ->leftJoin('servers', 'cost_allocations.server_id', '=', 'servers.id')
            ->where('cost_allocations.project_id', $id)
            ->select('cost_allocations.*', 'employees.first_name', 'employees.last_name', 'servers.name as server_name')
            ->get();

        $totalCostAllocation = $costAllocationsList->sum('amount');

        $projectProfit = ($project->budget_limit + $totalApprovedCR) - $totalCommission;
        $companyProfit = ($project->budget_limit + $totalApprovedCR) - ($totalCommission + $totalCostAllocation);
        $collectionRate = ($totalInvoiced > 0) ? round(($invoiceCollected / $totalInvoiced) * 100, 1) : 0;

        // Payment Milestones
        $paymentMilestones = DB::table('payment_milestones')
            ->where('project_id', $id)
            ->orderBy('due_date', 'asc')
            ->get();
            
        $milestonesDueTodayCount = $paymentMilestones
            ->where('status', 'pending')
            ->filter(function($m) {
                return \Carbon\Carbon::parse($m->due_date)->isToday();
            })
            ->count();

        // Project Documents
        $documents = DB::table('project_documents')
            ->leftJoin('users', 'project_documents.created_by', '=', 'users.id')
            ->where('project_documents.project_id', $id)
            ->whereNull('project_documents.deleted_at')
            ->select('project_documents.*', 'users.name as uploaded_by_name')
            ->orderBy('project_documents.created_at', 'desc')
            ->get();

        return view('projects-show', compact(
            'project', 'company', 'clients', 'partners', 
            'invoices', 'invoiceItems', 'payments', 'paymentModes', 'timesheets', 
            'change_requests', 'notes', 'interactions', 'invoiceTypes',
            'allClients', 'allPartners', 'commissions', 'bankAccounts', 'allRecipients',
            'schedules', 'documentTemplates', 'allInvoiceTypes', 'draftInvoices',
            'totalInvoiced', 'totalCollected', 'invoiceCollected', 'outstandingBalance', 'totalHours',
            'paymentMilestones', 'milestonesDueTodayCount', 'documents',
            'totalApprovedCR', 'totalCommission', 'totalCostAllocation', 'projectProfit', 'companyProfit',
            'costAllocationsList', 'collectionRate'
        ));
    }

    public function destroy($id)
    {
        DB::table('projects')->where('id', $id)->delete();
        return redirect('/projects')->with('success', 'Project deleted successfully!');
    }

    // --- Payment Milestones ---

    public function storeMilestone(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date'
        ]);

        $data['project_id'] = $id;
        $data['status'] = 'pending';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('payment_milestones')->insert($data);

        return back()->with('success', 'Payment milestone added successfully!');
    }

    public function skipMilestone($id, $milestoneId)
    {
        DB::table('payment_milestones')
            ->where('id', $milestoneId)
            ->where('project_id', $id)
            ->update([
                'status' => 'skipped',
                'updated_at' => now()
            ]);

        return back()->with('success', 'Payment milestone skipped.');
    }

    public function invoiceMilestone(Request $request, $id, $milestoneId)
    {
        $milestone = DB::table('payment_milestones')
            ->where('id', $milestoneId)
            ->where('project_id', $id)
            ->first();

        if (!$milestone || $milestone->status !== 'pending') {
            return back()->with('error', 'Invalid milestone or already processed.');
        }

        // Generate draft invoice
        $project = DB::table('projects')->where('id', $id)->first();
        $client = DB::table('project_party')->where('project_id', $id)->where('role', 'client')->first();
        $clientId = $client ? $client->party_id : (DB::table('parties')->value('id') ?? 1);
        $invoiceTypeId = DB::table('invoice_types')->value('id') ?? 1;
        
        $invoiceId = DB::table('invoices')->insertGetId([
            'client_id' => $clientId,
            'department_id' => $project->department_id ?? (DB::table('departments')->value('id') ?? 1),
            'project_id' => $id,
            'invoice_no' => 'INV-' . strtoupper(uniqid()),
            'status' => 'draft',
            'due_date' => null,
            'issue_date' => now()->format('Y-m-d'),
            'currency' => $project->currency ?? 'LKR',
            'amount' => $milestone->amount,
            'subtotal' => $milestone->amount,
            'discount_type' => 'fixed',
            'discount_value' => 0.00,
            'discount_amount' => 0.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'grand_total' => $milestone->amount,
            'created_at' => now(),
            'updated_at' => now()
        ]);


        DB::table('invoice_items')->insert([
            'invoice_id' => $invoiceId,
            'invoice_type_id' => $invoiceTypeId,
            'description' => 'Milestone: ' . $milestone->name,
            'qty' => 1,
            'unit_price' => $milestone->amount,
            'currency' => $project->currency ?? 'LKR',
            'tax_percentage' => 0,
            'total' => $milestone->amount,
            'created_at' => now()
        ]);

        // Mark milestone as invoiced
        DB::table('payment_milestones')
            ->where('id', $milestoneId)
            ->update([
                'status' => 'invoiced',
                'updated_at' => now()
            ]);

        return back()->with('success', 'Draft invoice generated for the milestone!');
    }

    public function storeTimesheet(Request $request, $id)
    {
        $data = $request->validate([
            'task_description' => 'required|string',
            'hours' => 'required|numeric|min:0.1',
            'logged_date' => 'required|date'
        ]);
        
        $data['project_id'] = $id;
        $data['created_at'] = now();
        
        DB::table('timesheets')->insert($data);
        return back()->with('success', 'Timesheet logged successfully!');
    }

    public function storePayment(Request $request, $id)
    {
        // Fetch project for details
        $project = DB::table('projects')->where('id', $id)->first();
        $departmentId = ($project && $project->department_id) ? $project->department_id : (DB::table('departments')->value('id') ?? 1);
        
        $cat = DB::table('categories')->where('name', 'Project Payment')->first();
        if (!$cat) {
            $companyId = DB::table('companies')->value('id') ?? 1;
            $categoryId = DB::table('categories')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Project Payment',
                'type' => 'income',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $categoryId = $cat->id;
        }

        // Save core payment
        $paymentData = $request->only(['payment_date', 'total_amount', 'currency']);
        $paymentData['project_id'] = $id;
        $paymentData['created_at'] = now();
        
        $paymentId = DB::table('payments')->insertGetId($paymentData);
        
        // Save allocations
        $allocInvoiceIds = $request->input('alloc_invoice_id', []);
        $allocAmounts = $request->input('alloc_amount', []);
        
        $allocInserts = [];
        foreach ($allocInvoiceIds as $idx => $invId) {
            $amt = (float)($allocAmounts[$idx] ?? 0);
            if ($amt > 0) {
                $allocInserts[] = [
                    'payment_id' => $paymentId,
                    'invoice_id' => $invId,
                    'amount' => $amt,
                    'created_at' => now(),
                ];
            }
        }
        
        if (!empty($allocInserts)) {
            DB::table('payment_allocations')->insert($allocInserts);
        }

        // Save dynamic payment modes
        $modes = $request->input('pm_mode', []);
        $amounts = $request->input('pm_amount', []);
        $banks = $request->input('pm_bank', []);
        $chequeNos = $request->input('pm_cheque_no', []);
        $chequeDates = $request->input('pm_cheque_date', []);
        $references = $request->input('pm_reference', []);
        $notes = $request->input('pm_notes', []);

        $modeInserts = [];
        $transactionInserts = [];
        for ($i = 0; $i < count($modes); $i++) {
            if (!empty($amounts[$i])) {
                $modeInserts[] = [
                    'payment_id' => $paymentId,
                    'mode' => $modes[$i],
                    'amount' => $amounts[$i],
                    'bank_name' => $banks[$i] ?? null,
                    'cheque_no' => $chequeNos[$i] ?? null,
                    'cheque_date' => $chequeDates[$i] ?? null,
                    'reference_no' => $references[$i] ?? null,
                    'notes' => $notes[$i] ?? null,
                    'created_at' => now(),
                ];
                
                $refNo = $references[$i] ?? $chequeNos[$i] ?? null;
                $desc = "Project Payment - " . ($project ? $project->name : "Prj {$id}") . " (" . ucfirst($modes[$i]) . ")";
                
                $transactionInserts[] = [
                    'type' => 'income',
                    'category_id' => $categoryId,
                    'department_id' => $departmentId,
                    'amount' => $amounts[$i],
                    'currency' => $paymentData['currency'],
                    'transaction_date' => $paymentData['payment_date'],
                    'description' => $desc,
                    'reference_no' => $refNo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($modeInserts)) {
            DB::table('payment_modes')->insert($modeInserts);
        }
        if (!empty($transactionInserts)) {
            DB::table('transactions')->insert($transactionInserts);
        }

        // Auto-mark invoices based on allocations
        if (!empty($allocInserts)) {
            foreach ($allocInserts as $alloc) {
                $invId = $alloc['invoice_id'];
                $inv = DB::table('invoices')->where('id', $invId)->first();
                if ($inv) {
                    $collected = DB::table('payment_allocations')->where('invoice_id', $invId)->sum('amount');
                    if ($collected >= $inv->amount) {
                        DB::table('invoices')->where('id', $invId)->update(['status' => 'paid']);
                    } elseif ($collected > 0) {
                        DB::table('invoices')->where('id', $invId)->update(['status' => 'partially_paid']);
                    }
                }
            }
        }

        return back()->with('success', 'Payment recorded and synced to Transactions successfully!');
    }

    public function storeChangeRequest(Request $request, $id)
    {
        $data = $request->except('_token', 'auto_create_invoice');
        $data['project_id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        $crId = DB::table('change_requests')->insertGetId($data);

        if ($request->has('auto_create_invoice') && $data['status'] === 'approved' && isset($data['amount']) && $data['amount'] > 0) {
            $project = DB::table('projects')->where('id', $id)->first();
            $clientParty = DB::table('project_party')->where('project_id', $id)->where('role', 'client')->first();
            
            if ($project && $clientParty) {
                $lastInvoice = DB::table('invoices')->orderBy('id', 'desc')->first();
                $nextId = $lastInvoice ? $lastInvoice->id + 1 : 1;
                $invoiceNo = 'INV-CR-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
                
                $crType = DB::table('invoice_types')->where('name', 'CR')->first();
                $invoiceTypeId = $crType ? $crType->id : (DB::table('invoice_types')->value('id') ?? 1);

                $currency = $data['currency'] ?? ($project->currency ?? 'LKR');

                $invoiceId = DB::table('invoices')->insertGetId([
                    'invoice_no' => $invoiceNo,
                    'client_id' => $clientParty->party_id,
                    'project_id' => $id,
                    'department_id' => $project->department_id ?? (DB::table('departments')->value('id') ?? 1),
                    'amount' => $data['amount'],
                    'subtotal' => $data['amount'],
                    'grand_total' => $data['amount'],
                    'currency' => $currency,
                    'status' => 'draft',
                    'due_date' => null,
                    'issue_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'invoice_type_id' => $invoiceTypeId,
                    'description' => $data['description'],
                    'qty' => 1,
                    'unit_price' => $data['amount'],
                    'currency' => $currency,
                    'tax_percentage' => 0,
                    'total' => $data['amount'],
                    'created_at' => now(),
                ]);

                DB::table('change_requests')->where('id', $crId)->update([
                    'status' => 'invoiced',
                    'updated_at' => now()
                ]);
            }
        }

        return back()->with('success', 'Change Request created successfully!');
    }

    public function approveChangeRequest($id, $crId)
    {
        $cr = DB::table('change_requests')->where('id', $crId)->where('project_id', $id)->first();
        if (!$cr) {
            return back()->with('error', 'Change Request not found.');
        }
        
        $status = 'approved';
        
        if ($cr->amount > 0) {
            $project = DB::table('projects')->where('id', $id)->first();
            $clientParty = DB::table('project_party')->where('project_id', $id)->where('role', 'client')->first();
            
            if ($project && $clientParty) {
                $lastInvoice = DB::table('invoices')->orderBy('id', 'desc')->first();
                $nextId = $lastInvoice ? $lastInvoice->id + 1 : 1;
                $invoiceNo = 'INV-CR-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
                
                $crType = DB::table('invoice_types')->where('name', 'CR')->first();
                $invoiceTypeId = $crType ? $crType->id : (DB::table('invoice_types')->value('id') ?? 1);

                $currency = $cr->currency ?? ($project->currency ?? 'LKR');

                $invoiceId = DB::table('invoices')->insertGetId([
                    'invoice_no' => $invoiceNo,
                    'client_id' => $clientParty->party_id,
                    'project_id' => $id,
                    'department_id' => $project->department_id ?? (DB::table('departments')->value('id') ?? 1),
                    'amount' => $cr->amount,
                    'subtotal' => $cr->amount,
                    'grand_total' => $cr->amount,
                    'currency' => $currency,
                    'status' => 'draft',
                    'due_date' => null,
                    'issue_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'invoice_type_id' => $invoiceTypeId,
                    'description' => $cr->description,
                    'qty' => 1,
                    'unit_price' => $cr->amount,
                    'currency' => $currency,
                    'tax_percentage' => 0,
                    'total' => $cr->amount,
                    'created_at' => now(),
                ]);
                
                $status = 'invoiced';
            }
        }
        
        DB::table('change_requests')->where('id', $crId)->update([
            'status' => $status,
            'updated_at' => now()
        ]);
        
        return back()->with('success', 'Change Request approved ' . ($status === 'invoiced' ? 'and invoiced ' : '') . 'successfully!');
    }

    public function updateChangeRequest(Request $request, $id, $crId)
    {
        $cr = DB::table('change_requests')->where('id', $crId)->where('project_id', $id)->first();
        if (!$cr) return back()->with('error', 'Change request not found.');

        $links = !empty($cr->external_links) ? json_decode($cr->external_links, true) : [];

        // Add External Link
        if ($request->filled('link_title') && $request->filled('link_url')) {
            $links[] = [
                'title' => $request->input('link_title'),
                'url' => $request->input('link_url')
            ];
            DB::table('change_requests')->where('id', $crId)->update([
                'external_links' => json_encode($links),
                'updated_at' => now()
            ]);
        }

        // Add File Attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/change_requests', 'public');
                DB::table('attachments')->insert([
                    'model_type' => 'ChangeRequest',
                    'model_id' => $crId,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'uploaded_by' => 1,
                    'created_at' => now(),
                ]);
            }
        }

        return back()->with('success', 'Change Request updated with attachments and external links!');
    }

    public function storeNote(Request $request, $id)
    {
        $data = $request->validate([
            'content' => 'required|string'
        ]);
        $data['notable_type'] = 'project';
        $data['notable_id'] = $id;
        $data['created_at'] = now();
        
        DB::table('notes')->insert($data);
        return back()->with('success', 'Note added successfully!');
    }

    public function storeInteraction(Request $request, $id)
    {
        $data = $request->except('_token');
        $data['interactionable_type'] = 'project';
        $data['interactionable_id'] = $id;
        $data['created_at'] = now();
        
        DB::table('interactions')->insert($data);
        return back()->with('success', 'Interaction logged successfully!');
    }

    private function calculateCommissionDetails($projectId)
    {
        $commissions = DB::table('project_commissions')
            ->join('parties', 'project_commissions.party_id', '=', 'parties.id')
            ->where('project_commissions.project_id', $projectId)
            ->select('project_commissions.*', 'parties.name as party_name', 'parties.types as party_types')
            ->get();

        $project = DB::table('projects')->where('id', $projectId)->first();
        if (!$project) return collect();

        // Gather base variables
        $totalInvoiced = DB::table('invoices')->where('project_id', $projectId)->whereNotIn('status', ['draft', 'pending_approval'])->sum('amount');
        $totalCollected = DB::table('payments')->where('project_id', $projectId)->sum('total_amount');
        $invoiceCount = DB::table('invoices')->where('project_id', $projectId)->whereNotIn('status', ['draft', 'pending_approval'])->count();
        $paymentCount = DB::table('payments')->where('project_id', $projectId)->count();

        foreach ($commissions as $comm) {
            $totalComm = 0;
            if ($comm->commission_type === 'percentage') {
                $percentage = $comm->percentage_value ?? 0;
                if ($comm->calculation_basis === 'invoiced') {
                    $totalComm = $totalInvoiced * ($percentage / 100);
                } elseif ($comm->calculation_basis === 'collected') {
                    $totalComm = $totalCollected * ($percentage / 100);
                } elseif ($comm->calculation_basis === 'budget') {
                    $totalComm = ($project->budget_limit ?? 0) * ($percentage / 100);
                }
            } else { // fixed
                $fixed = $comm->fixed_amount ?? 0;
                if ($comm->trigger_type === 'invoice') {
                    $totalComm = $fixed * $invoiceCount;
                } elseif ($comm->trigger_type === 'milestone') {
                    $totalComm = $fixed * $paymentCount;
                } else {
                    $totalComm = $fixed;
                }
            }

            $paid = DB::table('commission_payments')->where('project_commission_id', $comm->id)->sum('amount');
            
            $comm->total_commission = $totalComm;
            $comm->paid = $paid;
            $comm->payable = max(0, $totalComm - $paid);
        }

        return $commissions;
    }

    public function storeCommission(Request $request, $id)
    {
        $data = $request->except('_token');
        $data['project_id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        if (empty($data['effective_from'])) $data['effective_from'] = date('Y-m-d');

        DB::table('project_commissions')->insert($data);
        return back()->with('success', 'Commission setup added successfully!');
    }

    public function updateCommission(Request $request, $projectId, $commId)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        DB::table('project_commissions')->where('id', $commId)->update($data);
        return back()->with('success', 'Commission setup updated successfully!');
    }

    public function destroyCommission(Request $request, $projectId, $commId)
    {
        $hasPayments = DB::table('commission_payments')->where('project_commission_id', $commId)->exists();
        if ($hasPayments) {
            return back()->with('error', 'Cannot delete commission setup because payments are already recorded against it. Set status to ended instead.');
        }

        DB::table('project_commissions')->where('id', $commId)->delete();
        return back()->with('success', 'Commission setup removed successfully!');
    }

    public function storeCommissionPayment(Request $request, $projectId, $commId)
    {
        $project = DB::table('projects')->where('id', $projectId)->first();
        if (!$project) abort(404);

        $commDetails = $this->calculateCommissionDetails($projectId)->firstWhere('id', $commId);
        if (!$commDetails) abort(404);

        $amount = $request->input('amount');
        if ($amount > $commDetails->payable) {
            return back()->with('error', 'Payment amount cannot exceed the payable balance of ' . number_format($commDetails->payable, 2));
        }

        $paymentData = $request->except(['_token', 'currency']);
        $paymentData['project_commission_id'] = $commId;
        $paymentData['created_at'] = now();
        $paymentData['updated_at'] = now();

        $paymentId = DB::table('commission_payments')->insertGetId($paymentData);

        // Record expense transaction under Commission Expense category
        $cat = DB::table('categories')->where('name', 'Commission Expense')->first();
        if (!$cat) {
            $catId = DB::table('categories')->insertGetId([
                'name' => 'Commission Expense',
                'type' => 'expense',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $catId = $cat->id;
        }

        DB::table('transactions')->insert([
            'type' => 'expense',
            'category_id' => $catId,
            'department_id' => $project->department_id ?? 1,
            'bank_account_id' => $request->input('bank_account_id'),
            'amount' => $amount,
            'currency' => $request->input('currency', $project->currency ?? (DB::table('companies')->value('base_currency') ?? 'LKR')),
            'transaction_date' => $request->input('payment_date'),
            'description' => "Commission Payment to " . $commDetails->party_name . " for project " . $project->name,
            'reference_no' => $request->input('reference_no'),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return back()->with('success', 'Commission payment recorded and synced to expenses successfully!');
    }

    private function calculateNextDate($currentDate, $frequency, $intervalDays = null, $genDay = null)
    {
        $date = \Carbon\Carbon::parse($currentDate);
        if ($frequency === 'monthly') {
            $date->addMonth();
            if ($genDay) {
                $date->day(min($genDay, $date->daysInMonth));
            }
        } elseif ($frequency === 'quarterly') {
            $date->addMonths(3);
            if ($genDay) {
                $date->day(min($genDay, $date->daysInMonth));
            }
        } elseif ($frequency === 'yearly') {
            $date->addYear();
            if ($genDay) {
                $date->day(min($genDay, $date->daysInMonth));
            }
        } elseif ($frequency === 'custom' && $intervalDays) {
            $date->addDays($intervalDays);
        } else {
            $date->addMonth();
        }
        return $date->toDateString();
    }

    private function adjustForWeekends($dateString, $enabled)
    {
        if (!$enabled) return $dateString;
        $date = \Carbon\Carbon::parse($dateString);
        while ($date->isWeekend()) {
            $date->addDay();
        }
        return $date->toDateString();
    }

    private function generateInvoiceFromSchedule($schedule, $generationDate)
    {
        $project = DB::table('projects')->where('id', $schedule->project_id)->first();
        if (!$project) return null;

        $clientParty = DB::table('project_party')
            ->where('project_id', $schedule->project_id)
            ->where('role', 'client')
            ->first();
        $clientId = $clientParty ? $clientParty->party_id : null;

        $dueDate = \Carbon\Carbon::parse($generationDate)->addDays(30)->toDateString();
        $invoiceNo = 'INV-SCH-' . date('Ymd') . '-' . rand(1000, 9999);
        $status = $schedule->require_approval ? 'draft' : 'sent';

        // 1. Fetch template snapshot
        $departmentId = $project->department_id ?? 1;
        $template = DB::table('document_templates')
            ->where('department_id', $departmentId)
            ->where('is_default', true)
            ->first();

        if (!$template) {
            $template = DB::table('document_templates')->where('is_default', true)->first();
            if (!$template) $template = DB::table('document_templates')->first();
        }

         $templateSnapshot = $template ? json_encode([
            'header_image_url' => $template->header_image_url,
            'footer_image_url' => $template->footer_image_url,
            'background_image_url' => $template->background_image_url ?? null,
            'company_details' => $template->company_details,
            'bank_details' => $template->bank_details,
            'description' => $template->description,
            'terms_conditions' => $template->terms_conditions,
            'language' => $template->language ?? 'English'
        ]) : null;

        $invoiceId = DB::table('invoices')->insertGetId([
            'project_id' => $schedule->project_id,
            'client_id' => $clientId,
            'department_id' => $departmentId,
            'invoice_no' => $invoiceNo,
            'amount' => 0,
            'subtotal' => 0,
            'advance_paid' => 0,
            'grand_total' => 0,
            'currency' => $schedule->currency,
            'status' => $status,
            'template_snapshot' => $templateSnapshot,
            'issue_date' => $generationDate,
            'due_date' => $dueDate,
            'schedule_id' => $schedule->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $items = DB::table('invoice_schedule_items')->where('schedule_id', $schedule->id)->get();
        $totalAmount = 0;
        $itemsToInsert = [];

        foreach ($items as $item) {
            $itemTotal = ($item->quantity * $item->unit_price) * (1 + ($item->tax_percentage / 100));
            $totalAmount += $itemTotal;

            $itemsToInsert[] = [
                'invoice_id' => $invoiceId,
                'invoice_type_id' => $schedule->invoice_type_id,
                'description' => $item->description,
                'qty' => $item->quantity,
                'unit_price' => $item->unit_price,
                'currency' => $schedule->currency,
                'tax_percentage' => $item->tax_percentage,
                'total' => $itemTotal,
                'created_at' => now()
            ];
        }

        if (!empty($itemsToInsert)) {
            DB::table('invoice_items')->insert($itemsToInsert);
        }

        DB::table('invoices')->where('id', $invoiceId)->update([
            'amount' => $totalAmount,
            'subtotal' => $totalAmount,
            'grand_total' => $totalAmount
        ]);

        if ($schedule->notify_on_generation) {
            DB::table('reminders')->insert([
                'type' => 'invoice',
                'reference_id' => $invoiceId,
                'reference_type' => 'invoice',
                'due_date' => $dueDate,
                'notify_before_days' => 0,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return $invoiceId;
    }

    public static function checkActiveSchedules()
    {
        $today = date('Y-m-d');
        $schedules = DB::table('invoice_schedules')
            ->where('status', 'active')
            ->where('from_date', '<=', $today)
            ->where(function($q) use ($today) {
                $q->whereNull('to_date')->orWhere('to_date', '>=', $today);
            })
            ->where('next_generation_date', '<=', $today)
            ->get();

        $controller = new self();

        foreach ($schedules as $s) {
            $controller->generateInvoiceFromSchedule($s, $today);
            
            $nextDate = $controller->calculateNextDate($s->next_generation_date, $s->frequency, $s->custom_interval_days, $s->generate_day);
            $nextDate = $controller->adjustForWeekends($nextDate, $s->auto_adjust_holidays);
            
            $updateData = [
                'next_generation_date' => $nextDate,
                'updated_at' => now()
            ];

            if ($s->to_date && $nextDate > $s->to_date) {
                $updateData['status'] = 'completed';
            }

            DB::table('invoice_schedules')->where('id', $s->id)->update($updateData);
        }
    }

    public function storeSchedule(Request $request, $id)
    {
        $scheduleData = $request->only([
            'name', 'from_date', 'to_date', 'frequency', 'custom_interval_days', 
            'generate_day', 'next_generation_date', 'invoice_type_id', 'currency', 
            'template_id', 'notes'
        ]);

        $scheduleData['project_id'] = $id;
        $scheduleData['require_approval'] = $request->has('require_approval') ? 1 : 0;
        $scheduleData['auto_adjust_holidays'] = $request->has('auto_adjust_holidays') ? 1 : 0;
        $scheduleData['notify_on_generation'] = $request->has('notify_on_generation') ? 1 : 0;
        $scheduleData['status'] = 'active';
        $scheduleData['created_at'] = now();
        $scheduleData['updated_at'] = now();

        if (empty($scheduleData['next_generation_date'])) {
            $scheduleData['next_generation_date'] = $scheduleData['from_date'];
        }

        $scheduleId = DB::table('invoice_schedules')->insertGetId($scheduleData);

        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);
        $taxes = $request->input('item_tax', []);

        $items = [];
        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i])) {
                $items[] = [
                    'schedule_id' => $scheduleId,
                    'description' => $descriptions[$i],
                    'quantity' => $qtys[$i] ?? 1,
                    'unit_price' => $prices[$i] ?? 0,
                    'tax_percentage' => $taxes[$i] ?? 0,
                    'created_at' => now()
                ];
            }
        }

        if (!empty($items)) {
            DB::table('invoice_schedule_items')->insert($items);
        }

        return back()->with('success', 'Invoice generation schedule created successfully!');
    }

    public function updateSchedule(Request $request, $projectId, $scheduleId)
    {
        $scheduleData = $request->only([
            'name', 'from_date', 'to_date', 'frequency', 'custom_interval_days', 
            'generate_day', 'next_generation_date', 'invoice_type_id', 'currency', 
            'template_id', 'notes'
        ]);

        $scheduleData['require_approval'] = $request->has('require_approval') ? 1 : 0;
        $scheduleData['auto_adjust_holidays'] = $request->has('auto_adjust_holidays') ? 1 : 0;
        $scheduleData['notify_on_generation'] = $request->has('notify_on_generation') ? 1 : 0;
        $scheduleData['updated_at'] = now();

        DB::table('invoice_schedules')->where('id', $scheduleId)->update($scheduleData);

        DB::table('invoice_schedule_items')->where('schedule_id', $scheduleId)->delete();

        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_price', []);
        $taxes = $request->input('item_tax', []);

        $items = [];
        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i])) {
                $items[] = [
                    'schedule_id' => $scheduleId,
                    'description' => $descriptions[$i],
                    'quantity' => $qtys[$i] ?? 1,
                    'unit_price' => $prices[$i] ?? 0,
                    'tax_percentage' => $taxes[$i] ?? 0,
                    'created_at' => now()
                ];
            }
        }

        if (!empty($items)) {
            DB::table('invoice_schedule_items')->insert($items);
        }

        return back()->with('success', 'Invoice generation schedule updated successfully!');
    }

    public function destroySchedule(Request $request, $projectId, $scheduleId)
    {
        $hasInvoices = DB::table('invoices')->where('schedule_id', $scheduleId)->exists();
        if ($hasInvoices) {
            return back()->with('error', 'Cannot delete schedule because invoices have already been generated from it. Terminate/Cancel it instead.');
        }

        DB::table('invoice_schedules')->where('id', $scheduleId)->delete();
        return back()->with('success', 'Invoice generation schedule deleted successfully!');
    }

    public function pauseSchedule(Request $request, $projectId, $scheduleId)
    {
        DB::table('invoice_schedules')->where('id', $scheduleId)->update([
            'status' => 'paused',
            'updated_at' => now()
        ]);
        return back()->with('success', 'Schedule paused successfully!');
    }

    public function resumeSchedule(Request $request, $projectId, $scheduleId)
    {
        DB::table('invoice_schedules')->where('id', $scheduleId)->update([
            'status' => 'active',
            'updated_at' => now()
        ]);
        return back()->with('success', 'Schedule resumed successfully!');
    }

    public function runScheduleImmediately(Request $request, $projectId, $scheduleId)
    {
        $schedule = DB::table('invoice_schedules')->where('id', $scheduleId)->first();
        if (!$schedule) abort(404);

        $today = date('Y-m-d');
        $this->generateInvoiceFromSchedule($schedule, $today);

        $nextDate = $this->calculateNextDate($schedule->next_generation_date, $schedule->frequency, $schedule->custom_interval_days, $schedule->generate_day);
        $nextDate = $this->adjustForWeekends($nextDate, $schedule->auto_adjust_holidays);

        $updateData = [
            'next_generation_date' => $nextDate,
            'updated_at' => now()
        ];

        if ($schedule->to_date && $nextDate > $schedule->to_date) {
            $updateData['status'] = 'completed';
        }

        DB::table('invoice_schedules')->where('id', $scheduleId)->update($updateData);

        return back()->with('success', 'Invoice auto-generated immediately and next run advanced!');
    }

    public function skipOccurrence(Request $request, $projectId, $scheduleId)
    {
        $schedule = DB::table('invoice_schedules')->where('id', $scheduleId)->first();
        if (!$schedule) abort(404);

        $nextDate = $this->calculateNextDate($schedule->next_generation_date, $schedule->frequency, $schedule->custom_interval_days, $schedule->generate_day);
        $nextDate = $this->adjustForWeekends($nextDate, $schedule->auto_adjust_holidays);

        $updateData = [
            'next_generation_date' => $nextDate,
            'updated_at' => now()
        ];

        if ($schedule->to_date && $nextDate > $schedule->to_date) {
            $updateData['status'] = 'completed';
        }

        DB::table('invoice_schedules')->where('id', $scheduleId)->update($updateData);

        return back()->with('success', 'Next occurrence skipped successfully!');
    }

    public function approveDraftInvoice(Request $request, $projectId, $invoiceId)
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
        if (!$invoice) abort(404);

        $invoiceNo = $invoice->invoice_no;
        if (str_starts_with($invoiceNo, 'INV-SCH-')) {
            $invoiceNo = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        DB::table('invoices')->where('id', $invoiceId)->update([
            'status' => 'sent',
            'invoice_no' => $invoiceNo,
            'issue_date' => date('Y-m-d'),
            'updated_at' => now()
        ]);

        return back()->with('success', 'Draft invoice approved and set to sent!');
    }

    public function rejectDraftInvoice(Request $request, $projectId, $invoiceId)
    {
        DB::table('invoices')->where('id', $invoiceId)->update([
            'status' => 'cancelled',
            'updated_at' => now()
        ]);
        return back()->with('success', 'Draft invoice rejected and cancelled.');
    }
}
