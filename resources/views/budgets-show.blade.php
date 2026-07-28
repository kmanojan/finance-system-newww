@extends('layouts.app')
@section('title', 'Budget Details')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Ledger</h2>
    <nav class="nav-links">
        <a href="/transactions" class="nav-link {{ request()->is('transactions') ? 'active' : '' }}">All Transactions</a>
        <a href="/journal-entries" class="nav-link {{ request()->is('journal-entries') ? 'active' : '' }}">Journal Entries</a>
        <a href="/budgets" class="nav-link active">Budgets</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 2rem;">
    <div class="header-titles">
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
            <a href="/budgets" style="color:var(--slate-500); text-decoration:none;"><ion-icon name="arrow-back-outline"></ion-icon> Back to Budgets</a>
        </div>
        <h1>Budget: {{ $budget->name }}</h1>
        <p class="subtitle">Period: {{ ucfirst($budget->period) }} ({{ $budget->start_date }} to {{ $budget->end_date }})</p>
    </div>
    
    <div class="header-actions">
        <span class="badge" style="background:{{ $budget->status_class }};color:{{ $budget->status_text_class }};font-size:1rem;padding:0.6em 1.2em;border-radius:8px;margin-right:1rem;">{{ $budget->status_label }}</span>
        <button class="btn btn-primary-gradient btn-pill" onclick="openQuickExpenseModal(null, '', {{ $budget->allocated_amount }}, {{ $budget->actual_spent }}, '{{ $budget->currency }}')">
            <ion-icon name="add-outline"></ion-icon> Log Transaction
        </button>
    </div>
</header>

<div class="summary-cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:2.5rem;">
    <div class="card" style="padding:1.5rem; border-left:4px solid var(--primary);">
        <div style="color:var(--slate-500); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Total Allocated</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--slate-800); margin-top:0.5rem;">{{ $budget->currency }} {{ number_format($budget->allocated_amount, 2) }}</div>
    </div>
    <div class="card" style="padding:1.5rem; border-left:4px solid {{ $budget->status_text_class }};">
        <div style="color:var(--slate-500); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Actual Spent</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--slate-800); margin-top:0.5rem;">{{ $budget->currency }} {{ number_format($budget->actual_spent, 2) }}</div>
    </div>
    <div class="card" style="padding:1.5rem; border-left:4px solid {{ $budget->remaining >= 0 ? 'var(--success)' : 'var(--danger)' }};">
        <div style="color:var(--slate-500); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Remaining</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--slate-800); margin-top:0.5rem;">{{ $budget->currency }} {{ number_format($budget->remaining, 2) }}</div>
    </div>
    <div class="card" style="padding:1.5rem; display:flex; flex-direction:column; justify-content:center;">
        <div style="color:var(--slate-500); font-size:0.85rem; font-weight:600; text-transform:uppercase; margin-bottom:0.5rem;">Total Usage</div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="flex-grow: 1; background: var(--bg-sidebar-primary); border-radius: 4px; height: 10px; overflow: hidden; border: 1px solid var(--border);">
                <div style="background: {{ $budget->status_class }}; width: {{ min($budget->percent_used, 100) }}%; height: 100%;"></div>
            </div>
            <span class="font-medium" style="font-size: 1rem; color:var(--text-heading);">{{ $budget->percent_used }}%</span>
        </div>
    </div>
</div>

<div class="card" style="padding:1.5rem; margin-bottom: 2rem;">
    <h2 class="section-title" style="margin-top:0; margin-bottom:1.5rem;"><ion-icon name="folder-open-outline"></ion-icon> Budget Allocation Tree</h2>
    
    @foreach($groups as $group)
        <div style="margin-bottom: 2rem; border:1px solid var(--border); border-radius:12px; overflow:hidden; background: var(--bg-card);">
            <div style="background:var(--bg-sidebar-secondary); padding:1rem; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border);">
                <div>
                    <span style="font-size:1.15rem; font-weight:700; color:var(--text-heading);">Group: {{ $group->name }}</span>
                </div>
                <div style="display:flex; gap:1.5rem; font-size:0.9rem; align-items:center;">
                    <div>Allocated: <strong style="color:var(--text-heading);">{{ $budget->currency }} {{ number_format($group->allocated_amount, 2) }}</strong></div>
                    <div>Spent: <strong style="color:var(--primary);">{{ $budget->currency }} {{ number_format($group->actual_spent, 2) }}</strong></div>
                    <div>Remaining: <strong style="color:{{ $group->remaining >= 0 ? 'var(--success)' : 'var(--danger)' }}">{{ $budget->currency }} {{ number_format($group->remaining, 2) }}</strong></div>
                    @if($group->name !== 'Unspecified')
                        <button class="btn btn-sm btn-outline" style="padding:0.2rem 0.6rem; font-size:0.8rem;" onclick="openQuickExpenseModal(null, '', {{ $group->allocated_amount }}, {{ $group->actual_spent }}, '{{ $budget->currency }}')">
                            <ion-icon name="add-outline" style="vertical-align:middle;"></ion-icon> Log Tx
                        </button>
                    @endif
                </div>
            </div>
            <div style="padding:0;">
                <table class="data-table" style="margin:0; width:100%;">
                    <thead>
                        <tr>
                            <th style="padding-left:1.5rem;">Line Item</th>
                            <th>Allocated</th>
                            <th>Spent</th>
                            <th>Usage</th>
                            <th>Remaining</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group->items as $item)
                        <tr>
                            <td data-label="Line Item" style="padding-left:1.5rem;"><span class="font-medium" style="font-size:1rem;">{{ $item->name }}</span></td>
                            <td data-label="Allocated">{{ $budget->currency }} {{ number_format($item->allocated_amount, 2) }}</td>
                            <td data-label="Spent">{{ $budget->currency }} {{ number_format($item->actual_spent, 2) }}</td>
                            <td data-label="Usage">
                                <div style="display: flex; align-items: center; gap: 8px; max-width:200px;">
                                    <div style="flex-grow: 1; background: var(--bg-sidebar-primary); border-radius: 4px; height: 6px; overflow: hidden; border: 1px solid var(--border);">
                                        <div style="background: {{ $item->percent_used >= 100 ? 'var(--danger)' : ($item->percent_used >= 80 ? 'var(--warning)' : 'var(--success)') }}; width: {{ min($item->percent_used, 100) }}%; height: 100%;"></div>
                                    </div>
                                    <span class="text-muted" style="font-size: 0.8rem;">{{ $item->percent_used }}%</span>
                                </div>
                            </td>
                            <td data-label="Remaining">
                                <span class="font-medium" style="color:{{ $item->remaining >= 0 ? 'var(--success)' : 'var(--danger)' }};">
                                    {{ $budget->currency }} {{ number_format($item->remaining, 2) }}
                                </span>
                            </td>
                            <td data-label="Action">
                                <button class="btn btn-sm btn-outline" style="padding:0.2rem 0.6rem; font-size:0.8rem;" onclick="openQuickExpenseModal({{ $item->id }}, {{ json_encode($group->name . ' > ' . $item->name) }}, {{ $item->allocated_amount }}, {{ $item->actual_spent }}, '{{ $budget->currency }}')">
                                    <ion-icon name="add-outline" style="vertical-align:middle;"></ion-icon> Log Tx
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>

<div class="card" style="padding:1.5rem;">
    <h2 class="section-title" style="margin-top:0; margin-bottom:1.5rem;"><ion-icon name="receipt-outline"></ion-icon> Budget Transactions Log</h2>
    <div class="data-table-container">
        <table class="data-table" style="margin:0;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Group &gt; Item</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $tx)
                <tr>
                    <td data-label="Date">{{ $tx->transaction_date }}</td>
                    <td data-label="Group > Item"><span style="color:var(--slate-800); font-weight:600;">{{ $tx->group_name }}</span> &gt; <span class="text-muted">{{ $tx->item_name }}</span></td>
                    <td data-label="Category"><span class="badge" style="background:#f1f5f9; color:#475569;">{{ $tx->category_name ?? 'Uncategorized' }}</span></td>
                    <td data-label="Description"><span class="font-medium">{{ $tx->description }}</span></td>
                    <td data-label="Amount" style="color:{{ $tx->type === 'income' ? 'var(--success)' : 'var(--danger)' }}; font-weight:600;">
                        {{ $tx->type === 'income' ? '+' : '-' }} {{ $tx->currency }} {{ number_format($tx->amount, 2) }}
                    </td>
                    <td data-label="Action" style="text-align:right;">
                        <button class="btn btn-sm btn-outline" style="padding:0.2rem 0.6rem; font-size:0.8rem;" onclick="openEditTransactionModal({{ json_encode($tx) }})">
                            <ion-icon name="create-outline" style="vertical-align:middle;"></ion-icon> Edit
                        </button>
                    </td>
                </tr>
                @endforeach
                @if($transactions->isEmpty())
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No transactions recorded for this budget yet.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('modals')
@php
    $flatBudgetItems = [];
    foreach ($groups as $group) {
        foreach ($group->items as $item) {
            $flatBudgetItems[] = (object)[
                'id' => $item->id,
                'item_name' => $item->name,
                'group_name' => $group->name,
                'budget_name' => $budget->name
            ];
        }
    }
@endphp

<div class="modal-backdrop" id="addExpenseModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Record Transaction</h3>
            <button class="btn-close" onclick="closeModal('addExpenseModal')">&times;</button>
        </div>
        <form action="/budgets/{{ $budget->id }}/transactions" method="POST">
            @csrf
            <div class="modal-body">
                <div id="budget_item_stats_card" style="display:none; background:var(--bg-card); border:1px solid var(--border); padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                        <span class="text-muted" style="font-size:0.85rem; font-weight:600; text-transform:uppercase;">Allocated</span>
                        <strong id="stats_allocated" style="color:var(--text-heading);"></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                        <span class="text-muted" style="font-size:0.85rem; font-weight:600; text-transform:uppercase;">Logged (Spent)</span>
                        <strong id="stats_spent" style="color:var(--primary);"></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:1px solid var(--border); padding-top:0.5rem;">
                        <span class="text-muted" style="font-size:0.85rem; font-weight:600; text-transform:uppercase;">Remaining</span>
                        <strong id="stats_remaining"></strong>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control" required>
                        <option value="expense">Expense (-)</option>
                        <option value="income">Income (+)</option>
                    </select>
                </div>
                
                <div class="form-group" id="budget_item_display_group" style="display:none; background:var(--bg-sidebar-primary); border:1px solid var(--border); padding:0.8rem; border-radius:6px; margin-bottom:1rem;">
                    <span class="text-muted" style="font-size:0.85rem;">Linked Budget Item:</span><br>
                    <strong id="budget_item_display_text" style="color:var(--text-heading);"></strong>
                </div>

                <div class="form-group" id="budget_item_select_group">
                    <label class="form-label">Link to Budget Line Item</label>
                    <x-budget-item-selector name="budget_item_id" id="budgetShowSelector" :budgetItems="$flatBudgetItems" />
                </div>
                
                <div class="form-row" style="margin-top: 1rem;">
                    <div class="form-col">
                        <label class="form-label">Date</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control" required>
                            <optgroup label="Expenses">
                                @foreach($categories->where('type', 'expense') as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Income">
                                @foreach($categories->where('type', 'income') as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Amount ({{ $budget->currency }})</label>
                        <x-amount-input name="amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Department</label>
                        <x-department-selector name="department_id" :departments="$departments" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_method" id="quick_payment_method" class="form-control" required>
                            <option value="Normal">Normal</option>
                            <option value="Petty Cash">Petty Cash</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Bank Account</label>
                        <select name="bank_account_id" class="form-control">
                            <option value="">Petty Cash (No Bank Account)</option>
                            @foreach($bankAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->bank_name }} - {{ $acc->account_no }} ({{ $acc->currency }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Reference No.</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. CHQ-12984, INV-838">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Tags (Optional)</label>
                        <x-tag-selector name="tag_ids" :tags="$tags" :multiple="true" />
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="Describe the expense details..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addExpenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Record Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal-backdrop" id="editTransactionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Transaction</h3>
            <button class="btn-close" onclick="closeModal('editTransactionModal')">&times;</button>
        </div>
        <form id="editTransactionForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Type</label>
                    <select name="type" id="edit_tx_type" class="form-control" required>
                        <option value="expense">Expense (-)</option>
                        <option value="income">Income (+)</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="edit_tx_category" class="form-control" required>
                            <optgroup label="Expenses">
                                @foreach($categories->where('type', 'expense') as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Income">
                                @foreach($categories->where('type', 'income') as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_method" id="edit_tx_payment_method" class="form-control" required>
                            <option value="Normal">Normal</option>
                            <option value="Petty Cash">Petty Cash</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Amount</label>
                        <x-amount-input name="amount" id="edit_tx_amount" required="true" />
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_tx_description" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editTransactionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openQuickExpenseModal(itemId = null, displayText = '', allocated = null, spent = null, currency = '') {
    const displayGroup = document.getElementById('budget_item_display_group');
    const selectGroup = document.getElementById('budget_item_select_group');
    const displayVal = document.getElementById('budget_item_display_text');
    const statsCard = document.getElementById('budget_item_stats_card');
    
    if (allocated !== null && spent !== null) {
        statsCard.style.display = 'block';
        const rem = allocated - spent;
        document.getElementById('stats_allocated').innerText = currency + ' ' + Number(allocated).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('stats_spent').innerText = currency + ' ' + Number(spent).toLocaleString(undefined, {minimumFractionDigits: 2});
        const remEl = document.getElementById('stats_remaining');
        remEl.innerText = currency + ' ' + Number(rem).toLocaleString(undefined, {minimumFractionDigits: 2});
        remEl.style.color = rem >= 0 ? 'var(--success)' : 'var(--danger)';
    } else {
        statsCard.style.display = 'none';
    }
    
    const selectorComponent = document.getElementById('component_budgetShowSelector');
    const selectVal = selectorComponent.querySelector('#hidden_inputs_budgetShowSelector input');
    const displaySpan = selectorComponent.querySelector('#display_budgetShowSelector');
    const triggerBtn = selectorComponent.querySelector('button');

    if (itemId) {
        displayGroup.style.display = 'block';
        selectGroup.style.display = 'none';
        displayVal.innerText = displayText;
        selectVal.value = itemId;
        triggerBtn.style.pointerEvents = 'none';
        triggerBtn.style.background = '#f1f5f9';
    } else {
        displayGroup.style.display = 'none';
        selectGroup.style.display = 'block';
        selectVal.value = '';
        displaySpan.innerText = 'Select Budget Line Item...';
        displaySpan.style.color = '#94a3b8';
        triggerBtn.style.pointerEvents = 'auto';
        triggerBtn.style.background = 'transparent';
    }
    openModal('addExpenseModal');
}

function openEditTransactionModal(tx) {
    document.getElementById('editTransactionForm').action = '/transactions/' + tx.id;
    document.getElementById('edit_tx_type').value = tx.type;
    document.getElementById('edit_tx_category').value = tx.category_id;
    document.getElementById('edit_tx_payment_method').value = tx.payment_method || 'Normal';
    document.getElementById('edit_tx_amount').nextElementSibling.value = tx.amount;
    if (typeof formatAmountBlur === 'function') formatAmountBlur(document.getElementById('edit_tx_amount'));
    document.getElementById('edit_tx_description').value = tx.description;
    
    openModal('editTransactionModal');
}
</script>
@endsection
