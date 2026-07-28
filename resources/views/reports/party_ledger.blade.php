@extends('reports.layout')
@section('title', 'Party Payables & Full Ledger Report')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Party Payables & Full Financial Ledger</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Consolidated 360° financial transactions, payables, and paids across all parties (Clients, Vendors, Lenders, Partners).</p>
    </div>
</header>

<!-- Filter Toolbar -->
<div class="card" style="padding:1.2rem; margin-bottom:1.5rem; background:var(--bg-card); border:1px solid var(--border);">
    <form method="GET" action="{{ route('reports.party_ledger') }}" style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
            <label class="form-label font-medium">Filter Party Role</label>
            <select name="role" class="form-control">
                <option value="all" {{ $roleFilter == 'all' ? 'selected' : '' }}>All Roles (Clients, Vendors, Lenders, Partners)</option>
                <option value="client" {{ $roleFilter == 'client' ? 'selected' : '' }}>Clients (AR)</option>
                <option value="vendor" {{ $roleFilter == 'vendor' ? 'selected' : '' }}>Vendors & Suppliers (AP)</option>
                <option value="lender" {{ $roleFilter == 'lender' ? 'selected' : '' }}>Lenders, Banks & Directors (Loans)</option>
                <option value="partner" {{ $roleFilter == 'partner' ? 'selected' : '' }}>Project Partners (Commissions)</option>
            </select>
        </div>
        <div style="flex:1; min-width:220px;">
            <label class="form-label font-medium">Select Specific Party Statement</label>
            <select name="party_id" class="form-control">
                <option value="">-- All Parties Summary --</option>
                @foreach($partySummaries as $ps)
                    <option value="{{ $ps->id }}" {{ $selectedPartyId == $ps->id ? 'selected' : '' }}>
                        {{ $ps->name }} ({{ str_replace(',', ', ', $ps->types) }})
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary-gradient">
            <ion-icon name="funnel-outline"></ion-icon> Filter Ledger
        </button>
        @if($selectedPartyId || ($roleFilter && $roleFilter !== 'all'))
            <a href="{{ route('reports.party_ledger') }}" class="btn btn-outline" style="color:var(--text-muted);">Reset</a>
        @endif
    </form>
</div>

<!-- Consolidated Summary KPI Cards -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="metric-card" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding:1.25rem; border-radius:12px; color:white;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; opacity:0.9;">Total Invoiced (AR)</div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($partySummaries->sum('total_invoiced'), 2) }}</div>
    </div>
    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding:1.25rem; border-radius:12px; color:white;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; opacity:0.9;">Collections & Paids Settled</div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($partySummaries->sum('total_collected') + $partySummaries->sum('total_paids'), 2) }}</div>
    </div>
    <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #f43f5e 100%); padding:1.25rem; border-radius:12px; color:white;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; opacity:0.9;">Total Payables (AP + Loans)</div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($partySummaries->sum('total_payables'), 2) }}</div>
    </div>
</div>

@if($partyDetail)
<!-- Party Detailed Transaction Statement Timeline -->
<div class="card" style="padding:1.5rem; margin-bottom:2rem; border-left:4px solid var(--primary);">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:1.4rem; color:var(--text-heading);">Full Financial Ledger Statement: {{ $partyDetail->name }}</h2>
            <div style="margin-top:0.3rem; color:var(--text-muted); font-size:0.9rem;">
                Roles: <span class="badge badge-draft">{{ str_replace(',', ', ', $partyDetail->types) }}</span> | 
                Contact: {{ $partyDetail->contact_person ?? 'N/A' }} ({{ $partyDetail->phone ?? $partyDetail->email }})
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Net Account Position</div>
            <div style="font-size:1.6rem; font-weight:800; color:{{ $partyDetail->net_balance >= 0 ? 'var(--success)' : 'var(--danger)' }};">
                LKR {{ number_format(abs($partyDetail->net_balance), 2) }} {{ $partyDetail->net_balance >= 0 ? 'DR (Receivable)' : 'CR (Payable)' }}
            </div>
        </div>
    </div>
</div>

<div class="card" style="padding:0; overflow-x:auto;">
    <table class="data-table" style="width:100%; margin:0;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Transaction Type</th>
                <th>Reference #</th>
                <th>Description</th>
                <th style="text-align:right;">Debit (DR)</th>
                <th style="text-align:right;">Credit (CR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($partyTimeline as $tx)
            <tr>
                <td>{{ \Carbon\Carbon::parse($tx->date)->format('Y-m-d') }}</td>
                <td><span class="badge badge-draft">{{ $tx->type }}</span></td>
                <td><span class="font-medium">{{ $tx->reference }}</span></td>
                <td>{{ $tx->description }}</td>
                <td style="text-align:right; font-weight:600; color:var(--primary);">
                    {{ $tx->debit > 0 ? 'LKR ' . number_format($tx->debit, 2) : '-' }}
                </td>
                <td style="text-align:right; font-weight:600; color:var(--success);">
                    {{ $tx->credit > 0 ? 'LKR ' . number_format($tx->credit, 2) : '-' }}
                </td>
            </tr>
            @endforeach
            @if($partyTimeline->isEmpty())
            <tr>
                <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No ledger transaction history recorded for this party.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

@else

<!-- Consolidated Party Summary Table -->
<div class="card" style="padding:0; overflow-x:auto;">
    <table class="data-table" style="width:100%; margin:0;">
        <thead>
            <tr>
                <th>Party Name</th>
                <th>Roles / Types</th>
                <th style="text-align:right;">Client Invoiced</th>
                <th style="text-align:right;">Collections Received</th>
                <th style="text-align:right;">Vendor / Loan Payables</th>
                <th style="text-align:right; color:var(--success);">Paids Settled</th>
                <th style="text-align:right; color:var(--danger);">Net Outstanding Balance</th>
                <th style="text-align:center;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($partySummaries as $ps)
            <tr>
                <td>
                    <strong style="color:var(--text-heading); font-size:0.95rem;">{{ $ps->name }}</strong>
                    <div style="font-size:0.75rem; color:var(--text-muted);">{{ $ps->contact_person }}</div>
                </td>
                <td><span class="badge badge-draft">{{ str_replace(',', ', ', $ps->types) }}</span></td>
                <td style="text-align:right;">LKR {{ number_format($ps->total_invoiced, 2) }}</td>
                <td style="text-align:right;">LKR {{ number_format($ps->total_collected, 2) }}</td>
                <td style="text-align:right; font-weight:600; color:var(--danger);">LKR {{ number_format($ps->total_payables, 2) }}</td>
                <td style="text-align:right; font-weight:600; color:var(--success);">LKR {{ number_format($ps->total_paids, 2) }}</td>
                <td style="text-align:right; font-weight:800; color:{{ $ps->net_balance >= 0 ? 'var(--success)' : 'var(--danger)' }}; font-size:1rem;">
                    LKR {{ number_format(abs($ps->net_balance), 2) }} {{ $ps->net_balance >= 0 ? 'DR' : 'CR' }}
                </td>
                <td style="text-align:center;">
                    <a href="{{ route('reports.party_ledger', ['party_id' => $ps->id]) }}" class="btn btn-outline" style="padding:0.3rem 0.6rem; font-size:0.8rem;">
                        View Ledger
                    </a>
                </td>
            </tr>
            @endforeach
            @if($partySummaries->isEmpty())
            <tr>
                <td colspan="8" style="text-align:center; padding:2rem; color:var(--text-muted);">No party records found.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endif
@endsection
