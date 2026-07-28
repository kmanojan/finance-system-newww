@extends('layouts.app')
@section('title', 'Accounts Payable & Vendor Bills')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Payables & AP</h2>
    <nav class="nav-links">
        <a href="/invoices" class="nav-link">Client Invoices (AR)</a>
        <a href="/payables/vendor-bills" class="nav-link active">Vendor Bills (AP)</a>
        <a href="/payables/purchase-orders" class="nav-link">Purchase Orders</a>
    </nav>
</aside>

@section('content')
<header class="page-header" style="margin-bottom: 2rem;">
    <div class="header-titles">
        <h1>Accounts Payable (Payables)</h1>
        <p class="subtitle">Manage vendor bills, purchase orders, and AP double-entry ledger postings.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openModal('createVendorBillModal')">
        <ion-icon name="add-outline"></ion-icon> Create Vendor Bill
    </button>
</header>

<div class="summary-cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <div class="card" style="padding:1.5rem; border-left:4px solid var(--primary);">
        <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Total Payable</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--text-heading); margin-top:0.5rem;">LKR {{ number_format($bills->sum('amount'), 2) }}</div>
    </div>
    <div class="card" style="padding:1.5rem; border-left:4px solid var(--warning);">
        <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Pending Bills</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--text-heading); margin-top:0.5rem;">{{ $bills->where('status', 'pending')->count() }}</div>
    </div>
    <div class="card" style="padding:1.5rem; border-left:4px solid var(--success);">
        <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Paid Bills</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--text-heading); margin-top:0.5rem;">{{ $bills->where('status', 'paid')->count() }}</div>
    </div>
</div>

<div class="card" style="padding:0; overflow-x:auto;">
    <table class="data-table" style="width:100%; margin:0;">
        <thead>
            <tr>
                <th>Bill #</th>
                <th>Vendor</th>
                <th>Department</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bills as $bill)
            <tr>
                <td><span class="font-medium">{{ $bill->bill_number }}</span></td>
                <td>{{ $bill->vendor->name ?? 'Vendor #'.$bill->vendor_id }}</td>
                <td>{{ $bill->department->name ?? 'General' }}</td>
                <td>{{ $bill->issue_date ? $bill->issue_date->format('Y-m-d') : '-' }}</td>
                <td>{{ $bill->due_date ? $bill->due_date->format('Y-m-d') : '-' }}</td>
                <td style="font-weight:600;">{{ $bill->currency }} {{ number_format($bill->amount, 2) }}</td>
                <td>
                    @if($bill->status === 'paid')
                        <span class="badge" style="background:#dcfce7; color:#166534;">Paid</span>
                    @else
                        <span class="badge badge-draft">Pending</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @if($bills->isEmpty())
            <tr>
                <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">No vendor bills recorded yet.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

<!-- Modal: Create Vendor Bill -->
<div class="modal-backdrop" id="createVendorBillModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Create Vendor Bill</h3>
            <button type="button" class="btn-close" onclick="closeModal('createVendorBillModal')">&times;</button>
        </div>
        <form action="/payables/vendor-bills" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Bill Number *</label>
                        <input type="text" name="bill_number" class="form-control" required placeholder="VB-2026-001">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Vendor *</label>
                        <select name="vendor_id" class="form-control" required>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-col">
                        <label class="form-label">Department *</label>
                        <select name="department_id" class="form-control" required>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Amount *</label>
                        <x-amount-input name="amount" required="true" />
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-col">
                        <label class="form-label">Issue Date *</label>
                        <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createVendorBillModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Vendor Bill</button>
            </div>
        </form>
    </div>
</div>
@endsection
