@extends('layouts.app')
@section('title', 'Bank Accounts - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Bank Accounts</h1>
        <p class="subtitle">Manage company bank accounts and current balances.</p>
    </div>
    <button class="btn btn-primary btn-pill mobile-hide" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Bank Account
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
            <input type="text" placeholder="Search bank accounts">
        </div>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Bank Name</th>
                <th>Account No</th>
                <th>Currency</th>
                <th>Current Balance</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td data-label="Bank Name"><span class="font-medium">{{ $item->bank_name ?? '' }}</span></td>
                <td data-label="Account No"><span class="text-muted">{{ $item->account_no ?? '-' }}</span></td>
                <td data-label="Currency"><span class="text-muted">{{ $item->currency ?? '-' }}</span></td>
                <td data-label="Current Balance"><span class="font-semibold" style="color:var(--primary);">{{ $item->currency ?? 'LKR' }} {{ number_format($item->current_balance ?? 0, 2) }}</span></td>
                <td data-label="Action">
                    <div class="actions">
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/bank-accounts/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr><td colspan="5" class="text-center text-muted py-4">No bank accounts found.</td></tr>
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
        document.getElementById('editForm').action = '/master/bank-accounts/' + item.id;
        if (document.getElementById('edit_bank_name')) document.getElementById('edit_bank_name').value = item.bank_name || '';
        if (document.getElementById('edit_account_no')) document.getElementById('edit_account_no').value = item.account_no || '';
        if (document.getElementById('edit_currency')) document.getElementById('edit_currency').value = item.currency || '';
        if (document.getElementById('edit_current_balance')) document.getElementById('edit_current_balance').value = item.current_balance || '0.00';
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Bank Account</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/bank-accounts" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Bank Name *</label>
                    <input type="text" name="bank_name" class="form-control" required>
                </div>
                <div class="form-row" style="margin-top:1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Account No. *</label>
                        <input type="text" name="account_no" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency *</label>
                        <x-currency-selector name="currency" selected="LKR" required />
                    </div>
                </div>
                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Starting Balance</label>
                    <x-amount-input name="current_balance" value="0.00" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Account</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Bank Account</h3>
            <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Bank Name *</label>
                    <input type="text" name="bank_name" id="edit_bank_name" class="form-control" required>
                </div>
                <div class="form-row" style="margin-top:1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Account No. *</label>
                        <input type="text" name="account_no" id="edit_account_no" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency *</label>
                        <input type="text" name="currency" id="edit_currency" class="form-control" required>
                    </div>
                </div>
                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Current Balance</label>
                    <x-amount-input name="current_balance" id="edit_current_balance" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Account</button>
            </div>
        </form>
    </div>
</div>
@endsection
