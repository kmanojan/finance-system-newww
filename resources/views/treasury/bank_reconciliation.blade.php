@extends('layouts.app')
@section('title', 'Treasury & Bank Reconciliation')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Treasury</h2>
    <nav class="nav-links">
        <a href="/treasury/bank-reconciliation" class="nav-link active">Bank Reconciliation (BRS)</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 2rem;">
    <div class="header-titles">
        <h1>Bank Reconciliation & Treasury</h1>
        <p class="subtitle">Import bank statements and auto-match cleared entries against General Ledger cash transactions.</p>
    </div>
</header>

<div class="card" style="padding:1.5rem; margin-bottom:2rem;">
    <form action="/treasury/bank-reconciliation/auto-match" method="POST" style="display:flex; align-items:flex-end; gap:1rem; flex-wrap:wrap;">
        @csrf
        <div style="flex:1; min-width:250px;">
            <label class="form-label">Select Bank Account to Auto-Match *</label>
            <select name="bank_account_id" class="form-control" required>
                @foreach($bankAccounts as $acc)
                    <option value="{{ $acc->id }}" {{ request('bank_account_id') == $acc->id ? 'selected' : '' }}>
                        {{ $acc->bank_name }} - {{ $acc->account_no }} ({{ $acc->currency }})
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary-gradient">
            <ion-icon name="sync-outline"></ion-icon> Run Auto-Match Engine
        </button>
    </form>
</div>

<div class="card" style="padding:0; overflow-x:auto;">
    <table class="data-table" style="width:100%; margin:0;">
        <thead>
            <tr>
                <th>Statement Date</th>
                <th>Bank Account</th>
                <th>Description</th>
                <th>Reference #</th>
                <th>Amount</th>
                <th>Match Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statements as $stmt)
            <tr>
                <td>{{ $stmt->statement_date ? $stmt->statement_date->format('Y-m-d') : '-' }}</td>
                <td>{{ $stmt->bankAccount->bank_name ?? '-' }}</td>
                <td>{{ $stmt->description }}</td>
                <td><span class="font-medium">{{ $stmt->reference_no ?? '-' }}</span></td>
                <td style="font-weight:600;">LKR {{ number_format($stmt->amount, 2) }}</td>
                <td>
                    @if($stmt->is_matched)
                        <span class="badge" style="background:#dcfce7; color:#166534;">Matched</span>
                    @else
                        <span class="badge badge-draft">Unmatched</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @if($statements->isEmpty())
            <tr>
                <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No imported bank statements found.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
