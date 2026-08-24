@extends('layouts.app')
@section('title', 'Parties - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Parties</h1>
        <p class="subtitle">Manage clients, vendors, and partner organizations.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Party
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
    @if(session('generated_link'))
        <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" readonly value="{{ session('generated_link') }}" class="form-control" style="max-width: 400px; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.9rem; border-color: #bbf7d0;">
            <button type="button" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.9rem; border-color: #166534; color: #166534;" onclick="navigator.clipboard.writeText('{{ session('generated_link') }}'); alert('Copied to clipboard!')">Copy Link</button>
        </div>
    @endif
</div>
@endif

<div class="toolbar">
    <div class="toolbar-left">
        <span style="font-size:0.85rem; color:var(--text-muted);">
            Total: <strong style="color:var(--text-heading);">{{ $data->total() }}</strong> parties
        </span>
    </div>
    <div class="toolbar-right">
        <form method="GET" action="/master/parties" style="margin:0; display:flex; gap:0.5rem; align-items:center;">
            <div class="search-input">
                <ion-icon name="search-outline"></ion-icon>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search parties by name, email, phone...">
            </div>
            @if(request('search') || request('type') || request('status'))
                <a href="/master/parties" class="btn btn-outline" style="padding:0.4rem 0.75rem; font-size:0.85rem;" title="Clear Filters">Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type(s)</th>
                <th>Contact Person</th>
                <th>Email / Phone</th>
                <th>Linked Projects</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td data-label="Name"><span class="font-medium">{{ $item->name ?? '' }}</span></td>
                <td data-label="Type(s)">
                    @foreach(explode(',', $item->types) as $type)
                        @if(!empty($type))
                            <span class="badge" style="background:var(--primary-light); color:var(--primary); font-size:0.75rem; margin-right:4px; padding:0.2em 0.6em; border-radius:4px;">{{ ucfirst($type) }}</span>
                        @endif
                    @endforeach
                </td>
                <td data-label="Contact Person"><span class="text-muted">{{ $item->contact_person ?? '-' }}</span></td>
                <td data-label="Email / Phone"><span class="text-muted">{{ $item->email ?? '-' }}<br><small>{{ $item->phone ?? '' }}</small></span></td>
                <td data-label="Linked Projects"><span class="text-muted">{{ $item->projects_count ?? 0 }}</span></td>
                <td data-label="Status">
                    <span class="badge" style="background:{{ $item->status === 'active' ? 'var(--success-light)' : '#fee2e2' }}; color:{{ $item->status === 'active' ? 'var(--success)' : '#b91c1c' }}; font-size:0.75rem;">
                        {{ ucfirst($item->status) }}
                    </span>
                </td>
                <td data-label="Action">
                    <div class="actions">
                        @if(str_contains($item->types ?? '', 'client'))
                        <form action="/share-links" method="POST" style="display:inline;" title="Generate Client Share Link">
                            @csrf
                            <input type="hidden" name="shareable_type" value="party">
                            <input type="hidden" name="shareable_id" value="{{ $item->id }}">
                            <input type="hidden" name="audience" value="client">
                            <input type="hidden" name="expires_at" value="{{ \Carbon\Carbon::now()->addDays(30)->format('Y-m-d') }}">
                            <button type="submit" class="action-btn" style="color:var(--primary);" title="Generate Client Share Link"><ion-icon name="share-social-outline"></ion-icon></button>
                        </form>
                        @endif
                        @if(str_contains($item->types ?? '', 'partner'))
                        <form action="/share-links" method="POST" style="display:inline;" title="Generate Partner Share Link">
                            @csrf
                            <input type="hidden" name="shareable_type" value="party">
                            <input type="hidden" name="shareable_id" value="{{ $item->id }}">
                            <input type="hidden" name="audience" value="partner">
                            <input type="hidden" name="expires_at" value="{{ \Carbon\Carbon::now()->addDays(30)->format('Y-m-d') }}">
                            <button type="submit" class="action-btn" style="color:#d97706;" title="Generate Partner Share Link"><ion-icon name="share-outline"></ion-icon></button>
                        </form>
                        @endif
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/parties/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr><td colspan="7" class="text-center text-muted py-4">No parties found.</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if($data->hasPages())
<div style="margin-top:1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="font-size:0.85rem; color:var(--text-muted);">
        Showing {{ $data->firstItem() ?? 0 }} to {{ $data->lastItem() ?? 0 }} of {{ $data->total() }} parties
    </div>
    <div>
        {{ $data->links() }}
    </div>
</div>
@endif

<script>
    function toggleCommFields(prefix) {
        const isPartner = document.getElementById(prefix + '_type_partner')?.checked;
        const isVendor = document.getElementById(prefix + '_type_vendor')?.checked;
        const commFields = document.getElementById(prefix + '_commission_fields');
        if (commFields) {
            if (isPartner || isVendor) {
                commFields.style.display = 'flex';
            } else {
                commFields.style.display = 'none';
            }
        }
    }

    function openCreateModal() {
        var createForm = document.querySelector('#createModal form');
        if (createForm) createForm.reset();
        openModal('createModal');
    }

    function openEditModal(item) {
        document.getElementById('editForm').action = '/master/parties/' + item.id;
        
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_contact_person')) document.getElementById('edit_contact_person').value = item.contact_person || '';
        if (document.getElementById('edit_tax_id')) document.getElementById('edit_tax_id').value = item.tax_id || '';
        if (document.getElementById('edit_email')) document.getElementById('edit_email').value = item.email || '';
        if (document.getElementById('edit_phone')) document.getElementById('edit_phone').value = item.phone || '';
        if (document.getElementById('edit_status')) document.getElementById('edit_status').value = item.status || 'active';
        if (document.getElementById('edit_address')) document.getElementById('edit_address').value = item.address || '';
        if (document.getElementById('edit_notes')) document.getElementById('edit_notes').value = item.notes || '';
        
        var types = (item.types || '').split(',');
        if (document.getElementById('edit_type_client')) document.getElementById('edit_type_client').checked = types.includes('client');
        if (document.getElementById('edit_type_partner')) document.getElementById('edit_type_partner').checked = types.includes('partner');
        if (document.getElementById('edit_type_vendor')) document.getElementById('edit_type_vendor').checked = types.includes('vendor');
        
        if (document.getElementById('edit_default_commission_type')) document.getElementById('edit_default_commission_type').value = item.default_commission_type || 'percentage';
        if (document.getElementById('edit_default_commission_value')) document.getElementById('edit_default_commission_value').value = item.default_commission_value || '';

        toggleCommFields('edit');
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Party</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/parties" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:block; margin-bottom:0.5rem;">Party Type(s) *</label>
                    <div style="display:flex; gap:1.5rem; align-items:center;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="checkbox" name="types[]" value="client" checked style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Client
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="checkbox" name="types[]" value="partner" id="create_type_partner" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);" onchange="toggleCommFields('create')"> Partner
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="checkbox" name="types[]" value="vendor" id="create_type_vendor" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);" onchange="toggleCommFields('create')"> Vendor
                        </label>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Tax ID</label>
                        <input type="text" name="tax_id" class="form-control">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>

                <div class="form-row" id="create_commission_fields" style="margin-top: 1.5rem; display:none; background:var(--bg-sidebar-secondary); padding:1rem; border-radius:8px; border:1px solid var(--border);">
                    <div class="form-col">
                        <label class="form-label">Default Commission Type</label>
                        <select name="default_commission_type" class="form-control">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Default Commission Value</label>
                        <x-amount-input name="default_commission_value" placeholder="e.g. 10 or 1500" />
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Party</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Party</h3>
            <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:block; margin-bottom:0.5rem;">Party Type(s) *</label>
                    <div style="display:flex; gap:1.5rem; align-items:center;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="checkbox" name="types[]" value="client" id="edit_type_client" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Client
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="checkbox" name="types[]" value="partner" id="edit_type_partner" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);" onchange="toggleCommFields('edit')"> Partner
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="checkbox" name="types[]" value="vendor" id="edit_type_vendor" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);" onchange="toggleCommFields('edit')"> Vendor
                        </label>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Tax ID</label>
                        <input type="text" name="tax_id" id="edit_tax_id" class="form-control">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                </div>

                <div class="form-row" id="edit_commission_fields" style="margin-top: 1.5rem; display:none; background:var(--bg-sidebar-secondary); padding:1rem; border-radius:8px; border:1px solid var(--border);">
                    <div class="form-col">
                        <label class="form-label">Default Commission Type</label>
                        <select name="default_commission_type" id="edit_default_commission_type" class="form-control">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Default Commission Value</label>
                        <x-amount-input name="default_commission_value" id="edit_default_commission_value" placeholder="e.g. 10 or 1500" />
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Party</button>
            </div>
        </form>
    </div>
</div>
@endsection
