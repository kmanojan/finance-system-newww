@extends('layouts.app')
@section('title', 'Budgets')

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
        <h1>Budgets</h1>
        <p class="subtitle">Manage allocations and monitor spending.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openModal('createBudgetModal')">
        <ion-icon name="add-outline"></ion-icon> New Budget
    </button>
</header>

<div class="toolbar">
    <div class="toolbar-left">
    </div>
    <div class="toolbar-right">
        <div class="search-input">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" placeholder="Search budgets">
        </div>
    </div>
</div>

@if($budgets->isEmpty())
    <x-empty-state 
        icon="pie-chart-outline" 
        title="No Budgets Defined" 
        description="Set up department and category budgets to monitor and limit expenditures." 
        actionModal="createBudgetModal" 
        actionText="Create Budget" 
    />
@else
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Budget (Period)</th>
                <th class="text-right">Allocated</th>
                <th class="text-right">Actual Spent</th>
                <th>% Used</th>
                <th class="text-right">Remaining</th>
                <th class="text-center">Status</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($budgets as $budget)
            <tr>
                <td data-label="Budget">
                    <a href="/budgets/{{ $budget->id }}" class="font-medium" style="font-size:1rem; color:var(--text-heading); text-decoration:none;">{{ $budget->name }}</a><br>
                    <small class="text-muted">{{ ucfirst($budget->period) }} | {{ $budget->start_date }} to {{ $budget->end_date }}</small>
                </td>
                <td data-label="Allocated" class="amount-cell"><x-amount-display :amount="$budget->allocated_amount" :currency="$budget->currency" /></td>
                <td data-label="Actual Spent" class="amount-cell"><x-amount-display :amount="$budget->actual_spent" :currency="$budget->currency" /></td>
                <td data-label="Percent Used">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="flex-grow: 1; background: var(--bg-page); border-radius: 4px; height: 8px; overflow: hidden; border:1px solid var(--border-light);">
                            <div style="background: {{ $budget->status_text_class }}; width: {{ min($budget->percent_used, 100) }}%; height: 100%;"></div>
                        </div>
                        <span class="text-muted tabular-nums" style="font-size: 0.85rem;">{{ $budget->percent_used }}%</span>
                    </div>
                </td>
                <td data-label="Remaining" class="amount-cell">
                    <x-amount-display :amount="$budget->remaining" :currency="$budget->currency" class="font-medium" style="color:{{ $budget->remaining >= 0 ? 'var(--success)' : 'var(--danger)' }};" />
                </td>
                <td data-label="Status" class="text-center">
                    <span class="badge" style="background:{{ $budget->status_class }};color:{{ $budget->status_text_class }};">{{ $budget->status_label }}</span>
                </td>
                <td data-label="Action" class="text-center">
                    <div class="actions" style="justify-content:center;">
                        <a href="/budgets/{{ $budget->id }}" class="action-btn" title="View Details"><ion-icon name="eye-outline"></ion-icon></a>
                        <button type="button" class="action-btn" title="Clone Budget" onclick="cloneBudget({{ $budget->id }})"><ion-icon name="copy-outline"></ion-icon></button>
                        <form id="delete_budget_{{ $budget->id }}" action="/budgets/{{ $budget->id }}" method="POST" style="display:inline; margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="action-btn action-danger" title="Delete Budget" onclick="return confirmAction({title:'Delete Budget?', message:'Delete budget {{ addslashes($budget->name) }}?', confirmText:'Delete Budget', formId:'delete_budget_{{ $budget->id }}'})">
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
<div class="modal-backdrop" id="createBudgetModal">
    <div class="modal-card" style="width: calc(100% - 100px); max-width: none; height: calc(100vh - 100px); display: flex; flex-direction: column; margin: 50px auto;">
        <div class="modal-header">
            <h3 class="modal-title">New Budget</h3>
            <button class="btn-close" onclick="closeModal('createBudgetModal')">&times;</button>
        </div>
        <form action="/budgets" method="POST" style="display: flex; flex-direction: column; height: 100%; margin: 0;">
            @csrf
            <div class="modal-body" style="flex-grow: 1; overflow-y: auto;">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Budget Name (Period)</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. 2026/04">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <x-currency-selector name="currency" :selected="$baseCurrency ?? 'LKR'" required />
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Period</label>
                        <select name="period" class="form-control" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                            <option value="one-time">One-time</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Dates (Start - End)</label>
                        <div style="display:flex;gap:0.5rem;">
                            <input type="date" name="start_date" class="form-control" required>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-light); padding-top: 1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h4 class="section-title" style="margin:0;">Budget Allocations</h4>
                        <button type="button" class="btn btn-sm btn-outline" onclick="addGroupRow()">+ Add Group</button>
                    </div>
                    <div id="budgetGroupsContainer">
                        <!-- Group and Item inputs will be added dynamically here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createBudgetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Budget</button>
            </div>
        </form>
    </div>
</div>
<script>
let groupCount = 0;

function addGroupRow(groupName = '', items = []) {
    const container = document.getElementById('budgetGroupsContainer');
    const groupIndex = groupCount++;
    
    const groupDiv = document.createElement('div');
    groupDiv.className = 'card';
    groupDiv.style.padding = '1rem';
    groupDiv.style.marginBottom = '1rem';
    groupDiv.style.border = '1px solid #cbd5e1';
    groupDiv.style.boxShadow = 'none';
    groupDiv.id = `group_card_${groupIndex}`;
    
    groupDiv.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <div style="display:flex; gap:0.5rem; align-items:center; flex-grow:1; margin-right:1rem;">
                <label class="form-label" style="margin:0; min-width:80px;">Group Name</label>
                <input type="text" name="groups[${groupIndex}][name]" class="form-control" placeholder="e.g. server, hr" value="${groupName}" required style="max-width:250px;">
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn btn-sm btn-outline" onclick="addItemRow(${groupIndex})">+ Add Item</button>
                <button type="button" class="btn btn-sm btn-outline" style="color:var(--danger); border-color:var(--danger);" onclick="removeGroup(${groupIndex})">&times;</button>
            </div>
        </div>
        <div id="group_items_container_${groupIndex}" style="padding-left:1.5rem; border-left:2px dashed #cbd5e1; display:flex; flex-direction:column; gap:0.5rem;">
            <!-- Items added here -->
        </div>
    `;
    
    container.appendChild(groupDiv);
    
    if (items && items.length > 0) {
        items.forEach(item => {
            addItemRow(groupIndex, item.name, item.allocated_amount);
        });
    } else {
        addItemRow(groupIndex);
    }
}

let itemCounts = {};

function addItemRow(groupIndex, itemName = '', itemAmount = '') {
    if (!itemCounts[groupIndex]) {
        itemCounts[groupIndex] = 0;
    }
    const itemIndex = itemCounts[groupIndex]++;
    const container = document.getElementById(`group_items_container_${groupIndex}`);
    
    const itemDiv = document.createElement('div');
    itemDiv.style.display = 'flex';
    itemDiv.style.gap = '0.5rem';
    itemDiv.style.alignItems = 'center';
    itemDiv.id = `group_${groupIndex}_item_${itemIndex}`;
    
    itemDiv.innerHTML = `
        <input type="text" name="groups[${groupIndex}][items][${itemIndex}][name]" class="form-control" placeholder="Item Name (e.g. digital ocean)" value="${itemName}" required style="flex-grow:2;">
        <div class="amount-input-wrapper" style="position: relative; width:120px;">
            <input type="text" class="form-control amount-display-input" placeholder="0.00" value="${itemAmount ? parseFloat(itemAmount).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : ''}" required oninput="formatAmountInput(this)" onblur="formatAmountBlur(this)">
            <input type="hidden" name="groups[${groupIndex}][items][${itemIndex}][allocated_amount]" class="amount-hidden" value="${itemAmount}">
        </div>
        <button type="button" class="btn btn-sm btn-outline" style="color:var(--danger); border-color:var(--danger); padding:0.4rem 0.6rem;" onclick="removeItem(${groupIndex}, ${itemIndex})">&times;</button>
    `;
    
    container.appendChild(itemDiv);
}

function removeGroup(groupIndex) {
    document.getElementById(`group_card_${groupIndex}`).remove();
}

function removeItem(groupIndex, itemIndex) {
    document.getElementById(`group_${groupIndex}_item_${itemIndex}`).remove();
}

function cloneBudget(id) {
    fetch('/budgets/' + id + '/json')
        .then(res => res.json())
        .then(data => {
            // Populate basic fields
            const form = document.querySelector('#createBudgetModal form');
            form.querySelector('input[name="name"]').value = data.name + ' (Copy)';
            form.querySelector('select[name="currency"]').value = data.currency;
            form.querySelector('select[name="period"]').value = data.period;
            form.querySelector('input[name="start_date"]').value = data.start_date;
            form.querySelector('input[name="end_date"]').value = data.end_date;
            
            // Clear current inputs
            document.getElementById('budgetGroupsContainer').innerHTML = '';
            groupCount = 0;
            itemCounts = {};
            
            // Rebuild groups and items
            if (data.groups && data.groups.length > 0) {
                data.groups.forEach(group => {
                    addGroupRow(group.name, group.items);
                });
            } else {
                addGroupRow();
            }
            
            openModal('createBudgetModal');
        })
        .catch(err => alert('Failed to clone budget: ' + err));
}

document.addEventListener('DOMContentLoaded', () => {
    // Only add a default row if creating a new one, not when populating clone
    const container = document.getElementById('budgetGroupsContainer');
    if (container && container.children.length === 0) {
        addGroupRow();
    }
});

function formatAmountInput(input) {
    let val = input.value.replace(/[^0-9.]/g, '');
    const parts = val.split('.');
    if (parts.length > 2) {
        parts.pop();
        val = parts.join('.');
    }
    if (parts.length === 2 && parts[1].length > 2) {
        parts[1] = parts[1].substring(0, 2);
        val = parts.join('.');
    }
    const hiddenInput = input.parentElement.querySelector('.amount-hidden');
    hiddenInput.value = val;
    if (parts[0].length > 0) {
        parts[0] = parseInt(parts[0], 10).toLocaleString('en-US');
        input.value = parts.join('.');
    } else {
        input.value = val;
    }
}

function formatAmountBlur(input) {
    let val = input.parentElement.querySelector('.amount-hidden').value;
    if (val && !isNaN(val)) {
        input.value = parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        input.parentElement.querySelector('.amount-hidden').value = parseFloat(val).toFixed(2);
    } else {
        input.value = '';
        input.parentElement.querySelector('.amount-hidden').value = '';
    }
}
</script>
@endsection
