@extends('layouts.app')
@section('title', 'Invoice Types - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Invoice Types</h1>
        <p class="subtitle">Manage invoice classifications and default categories.</p>
    </div>
    <button class="btn btn-primary btn-pill mobile-hide" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Invoice Type
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
            <input type="text" placeholder="Search invoice types">
        </div>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Maps To</th>
                <th>Default Category</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td data-label="Name"><span class="font-medium">{{ $item->name ?? '' }}</span></td>
                <td data-label="Maps To"><span class="badge" style="background:#f1f5f9; color:#475569; font-size:0.8rem; padding:0.4em 0.8em; border-radius:6px;">{{ ucfirst($item->maps_to) }}</span></td>
                <td data-label="Category"><span class="text-muted">{{ $categories->firstWhere('id', $item->default_category_id)->name ?? '-' }}</span></td>
                <td data-label="Action">
                    <div class="actions">
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/invoice-types/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr><td colspan="4" class="text-center text-muted py-4">No invoice types found.</td></tr>
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
        document.getElementById('editForm').action = '/master/invoice-types/' + item.id;
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_maps_to')) document.getElementById('edit_maps_to').value = item.maps_to || '';
        if (document.getElementById('edit_default_category_id')) document.getElementById('edit_default_category_id').value = item.default_category_id || '';
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Invoice Type</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/invoice-types" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Maps To</label>
                        <select name="maps_to" class="form-control" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Default Category</label>
                        <select name="default_category_id" class="form-control">
                            <option value="">-- None --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }} ({{ ucfirst($category->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Invoice Type</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Invoice Type</h3>
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
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Maps To</label>
                        <select name="maps_to" id="edit_maps_to" class="form-control" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Default Category</label>
                        <select name="default_category_id" id="edit_default_category_id" class="form-control">
                            <option value="">-- None --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }} ({{ ucfirst($category->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Invoice Type</button>
            </div>
        </form>
    </div>
</div>
@endsection
