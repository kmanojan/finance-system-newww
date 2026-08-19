@extends('layouts.app')
@section('title', 'Tax Config & Rates - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Tax Config & Rates</h1>
        <p class="subtitle">Manage statutory tax types, VAT, WHT, CIT rates, and effective date ranges (Sri Lanka baseline).</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Tax Rate
    </button>
</header>

@if(session('error'))
<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
    {{ session('error') }}
</div>
@endif

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
    {{ session('success') }}
</div>
@endif

<div class="toolbar">
    <div class="toolbar-left"></div>
    <div class="toolbar-right">
        <div class="search-input">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" placeholder="Search tax rates...">
        </div>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Tax Name</th>
                <th>Category</th>
                <th>Rate %</th>
                <th>Applies To</th>
                <th>Effective From</th>
                <th>Effective To</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($taxTypes as $item)
            <tr>
                <td data-label="Name">
                    <span class="font-medium">{{ $item->name }}</span>
                    @if($item->is_default)
                        <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:0.75rem; margin-left:0.5rem; padding:0.2em 0.6em; border-radius:4px;">Default</span>
                    @endif
                </td>
                <td data-label="Category">
                    <span class="badge" style="background:#f1f5f9; color:#334155; text-transform:uppercase; font-size:0.75rem; padding:0.3em 0.7em; border-radius:6px;">{{ strtoupper($item->category) }}</span>
                </td>
                <td data-label="Rate"><span class="font-bold text-primary" style="font-size:1.05rem;">{{ number_format($item->rate, 2) }}%</span></td>
                <td data-label="Applies To"><span class="text-muted">{{ ucfirst(str_replace('_', ' ', $item->applies_to)) }}</span></td>
                <td data-label="Effective From"><span class="text-muted">{{ \Carbon\Carbon::parse($item->effective_from)->format('Y-m-d') }}</span></td>
                <td data-label="Effective To"><span class="text-muted">{{ $item->effective_to ? \Carbon\Carbon::parse($item->effective_to)->format('Y-m-d') : 'Indefinite' }}</span></td>
                <td data-label="Status">
                    @if($item->is_active)
                        <span class="badge" style="background:#dcfce7; color:#166534; font-size:0.75rem; padding:0.3em 0.6em; border-radius:6px;">Active</span>
                    @else
                        <span class="badge" style="background:#fee2e2; color:#991b1b; font-size:0.75rem; padding:0.3em 0.6em; border-radius:6px;">Inactive</span>
                    @endif
                </td>
                <td data-label="Action">
                    <div class="actions">
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/tax-types/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Soft-delete this tax type?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($taxTypes->isEmpty())
            <tr><td colspan="8" class="text-center text-muted py-4">No tax types configured yet.</td></tr>
            @endif
        </tbody>
    </table>
</div>

<script>
    function openCreateModal() {
        var createForm = document.querySelector('#createModal form');
        if (createForm) createForm.reset();
        openModal('createModal');
    }

    function openEditModal(item) {
        document.getElementById('editForm').action = '/master/tax-types/' + item.id;
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_category')) document.getElementById('edit_category').value = item.category || 'vat';
        if (document.getElementById('edit_rate')) document.getElementById('edit_rate').value = item.rate || 0;
        if (document.getElementById('edit_applies_to')) document.getElementById('edit_applies_to').value = item.applies_to || 'invoice_item';
        if (document.getElementById('edit_effective_from')) document.getElementById('edit_effective_from').value = item.effective_from ? item.effective_from.split('T')[0] : '';
        if (document.getElementById('edit_effective_to')) document.getElementById('edit_effective_to').value = item.effective_to ? item.effective_to.split('T')[0] : '';
        if (document.getElementById('edit_is_default')) document.getElementById('edit_is_default').checked = !!item.is_default;
        if (document.getElementById('edit_is_active')) document.getElementById('edit_is_active').checked = !!item.is_active;
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Add New Tax Rate</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/tax-types" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Tax Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. VAT — Standard (18%)" required>
                </div>
                <div class="grid grid-2 gap-3">
                    <div class="form-group">
                        <label class="form-label required">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="vat">VAT (Value Added Tax)</option>
                            <option value="wht">WHT (Withholding Tax)</option>
                            <option value="cit">CIT (Corporate Income Tax)</option>
                            <option value="other">Other Statutory Tax</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Rate %</label>
                        <input type="number" step="0.01" min="0" max="100" name="rate" class="form-control" placeholder="18.00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">Applies To</label>
                    <select name="applies_to" class="form-control" required>
                        <option value="invoice_item">Invoice Line Items (VAT)</option>
                        <option value="commission_payment">Commission Payouts (WHT)</option>
                        <option value="loan_interest">Loan Interest Payments (WHT)</option>
                        <option value="other">General / Annual P&L Tax</option>
                    </select>
                </div>
                <div class="grid grid-2 gap-3">
                    <div class="form-group">
                        <label class="form-label required">Effective From</label>
                        <input type="date" name="effective_from" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Effective To (Optional)</label>
                        <input type="date" name="effective_to" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="is_default" value="1"> Make Default for Category & Context
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Tax Type</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Tax Type</h3>
            <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Tax Name</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                <div class="grid grid-2 gap-3">
                    <div class="form-group">
                        <label class="form-label required">Category</label>
                        <select id="edit_category" name="category" class="form-control" required>
                            <option value="vat">VAT</option>
                            <option value="wht">WHT</option>
                            <option value="cit">CIT</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Rate %</label>
                        <input type="number" step="0.01" min="0" max="100" id="edit_rate" name="rate" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">Applies To</label>
                    <select id="edit_applies_to" name="applies_to" class="form-control" required>
                        <option value="invoice_item">Invoice Line Items</option>
                        <option value="commission_payment">Commission Payouts</option>
                        <option value="loan_interest">Loan Interest Payments</option>
                        <option value="other">General / Annual</option>
                    </select>
                </div>
                <div class="grid grid-2 gap-3">
                    <div class="form-group">
                        <label class="form-label required">Effective From</label>
                        <input type="date" id="edit_effective_from" name="effective_from" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Effective To</label>
                        <input type="date" id="edit_effective_to" name="effective_to" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" id="edit_is_default" name="is_default" value="1"> Make Default
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Tax Type</button>
            </div>
        </form>
    </div>
</div>
@endsection
