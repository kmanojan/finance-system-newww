@extends('layouts.app')
@section('title', 'Cheques Management')

@section('secondary-sidebar')
    @include('operations._sidebar')
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Cheques Management</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Track incoming & outgoing cheque deposits, clearance dates, and bounced status.</p>
    </div>
    <button class="btn btn-primary-gradient btn-pill" onclick="openModal('createChequeModal')">
        <ion-icon name="add-outline" style="vertical-align:middle;"></ion-icon> Add New Cheque
    </button>
</header>

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #86efac;">
    {{ session('success') }}
</div>
@endif

<!-- Summary Cards -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="metric-card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Pending Deposit</h3>
            <ion-icon name="time-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $pendingCount }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Awaiting bank deposit</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Deposited</h3>
            <ion-icon name="arrow-forward-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $depositedCount }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">In clearing process</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Cleared</h3>
            <ion-icon name="checkmark-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $clearedCount }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Funds realized</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Bounced</h3>
            <ion-icon name="alert-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $bouncedCount }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Action required</div>
    </div>
</div>

<!-- Cheques Table -->
<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Cheque No.</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Bank Name</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Cheque Date</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Amount</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Status</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cheques as $c)
            <tr style="border-bottom: 1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; font-weight:700; color:var(--primary);">{{ $c->cheque_number }}</td>
                <td style="padding:0.85rem 1rem; font-weight:600; color:var(--text-heading);">{{ $c->bank_name }}</td>
                <td style="padding:0.85rem 1rem; text-align:center; font-size:0.88rem;">{{ $c->cheque_date }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:700; color:var(--text-heading);">{{ $c->currency }} {{ number_format($c->amount, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <span class="badge" style="background:{{ $c->status === 'cleared' ? 'var(--success-light)' : ($c->status === 'bounced' ? '#fee2e2' : 'var(--primary-light)') }}; color:{{ $c->status === 'cleared' ? 'var(--success)' : ($c->status === 'bounced' ? '#b91c1c' : 'var(--primary)') }}; font-size:0.75rem; font-weight:600; padding:0.2rem 0.5rem; border-radius:4px; text-transform:uppercase;">
                        {{ str_replace('_', ' ', $c->status) }}
                    </span>
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <div style="display:flex; justify-content:center; gap:0.35rem;">
                        @if($c->status === 'pending_deposit')
                        <form action="/cheques/{{ $c->id }}/status" method="POST" style="display:inline; margin:0;">
                            @csrf
                            <input type="hidden" name="status" value="deposited">
                            <button type="submit" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:5px;">Deposit</button>
                        </form>
                        @endif
                        @if($c->status !== 'cleared')
                        <form action="/cheques/{{ $c->id }}/status" method="POST" style="display:inline; margin:0;">
                            @csrf
                            <input type="hidden" name="status" value="cleared">
                            <button type="submit" class="btn btn-primary" style="padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:5px;">Clear</button>
                        </form>
                        @endif
                        @if($c->status !== 'bounced')
                        <form action="/cheques/{{ $c->id }}/status" method="POST" style="display:inline; margin:0;">
                            @csrf
                            <input type="hidden" name="status" value="bounced">
                            <button type="submit" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:5px; color:var(--danger); border-color:var(--danger);">Bounced</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
            @if($cheques->isEmpty())
            <tr><td colspan="6" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">No cheques recorded.</td></tr>
            @endif
        </tbody>
    </table>
</div>

<!-- Add Cheque Modal -->
<div class="modal-backdrop" id="createChequeModal">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Record New Cheque</h3>
            <button type="button" class="btn-close" onclick="closeModal('createChequeModal')">&times;</button>
        </div>
        <form action="/cheques" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Cheque Number *</label>
                        <input type="text" name="cheque_number" class="form-control" placeholder="e.g. CHQ-990182" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Cheque Date *</label>
                        <input type="date" name="cheque_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label">Bank Name *</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. Commercial Bank / Sampath Bank" required>
                </div>

                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Amount *</label>
                        <x-amount-input name="amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <x-currency-selector name="currency" selected="LKR" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createChequeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Cheque</button>
            </div>
        </form>
    </div>
</div>
@endsection
