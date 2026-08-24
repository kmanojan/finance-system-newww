@extends('layouts.app')
@section('title', 'Invoices')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Invoicing</h2>
    <nav class="nav-links">
        <a href="#invoices-tab" class="nav-link active" onclick="switchTab('invoices-tab', this)">All Invoices</a>
        <a href="/payables/vendor-bills" class="nav-link">Vendor Bills (AP)</a>
        <a href="#payments-tab" class="nav-link" onclick="switchTab('payments-tab', this)">Payments</a>
        <a href="#reminders-tab" class="nav-link" onclick="switchTab('reminders-tab', this)">Reminders</a>
    </nav>
</aside>

@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Invoices</h1>
        <p class="subtitle">Generate, send, and track client invoices.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openModal('createInvModal')">
        <ion-icon name="add-outline"></ion-icon> Create Invoice
    </button>
</header>

<style>
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" action="/invoices" style="display:flex; gap:0.5rem; align-items:center; margin:0;">
            <div style="width: 250px;">
                <x-client-selector name="client_id" :clients="$clients" :selected="request('client_id')" />
            </div>
            <button class="btn btn-outline" type="submit" style="padding: 0.5rem 1rem;">Filter</button>
            @if(request('client_id'))
                <a href="/invoices" class="btn btn-outline" style="color: var(--text-muted); padding: 0.5rem 1rem;">Clear</a>
            @endif
        </form>
    </div>
    <div class="toolbar-right">
        <div class="search-input">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" placeholder="Search...">
        </div>
    </div>
</div>

<div id="invoices-tab" class="tab-content active">

@if(isset($clientMetrics))
<div class="summary-cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
    <div class="card" style="padding:1.25rem; border-left:4px solid var(--primary);">
        <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Total Invoices Sent</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--text-heading); margin-top:0.4rem;" class="tabular-nums">{{ $baseCurrency ?? 'LKR' }} {{ number_format($clientMetrics->total_sent, 2) }}</div>
    </div>
    <div class="card" style="padding:1.25rem; border-left:4px solid var(--success);">
        <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Total Paid</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--success); margin-top:0.4rem;" class="tabular-nums">{{ $baseCurrency ?? 'LKR' }} {{ number_format($clientMetrics->total_paid, 2) }}</div>
    </div>
    <div class="card" style="padding:1.25rem; border-left:4px solid var(--danger);">
        <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Balance to Pay</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--danger); margin-top:0.4rem;" class="tabular-nums">{{ $baseCurrency ?? 'LKR' }} {{ number_format($clientMetrics->balance, 2) }}</div>
    </div>
    <div class="card" style="padding:1.25rem; border-left:4px solid var(--warning);">
        <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Draft Invoices</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--warning); margin-top:0.4rem;" class="tabular-nums">{{ $baseCurrency ?? 'LKR' }} {{ number_format($clientMetrics->total_draft, 2) }}</div>
    </div>
</div>
@endif

@if($invoices->isEmpty())
    <x-empty-state 
        icon="document-text-outline" 
        title="No Invoices Found" 
        description="Generate invoices, send them to clients, and track received payments." 
        actionModal="createInvModal" 
        actionText="Create Invoice" 
    />
@else
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Client</th>
                <th class="text-right">Total Amount</th>
                <th class="text-right">Paid Amount</th>
                <th>Due Date</th>
                <th class="text-center">Status</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
            <tr>
                <td data-label="Invoice #"><span class="font-medium" style="color:var(--text-heading);">{{ $invoice->invoice_no }}</span></td>
                <td data-label="Client"><span class="text-muted">{{ collect($clients)->firstWhere('id', $invoice->client_id)->name ?? 'Unknown' }}</span></td>
                <td data-label="Total Amount" class="amount-cell"><span class="text-heading font-medium tabular-nums">{{ $invoice->currency ?? ($baseCurrency ?? 'LKR') }} {{ number_format($invoice->amount, 2) }}</span></td>
                <td data-label="Paid Amount" class="amount-cell"><span class="text-success font-medium tabular-nums" style="color:var(--success);">{{ $invoice->currency ?? ($baseCurrency ?? 'LKR') }} {{ number_format($invoice->paid_amount, 2) }}</span></td>
                <td data-label="Due Date"><span class="text-muted">{{ $invoice->due_date }}</span></td>
                <td data-label="Status" class="text-center">
                    @if($invoice->status === 'paid')
                        <span class="badge badge-success">Paid</span>
                    @elseif($invoice->status === 'overdue')
                        <span class="badge badge-danger">Overdue</span>
                    @else
                        <span class="badge badge-warning">{{ ucfirst($invoice->status) }}</span>
                    @endif
                </td>
                <td data-label="Action" class="text-center">
                    <div class="actions" style="justify-content:center;">
                        <a href="/invoices/{{ $invoice->id }}/pdf" class="action-btn" title="Download PDF"><ion-icon name="download-outline"></ion-icon></a>
                        <form id="delete_inv_{{ $invoice->id }}" action="/invoices/{{ $invoice->id }}" method="POST" style="display:inline; margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="action-btn action-danger" title="Delete Invoice" onclick="return confirmAction({title:'Delete Invoice?', message:'Delete invoice {{ addslashes($invoice->invoice_no) }}?', confirmText:'Delete Invoice', formId:'delete_inv_{{ $invoice->id }}'})">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
</div> <!-- End invoices-tab -->

<div id="payments-tab" class="tab-content">
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice #</th>
                <th>Total Amount</th>
                <th>Currency</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td data-label="Date"><span class="font-medium">{{ $payment->payment_date }}</span></td>
                <td data-label="Invoice #"><span class="text-muted">{{ $payment->invoice_no ?? 'Project Payment' }}</span></td>
                <td data-label="Total Amount"><span class="text-heading font-medium" style="color:var(--success);">${{ number_format($payment->total_amount, 2) }}</span></td>
                <td data-label="Currency"><span class="badge" style="background:#f1f5f9;color:#475569;">{{ $payment->currency }}</span></td>
            </tr>
            @endforeach
            @if($payments->isEmpty())
            <tr><td colspan="4" class="text-center text-muted py-4">No payments recorded.</td></tr>
            @endif
        </tbody>
    </table>
</div>
</div> <!-- End payments-tab -->

<div id="reminders-tab" class="tab-content">
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Due Date</th>
                <th>Type</th>
                <th>Status</th>
                <th>Notify Before</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reminders as $reminder)
            <tr>
                <td data-label="Due Date"><span class="font-medium">{{ $reminder->due_date }}</span></td>
                <td data-label="Type"><span class="text-muted">{{ ucfirst(str_replace('_', ' ', $reminder->type)) }}</span></td>
                <td data-label="Status">
                    @if($reminder->status === 'settled')
                        <span class="badge" style="background:#DCFCE7;color:#166534;">Settled</span>
                    @elseif($reminder->status === 'snoozed')
                        <span class="badge" style="background:#fef3c7;color:#d97706;">Snoozed</span>
                    @elseif($reminder->status === 'pending' && \Carbon\Carbon::parse($reminder->due_date)->isPast())
                        <span class="badge badge-expired">Overdue</span>
                    @else
                        <span class="badge badge-draft">{{ ucfirst($reminder->status) }}</span>
                    @endif
                </td>
                <td data-label="Notify Before"><span class="text-muted">{{ $reminder->notify_before_days }} days</span></td>
            </tr>
            @endforeach
            @if($reminders->isEmpty())
            <tr><td colspan="4" class="text-center text-muted py-4">No reminders found.</td></tr>
            @endif
        </tbody>
    </table>
</div>
</div> <!-- End reminders-tab -->
@endsection

@section('modals')
<div class="modal-backdrop" id="createInvModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Create New Invoice</h3>
            <button class="btn-close" onclick="closeModal('createInvModal')">&times;</button>
        </div>
        <form action="/invoices" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-control" required>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Invoice Date</label>
                        <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Total Amount</label>
                        <x-amount-input name="amount" class="form-control" required="true" />
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Notes / Terms</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createInvModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Generate Invoice</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function switchTab(tabId, element) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.sidebar-secondary .nav-link').forEach(el => el.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        if (element) {
            element.classList.add('active');
        }
    }
</script>
@endsection
