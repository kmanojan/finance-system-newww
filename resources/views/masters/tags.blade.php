@extends('layouts.app')
@section('title', 'Tags - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Tags</h1>
        <p class="subtitle">Manage colored tags for labeling transactions and items.</p>
    </div>
    <button class="btn btn-primary btn-pill mobile-hide" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Tag
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
            <input type="text" placeholder="Search tags">
        </div>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Color</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td data-label="Name"><span class="font-medium">{{ $item->name ?? '' }}</span></td>
                <td data-label="Color">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 16px; height: 16px; border-radius: 50%; background-color: {{ $item->color ?? '#5243E8' }};"></div>
                        <span class="text-muted">{{ $item->color ?? '#5243E8' }}</span>
                    </div>
                </td>
                <td data-label="Action">
                    <div class="actions">
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/tags/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr><td colspan="3" class="text-center text-muted py-4">No tags found.</td></tr>
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
        document.getElementById('editForm').action = '/master/tags/' + item.id;
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_color')) document.getElementById('edit_color').value = item.color || '#5243E8';
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Tag</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/tags" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tag Color</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="color" name="color" value="#5243E8" style="width: 40px; height: 40px; padding: 0; border: none; border-radius: 4px; cursor: pointer;">
                        <span class="text-muted text-sm">Select a color to visually identify this tag</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Tag</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Tag</h3>
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
                    <label class="form-label">Tag Color</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="color" name="color" id="edit_color" class="form-control" style="width: 40px; height: 40px; padding: 0; border: none; border-radius: 4px; cursor: pointer;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Tag</button>
            </div>
        </form>
    </div>
</div>
@endsection
