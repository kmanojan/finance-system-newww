@extends('reports.layout')
@section('title', 'Client Statement of Account')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Client Statement of Account</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Consolidated invoice & payment ledger per client from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="card" style="padding: 1.25rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border); margin-bottom:1.5rem;">
    <form action="/reports/client-statement" method="GET" style="display:flex; gap:1rem; align-items:flex-end;">
        <input type="hidden" name="from" value="{{ $from }}">
        <input type="hidden" name="to" value="{{ $to }}">
        <div style="flex:1;">
            <label class="form-label">Select Client *</label>
            <select name="client_id" class="form-control" required onchange="this.form.submit()">
                <option value="">-- Choose Client --</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary-gradient">Generate Statement</button>
    </form>
</div>

@if($selectedClient)
<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <div style="padding: 1.25rem; background:var(--bg-page); border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:var(--text-heading);">Statement for: {{ $selectedClient->name }}</h3>
            <span style="font-size:0.8rem; color:var(--text-muted);">Contact: {{ $selectedClient->contact_person ?? '-' }} | {{ $selectedClient->email ?? '' }}</span>
        </div>
    </div>
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.8rem;">Date</th>
                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.8rem;">Entry Type</th>
                <th style="padding:0.75rem 1rem; text-align:left; font-size:0.8rem;">Ref / Notes</th>
                <th style="padding:0.75rem 1rem; text-align:right; font-size:0.8rem;">Invoice Amount</th>
                <th style="padding:0.75rem 1rem; text-align:right; font-size:0.8rem;">Payment Collected</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statement as $item)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:0.75rem 1rem; font-size:0.85rem;">{{ $item->date }}</td>
                <td style="padding:0.75rem 1rem;">
                    <span class="badge" style="background:{{ $item->entry_type === 'Invoice' ? 'var(--primary-light)' : 'var(--success-light)' }}; color:{{ $item->entry_type === 'Invoice' ? 'var(--primary)' : 'var(--success)' }}; font-weight:700;">
                        {{ $item->entry_type }}
                    </span>
                </td>
                <td style="padding:0.75rem 1rem; font-weight:600;">
                    {{ $item->entry_type === 'Invoice' ? 'Invoice #' . $item->invoice_number : 'Payment Received' }}
                </td>
                <td style="padding:0.75rem 1rem; text-align:right; font-weight:700;">
                    {{ $item->entry_type === 'Invoice' ? number_format($item->amount, 2) : '-' }}
                </td>
                <td style="padding:0.75rem 1rem; text-align:right; font-weight:700; color:var(--success);">
                    {{ $item->entry_type === 'Payment' ? number_format($item->total_amount, 2) : '-' }}
                </td>
            </tr>
            @endforeach
            @if($statement->isEmpty())
            <tr><td colspan="5" class="text-center text-muted py-4" style="padding:2rem; text-align:center;">No transaction or invoice ledger records found for this client in the selected date range.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endif
@endsection
