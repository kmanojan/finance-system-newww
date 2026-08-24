@extends('layouts.app')
@section('title', 'Ledger Transactions')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Ledger</h2>
    <nav class="nav-links">
        <a href="/transactions" class="nav-link {{ request()->is('transactions') ? 'active' : '' }}">All Transactions</a>
        <a href="/journal-entries" class="nav-link {{ request()->is('journal-entries') ? 'active' : '' }}">Journal Entries</a>
        <a href="/budgets" class="nav-link {{ request()->is('budgets') ? 'active' : '' }}">Budgets</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Transactions</h1>
        <p class="subtitle">Record and view double-entry ledger transactions.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openModal('createTxModal')">
        <ion-icon name="add-outline"></ion-icon> New Transaction
    </button>
</header>

<div class="toolbar">
    <div class="toolbar-left" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <form method="GET" action="/transactions" style="display: flex; gap: 0.5rem; align-items: center; margin: 0; flex-wrap: wrap;">
            <div>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" title="Start Date">
            </div>
            <div>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" title="End Date">
            </div>
            <div>
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <option value="income" {{ request('type') == 'income' ? 'selected' : '' }}>Income</option>
                    <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>Expense</option>
                </select>
            </div>
            <div>
                <select name="payment_method" class="form-control">
                    <option value="">All Modes</option>
                    <option value="Normal" {{ request('payment_method') == 'Normal' ? 'selected' : '' }}>Normal</option>
                    <option value="Petty Cash" {{ request('payment_method') == 'Petty Cash' ? 'selected' : '' }}>Petty Cash</option>
                    <option value="Credit Card" {{ request('payment_method') == 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                </select>
            </div>
            <div style="width: 200px;">
                <x-tag-selector name="tag_id" :tags="$tags" :multiple="false" :selected="request('tag_id')" />
            </div>
            <button class="btn btn-outline" type="submit" style="padding: 0.5rem 1rem;">Filter</button>
            @if(request('tag_id') || request('start_date') || request('end_date') || request('type') || request('payment_method'))
                <a href="/transactions" class="btn btn-outline" style="color: var(--text-muted); padding: 0.5rem 1rem;">Clear</a>
            @endif
        </form>
    </div>
    <div class="toolbar-right">
        <div class="search-input">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" placeholder="Search transactions">
        </div>
    </div>
</div>

@if($transactions->isEmpty())
    <x-empty-state 
        icon="cash-outline" 
        title="No Transactions Found" 
        description="Record income, expense, and transfer transactions in your double-entry ledger." 
        actionModal="createTxModal" 
        actionText="New Transaction" 
    />
@else
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th class="text-right">Amount</th>
                <th class="text-center">Type</th>
                <th class="text-center">Mode</th>
                <th class="text-center">Reconciled</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
            <tr>
                <td data-label="Date"><span class="font-medium tabular-nums">{{ $tx->transaction_date }}</span></td>
                <td data-label="Description"><span class="text-muted">{{ $tx->description }}</span></td>
                <td data-label="Amount" class="amount-cell"><x-amount-display :amount="$tx->amount" currency="{{ $tx->currency }}" class="text-heading font-medium tabular-nums" /></td>
                <td data-label="Type" class="text-center">
                    @if($tx->type === 'income')
                        <span class="badge badge-success">Income</span>
                    @else
                        <span class="badge badge-danger">Expense</span>
                    @endif
                </td>
                <td data-label="Mode" class="text-center">
                    <span class="badge badge-neutral">{{ $tx->payment_method ?? 'Normal' }}</span>
                </td>
                <td data-label="Reconciled" class="text-center">
                    @if($tx->reconciled)
                        <span class="badge badge-info">Yes</span>
                    @else
                        <span class="badge badge-neutral">No</span>
                    @endif
                </td>
                <td data-label="Action" class="text-center">
                    <div class="actions" style="justify-content:center;">
                        <form id="delete_tx_{{ $tx->id }}" action="/transactions/{{ $tx->id }}" method="POST" style="display:inline; margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="action-btn action-danger" title="Delete Transaction" onclick="return confirmAction({title:'Delete Transaction?', message:'Delete transaction for {{ addslashes($tx->description) }}?', confirmText:'Delete', formId:'delete_tx_{{ $tx->id }}'})">
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
@endsection

@section('modals')
<div class="modal-backdrop" id="createTxModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Transaction</h3>
            <button class="btn-close" onclick="closeModal('createTxModal')">&times;</button>
        </div>
        <form action="/transactions" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Department</label>
                        <x-department-selector name="department_id" :departments="$departments" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Date</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" required>
                </div>
                
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="Normal">Normal</option>
                            <option value="Petty Cash">Petty Cash</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Amount</label>
                        <x-amount-input name="amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Reference Number (Optional)</label>
                        <input type="text" name="reference_no" class="form-control">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
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
@endsection
