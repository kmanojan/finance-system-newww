@extends('layouts.app')
@section('title', 'Ledger Transactions')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Ledger</h2>
    <nav class="nav-links">
        <a href="/transactions" class="nav-link {{ request()->is('transactions') ? 'active' : '' }}">
            <ion-icon name="list-outline"></ion-icon> All Transactions
        </a>
        <a href="/journal-entries" class="nav-link {{ request()->is('journal-entries') ? 'active' : '' }}">
            <ion-icon name="book-outline"></ion-icon> Journal Entries
        </a>
        <a href="/budgets" class="nav-link {{ request()->is('budgets') ? 'active' : '' }}">
            <ion-icon name="calculator-outline"></ion-icon> Budgets
        </a>
    </nav>
</aside>
@endsection

@section('content')
<style>
.tx-filter-tab {
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--text-muted);
    background: var(--bg-page);
    border: 1px solid var(--border);
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.tx-filter-tab:hover, .tx-filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.tx-filter-tab .tab-badge {
    background: rgba(0,0,0,0.12);
    border-radius: 10px;
    padding: 0.1rem 0.45rem;
    font-size: 0.72rem;
}

.tx-filter-tab.active .tab-badge {
    background: rgba(255,255,255,0.25);
    color: white;
}

.tx-table tr:hover {
    background: var(--bg-table-hover);
}

/* Mobile Responsive Table Styles */
@media (max-width: 768px) {
    .tx-table, .tx-table thead, .tx-table tbody, .tx-table th, .tx-table td, .tx-table tr {
        display: block !important;
    }

    .tx-table thead {
        display: none !important;
    }

    .tx-table tbody tr {
        background: var(--bg-card) !important;
        border: 1px solid var(--border) !important;
        border-radius: 12px !important;
        margin-bottom: 0.85rem !important;
        padding: 0.75rem 1rem !important;
        box-shadow: var(--shadow-sm) !important;
    }

    .tx-table tbody td {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 0.5rem 0 !important;
        border: none !important;
        border-bottom: 1px solid var(--border-light) !important;
        text-align: right !important;
        font-size: 0.85rem !important;
        min-height: 36px !important;
    }

    .tx-table tbody td:last-child {
        border-bottom: none !important;
        padding-top: 0.65rem !important;
        padding-bottom: 0 !important;
    }

    .tx-table tbody td::before {
        content: attr(data-label) !important;
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        color: var(--text-muted) !important;
        padding-right: 0.75rem !important;
        text-align: left !important;
        display: inline-block !important;
        flex-shrink: 0 !important;
    }
}
</style>

<!-- Page Header -->
<header class="page-header" style="margin-bottom: 1.25rem;">
    <div class="header-titles">
        <h1 style="font-size:1.65rem; font-weight:800; color:var(--text-heading); margin:0;">Ledger Transactions</h1>
        <p class="subtitle" style="margin-top:0.25rem; font-size:0.85rem; color:var(--text-muted);">
            Real-time double-entry record of all revenue inflows, operational expenses, and ledger movements.
        </p>
    </div>
    <button class="btn btn-primary-gradient btn-pill" onclick="openModal('createTxModal')">
        <ion-icon name="add-outline" style="vertical-align:middle; font-size:1.1rem;"></ion-icon> Record Transaction
    </button>
</header>

<!-- 3 KPI Stat Metric Cards -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem; margin-bottom:1.25rem;">
    <!-- Card 1: Total Inflow (Income) -->
    <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border); padding:1.1rem 1.25rem; border-radius:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
            <span>Total Inflow (Income)</span>
            <ion-icon name="arrow-up-circle-outline" style="font-size:1.35rem; color:var(--success);"></ion-icon>
        </div>
        <div style="font-size:1.45rem; font-weight:800; color:var(--success); margin-top:0.35rem;">
            + LKR {{ number_format($totalIncome, 2) }}
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">
            {{ $incomeCount }} incoming revenue & disbursement entries
        </div>
    </div>

    <!-- Card 2: Total Outflow (Expense) -->
    <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border); padding:1.1rem 1.25rem; border-radius:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
            <span>Total Outflow (Expense)</span>
            <ion-icon name="arrow-down-circle-outline" style="font-size:1.35rem; color:var(--danger);"></ion-icon>
        </div>
        <div style="font-size:1.45rem; font-weight:800; color:var(--danger); margin-top:0.35rem;">
            - LKR {{ number_format($totalExpense, 2) }}
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">
            {{ $expenseCount }} operational expenses & settlements
        </div>
    </div>

    <!-- Card 3: Net Cash Flow / Balance -->
    <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border); padding:1.1rem 1.25rem; border-radius:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
            <span>Net Cash Movement</span>
            <ion-icon name="wallet-outline" style="font-size:1.35rem; color:var(--primary);"></ion-icon>
        </div>
        <div style="font-size:1.45rem; font-weight:800; color: {{ $netCashFlow >= 0 ? 'var(--text-heading)' : 'var(--danger)' }}; margin-top:0.35rem;">
            {{ $netCashFlow >= 0 ? '+' : '' }}LKR {{ number_format($netCashFlow, 2) }}
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">
            Net balance across {{ $totalCount }} transactions
        </div>
    </div>
</div>

<!-- Advanced Multi-Criteria Filter Bar -->
<div class="card" style="padding:1rem 1.25rem; margin-bottom:1.25rem; border:1px solid var(--border); border-radius:10px; background:var(--bg-card);">
    @php 
        $currType = request('type', 'all'); 
        $queryParams = request()->except('type');
    @endphp

    <!-- Top: Type Filter Tabs -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem; padding-bottom:0.85rem; border-bottom:1px solid var(--border-light);">
        <div style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap;">
            <a href="/transactions?{{ http_build_query(array_merge($queryParams, ['type' => 'all'])) }}" class="tx-filter-tab {{ $currType === 'all' ? 'active' : '' }}">
                All Movements <span class="tab-badge">{{ $totalCount }}</span>
            </a>
            <a href="/transactions?{{ http_build_query(array_merge($queryParams, ['type' => 'income'])) }}" class="tx-filter-tab {{ $currType === 'income' ? 'active' : '' }}">
                <ion-icon name="arrow-up-outline"></ion-icon> Income <span class="tab-badge">{{ $incomeCount }}</span>
            </a>
            <a href="/transactions?{{ http_build_query(array_merge($queryParams, ['type' => 'expense'])) }}" class="tx-filter-tab {{ $currType === 'expense' ? 'active' : '' }}">
                <ion-icon name="arrow-down-outline"></ion-icon> Expense <span class="tab-badge">{{ $expenseCount }}</span>
            </a>
        </div>

        @if(request('category_id') || request('payment_method') || request('department_id') || request('start_date') || request('end_date') || request('search') || (request('type') && request('type') !== 'all'))
            <a href="/transactions" class="btn btn-outline" style="color:var(--danger); border-color:var(--border); padding:0.35rem 0.75rem; font-size:0.8rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem; border-radius:6px;">
                <ion-icon name="close-circle-outline"></ion-icon> Clear Filters
            </a>
        @endif
    </div>

    <!-- Bottom: Filter Form Controls -->
    <form method="GET" action="/transactions" style="display:flex; gap:0.85rem; align-items:flex-end; flex-wrap:wrap; margin:0;">
        <input type="hidden" name="type" value="{{ $currType }}">

        <!-- Category Dropdown -->
        <div style="flex:1; min-width:160px; max-width:220px;">
            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">Category</label>
            <select name="category_id" class="form-control" style="font-size:0.82rem; padding:0.4rem 0.65rem; height:40px; border-radius:8px;">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }} ({{ ucfirst($cat->type) }})
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Payment Mode -->
        <div style="flex:1; min-width:140px; max-width:180px;">
            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">Payment Mode</label>
            <select name="payment_method" class="form-control" style="font-size:0.82rem; padding:0.4rem 0.65rem; height:40px; border-radius:8px;">
                <option value="">All Modes</option>
                <option value="Normal" {{ request('payment_method') == 'Normal' ? 'selected' : '' }}>Normal</option>
                <option value="Petty Cash" {{ request('payment_method') == 'Petty Cash' ? 'selected' : '' }}>Petty Cash</option>
                <option value="Credit Card" {{ request('payment_method') == 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                <option value="Bank Transfer" {{ request('payment_method') == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
            </select>
        </div>

        <!-- Date Range -->
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">From Date</label>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date', request('from')) }}" style="font-size:0.82rem; padding:0.4rem 0.65rem; height:40px; border-radius:8px;">
            </div>
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">To Date</label>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date', request('to')) }}" style="font-size:0.82rem; padding:0.4rem 0.65rem; height:40px; border-radius:8px;">
            </div>
        </div>

        <!-- Search Keyword -->
        <div style="flex:1; min-width:180px;">
            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Search description or reference..." value="{{ request('search') }}" style="font-size:0.82rem; padding:0.4rem 0.75rem; height:40px; border-radius:8px;">
        </div>

        <!-- Submit Button -->
        <div>
            <button class="btn btn-primary-gradient" type="submit" style="padding:0.4rem 1.1rem; font-size:0.85rem; height:40px; border-radius:8px; display:inline-flex; align-items:center; gap:0.35rem;">
                <ion-icon name="funnel-outline"></ion-icon> Filter
            </button>
        </div>
    </form>
</div>

<!-- Transactions Data Table -->
@if($transactions->isEmpty())
    <x-empty-state 
        icon="cash-outline" 
        title="No Transactions Found" 
        description="Record income receipts, expense disbursements, and repayments in your double-entry ledger." 
        actionModal="createTxModal" 
        actionText="Record Transaction" 
    />
@else
<div class="card" style="padding:0; overflow:visible; background:var(--bg-card); border-radius:10px; border:1px solid var(--border);">
    <table class="data-table tx-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1.15rem; text-align:left; font-size:0.78rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Date & Ref</th>
                <th style="padding:0.85rem 1.15rem; text-align:left; font-size:0.78rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Description & Category</th>
                <th style="padding:0.85rem 1.15rem; text-align:center; font-size:0.78rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Type</th>
                <th style="padding:0.85rem 1.15rem; text-align:center; font-size:0.78rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Payment Mode</th>
                <th style="padding:0.85rem 1.15rem; text-align:right; font-size:0.78rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Amount</th>
                <th style="padding:0.85rem 1.15rem; text-align:center; font-size:0.78rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
            <tr style="border-bottom: 1px solid var(--border-light);">
                <!-- Date & Ref -->
                <td data-label="Date & Ref" style="padding:0.85rem 1.15rem; text-align:left; vertical-align:middle;">
                    <div style="font-weight:700; color:var(--text-heading); font-size:0.88rem;">
                        {{ date('M d, Y', strtotime($tx->transaction_date)) }}
                    </div>
                    @if(!empty($tx->reference_no))
                        <div style="font-size:0.74rem; font-family:monospace; color:var(--primary); font-weight:600; margin-top:0.15rem;">
                            {{ $tx->reference_no }}
                        </div>
                    @endif
                </td>

                <!-- Description & Category & Department -->
                <td data-label="Description" style="padding:0.85rem 1.15rem; text-align:left; vertical-align:middle;">
                    <div style="font-weight:600; color:var(--text-heading); font-size:0.9rem;">
                        {{ $tx->description }}
                    </div>
                    <div style="display:flex; align-items:center; gap:0.4rem; margin-top:0.25rem; flex-wrap:wrap;">
                        @if(!empty($tx->category_name))
                            <span class="badge" style="background:var(--primary-light); color:var(--primary); font-size:0.72rem; padding:0.15rem 0.5rem; border-radius:4px; font-weight:600;">
                                {{ $tx->category_name }}
                            </span>
                        @endif
                        @if(!empty($tx->department_name))
                            <span class="badge badge-neutral" style="font-size:0.72rem; padding:0.15rem 0.45rem; border-radius:4px;">
                                {{ $tx->department_name }}
                            </span>
                        @endif
                    </div>
                </td>

                <!-- Type Badge -->
                <td data-label="Type" style="padding:0.85rem 1.15rem; text-align:center; vertical-align:middle;">
                    @if($tx->type === 'income')
                        <span class="badge badge-success" style="font-size:0.75rem; padding:0.25rem 0.6rem; font-weight:700; display:inline-flex; align-items:center; gap:0.25rem;">
                            <ion-icon name="arrow-up-outline"></ion-icon> Income
                        </span>
                    @else
                        <span class="badge badge-danger" style="font-size:0.75rem; padding:0.25rem 0.6rem; font-weight:700; display:inline-flex; align-items:center; gap:0.25rem;">
                            <ion-icon name="arrow-down-outline"></ion-icon> Expense
                        </span>
                    @endif
                </td>

                <!-- Mode -->
                <td data-label="Payment Mode" style="padding:0.85rem 1.15rem; text-align:center; vertical-align:middle;">
                    <span class="badge badge-neutral" style="font-size:0.75rem; padding:0.25rem 0.55rem; font-weight:600;">
                        {{ $tx->payment_method ?? 'Normal' }}
                    </span>
                </td>

                <!-- Amount -->
                <td data-label="Amount" style="padding:0.85rem 1.15rem; text-align:right; vertical-align:middle;">
                    <div style="font-size:0.95rem; font-weight:800; color: {{ $tx->type === 'income' ? 'var(--success)' : 'var(--danger)' }}; font-variant-numeric: tabular-nums;">
                        {{ $tx->type === 'income' ? '+' : '-' }} {{ $tx->currency }} {{ number_format($tx->amount, 2) }}
                    </div>
                </td>

                <!-- Actions -->
                <td data-label="Actions" style="padding:0.85rem 1.15rem; text-align:center; vertical-align:middle;">
                    <div style="display:flex; justify-content:center; align-items:center; gap:0.4rem;">
                        <button type="button" class="action-btn" title="Edit Transaction" onclick='openEditTxModal(@json($tx))' style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.3rem 0.55rem; cursor:pointer; color:var(--primary);">
                            <ion-icon name="create-outline" style="font-size:0.95rem;"></ion-icon>
                        </button>
                        <form id="delete_tx_{{ $tx->id }}" action="/transactions/{{ $tx->id }}" method="POST" style="display:inline; margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="action-btn action-danger" title="Delete Transaction" onclick="return confirmAction({title:'Delete Transaction?', message:'Delete transaction for {{ addslashes($tx->description) }} ({{ $tx->currency }} {{ number_format($tx->amount, 2) }})?', confirmText:'Delete', formId:'delete_tx_{{ $tx->id }}'})" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.3rem 0.55rem; cursor:pointer; color:var(--danger);">
                                <ion-icon name="trash-outline" style="font-size:0.95rem;"></ion-icon>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($transactions->hasPages())
<div style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="font-size:0.85rem; color:var(--text-muted);">
        Showing <strong style="color:var(--text-heading);">{{ $transactions->firstItem() ?? 0 }}</strong> to <strong style="color:var(--text-heading);">{{ $transactions->lastItem() ?? 0 }}</strong> of <strong style="color:var(--text-heading);">{{ $transactions->total() }}</strong> transactions
    </div>
    <div>
        {{ $transactions->links() }}
    </div>
</div>
@endif
@endif
@endsection

@section('modals')
<!-- Create Transaction Modal -->
<div class="modal-backdrop" id="createTxModal">
    <div class="modal-card" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title">Record New Transaction</h3>
            <button class="btn-close" onclick="closeModal('createTxModal')">&times;</button>
        </div>
        <form action="/transactions" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Department</label>
                        <x-department-selector name="department_id" :departments="$departments" />
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Transaction Date *</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label" style="font-weight:700;">Description *</label>
                    <input type="text" name="description" class="form-control" placeholder="E.g. Office Supplies / Client Payment / Utility Bill" required>
                </div>
                
                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Category *</label>
                        <select name="category_id" class="form-control" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ ucfirst($cat->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Type *</label>
                        <select name="type" class="form-control" required>
                            <option value="income">Income (Inflow)</option>
                            <option value="expense" selected>Expense (Outflow)</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Payment Mode</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="Normal">Normal</option>
                            <option value="Petty Cash">Petty Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Amount *</label>
                        <x-amount-input name="amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Reference Number (Optional)</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="E.g. INV-1002 / REC-401">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Link to Budget Item (Optional)</label>
                        <x-budget-item-selector name="budget_item_id" :budgetItems="$budgetItems" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Tags (Optional)</label>
                        <x-tag-selector name="tag_ids" :tags="$tags" :multiple="true" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createTxModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal-backdrop" id="editTxModal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Transaction</h3>
            <button class="btn-close" onclick="closeModal('editTxModal')">&times;</button>
        </div>
        <form id="editTxForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Department</label>
                        <select name="department_id" id="edit_department_id" class="form-control">
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Transaction Date *</label>
                        <input type="date" name="transaction_date" id="edit_transaction_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label" style="font-weight:700;">Description *</label>
                    <input type="text" name="description" id="edit_description" class="form-control" required>
                </div>
                
                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Category *</label>
                        <select name="category_id" id="edit_category_id" class="form-control" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ ucfirst($cat->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Type *</label>
                        <select name="type" id="edit_type" class="form-control" required>
                            <option value="income">Income (Inflow)</option>
                            <option value="expense">Expense (Outflow)</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Payment Mode</label>
                        <select name="payment_method" id="edit_payment_method" class="form-control" required>
                            <option value="Normal">Normal</option>
                            <option value="Petty Cash">Petty Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Amount *</label>
                        <x-amount-input name="amount" id="edit_amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_no" id="edit_reference_no" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editTxModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditTxModal(tx) {
    document.getElementById('editTxForm').action = '/transactions/' + tx.id;
    document.getElementById('edit_description').value = tx.description || '';
    document.getElementById('edit_transaction_date').value = tx.transaction_date || '';
    document.getElementById('edit_category_id').value = tx.category_id || '';
    document.getElementById('edit_type').value = tx.type || 'expense';
    document.getElementById('edit_payment_method').value = tx.payment_method || 'Normal';
    document.getElementById('edit_reference_no').value = tx.reference_no || '';
    
    const deptEl = document.getElementById('edit_department_id');
    if (deptEl && tx.department_id) {
        deptEl.value = tx.department_id;
    }
    
    setAmountInputValue('edit_amount', tx.amount);
    openModal('editTxModal');
}
</script>
@endsection

