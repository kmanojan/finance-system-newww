@extends('layouts.app')
@section('title', 'Approval Inbox')

@section('secondary-sidebar')
    @include('operations._sidebar')
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Global Approval Inbox</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Single screen listing all items awaiting confirmation (Draft Invoices, Over-budget allocations, and Loan closures).</p>
    </div>
</header>

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #86efac;">
    {{ session('success') }}
</div>
@endif

<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Total Pending Approvals</h3>
            <ion-icon name="checkbox-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $totalPendingCount }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Requires admin confirmation</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Draft Invoices</h3>
            <ion-icon name="document-text-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $pendingInvoices->count() }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Scheduled & manual drafts</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Over-budget Allocations</h3>
            <ion-icon name="alert-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $pendingCostAllocations->count() }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">High cost threshold</div>
    </div>
</div>

<!-- Pending Draft Invoices Section -->
<div class="card" style="padding: 1.5rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border); margin-bottom:1.5rem;">
    <h3 style="font-size:1.1rem; font-weight:700; color:var(--text-heading); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
        <ion-icon name="document-text-outline" style="color:var(--primary);"></ion-icon> Pending Draft Invoices ({{ $pendingInvoices->count() }})
    </h3>
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.8rem;">Invoice #</th>
                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.8rem;">Project Name</th>
                <th style="padding:0.75rem 1rem; text-align:right; font-size:0.8rem;">Amount</th>
                <th style="padding:0.75rem 1rem; text-align:center; font-size:0.8rem;">Date</th>
                <th style="padding:0.75rem 1rem; text-align:center; font-size:0.8rem;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pendingInvoices as $inv)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:0.75rem 1rem; font-weight:700; color:var(--primary);">{{ $inv->invoice_number }}</td>
                <td style="padding:0.75rem 1rem; font-weight:600;">{{ $inv->project_name }}</td>
                <td style="padding:0.75rem 1rem; text-align:right; font-weight:700;">{{ $inv->currency }} {{ number_format($inv->amount, 2) }}</td>
                <td style="padding:0.75rem 1rem; text-align:center; font-size:0.85rem;">{{ $inv->invoice_date }}</td>
                <td style="padding:0.75rem 1rem; text-align:center;">
                    <form action="/approvals/invoice/{{ $inv->id }}/approve" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="padding:0.25rem 0.6rem; font-size:0.78rem; border-radius:6px;">Approve</button>
                    </form>
                    <form action="/approvals/invoice/{{ $inv->id }}/reject" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-outline" style="padding:0.25rem 0.6rem; font-size:0.78rem; border-radius:6px; color:var(--danger); border-color:var(--danger);">Reject</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if($pendingInvoices->isEmpty())
            <tr><td colspan="5" class="text-center text-muted py-3" style="padding:1.5rem; text-align:center;">No pending draft invoices.</td></tr>
            @endif
        </tbody>
    </table>
</div>

<!-- Pending Loan Closure Requests -->
<div class="card" style="padding: 1.5rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <h3 style="font-size:1.1rem; font-weight:700; color:var(--text-heading); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
        <ion-icon name="card-outline" style="color:var(--primary);"></ion-icon> Pending Loan Closures ({{ $pendingLoans->count() }})
    </h3>
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.8rem;">Lender</th>
                <th style="padding:0.75rem 1rem; text-align:right; font-size:0.8rem;">Principal</th>
                <th style="padding:0.75rem 1rem; text-align:right; font-size:0.8rem;">Outstanding</th>
                <th style="padding:0.75rem 1rem; text-align:center; font-size:0.8rem;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pendingLoans as $loan)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:0.75rem 1rem; font-weight:700; color:var(--text-heading);">{{ $loan->lender_name }}</td>
                <td style="padding:0.75rem 1rem; text-align:right;">{{ $loan->currency }} {{ number_format($loan->principal_amount, 2) }}</td>
                <td style="padding:0.75rem 1rem; text-align:right; font-weight:700; color:var(--danger);">{{ $loan->currency }} {{ number_format($loan->outstanding_principal, 2) }}</td>
                <td style="padding:0.75rem 1rem; text-align:center;">
                    <form action="/approvals/loan/{{ $loan->id }}/approve" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="padding:0.25rem 0.6rem; font-size:0.78rem; border-radius:6px;">Approve Closure</button>
                    </form>
                    <form action="/approvals/loan/{{ $loan->id }}/reject" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-outline" style="padding:0.25rem 0.6rem; font-size:0.78rem; border-radius:6px; color:var(--danger); border-color:var(--danger);">Reject</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if($pendingLoans->isEmpty())
            <tr><td colspan="4" class="text-center text-muted py-3" style="padding:1.5rem; text-align:center;">No pending loan closure requests.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
