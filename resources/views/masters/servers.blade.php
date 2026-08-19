@extends('layouts.app')
@section('title', 'Servers - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Servers</h1>
        <p class="subtitle">Manage infrastructure servers and cloud hosting instances.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Server
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
            <input type="text" placeholder="Search servers">
        </div>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Provider</th>
                <th>Reference</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td data-label="Name"><span class="font-medium">{{ $item->name ?? '' }}</span></td>
                <td data-label="Provider"><span class="text-muted">{{ $item->provider ?? '-' }}</span></td>
                <td data-label="Reference"><span class="text-muted">{{ $item->reference ?? '-' }}</span></td>
                <td data-label="Status">
                    <span class="badge" style="background:{{ $item->is_active ? 'var(--success-light, #dcfce7)' : '#f1f5f9' }}; color:{{ $item->is_active ? 'var(--success, #166534)' : '#475569' }}; font-size:0.75rem; padding:0.25rem 0.5rem; border-radius:4px; font-weight:600;">
                        {{ $item->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td data-label="Action">
                    <div class="actions">
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/servers/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr><td colspan="5" class="text-center text-muted py-4">No servers found.</td></tr>
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
        document.getElementById('editForm').action = '/master/servers/' + item.id;
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_provider')) document.getElementById('edit_provider').value = item.provider || '';
        if (document.getElementById('edit_reference')) document.getElementById('edit_reference').value = item.reference || '';
        if (document.getElementById('edit_is_active')) document.getElementById('edit_is_active').checked = item.is_active == 1;
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Server</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/servers" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Provider</label>
                    <input type="text" name="provider" class="form-control" placeholder="e.g. AWS, DigitalOcean, Hetzner">
                </div>
                <div class="form-group">
                    <label class="form-label">Reference / Instance ID</label>
                    <input type="text" name="reference" class="form-control" placeholder="e.g. droplet-12345">
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Active Status
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Server</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Server</h3>
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
                    <label class="form-label">Provider</label>
                    <input type="text" name="provider" id="edit_provider" class="form-control" placeholder="e.g. AWS, DigitalOcean, Hetzner">
                </div>
                <div class="form-group">
                    <label class="form-label">Reference / Instance ID</label>
                    <input type="text" name="reference" id="edit_reference" class="form-control" placeholder="e.g. droplet-12345">
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Active Status
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Server</button>
            </div>
        </form>
    </div>
</div>
@endsection
