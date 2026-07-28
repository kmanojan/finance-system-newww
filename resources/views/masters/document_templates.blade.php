@extends('layouts.app')
@section('title', 'Document Templates - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Document Templates</h1>
        <p class="subtitle">Manage header, footer, background templates, and invoice defaults.</p>
    </div>
    <button class="btn btn-primary btn-pill mobile-hide" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Template
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
            <input type="text" placeholder="Search document templates">
        </div>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Department ID</th>
                <th>Default</th>
                <th>Language</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td data-label="Name"><span class="font-medium">{{ $item->name ?? '' }}</span></td>
                <td data-label="Department ID"><span class="text-muted">{{ $item->department_id ?? 'Any' }}</span></td>
                <td data-label="Default">
                    <span class="badge" style="background:{{ $item->is_default ? 'var(--success-light)' : '#f1f5f9' }}; color:{{ $item->is_default ? 'var(--success)' : '#475569' }}; font-size:0.75rem;">
                        {{ $item->is_default ? 'Yes' : 'No' }}
                    </span>
                </td>
                <td data-label="Language"><span class="text-muted">{{ $item->language ?? '-' }}</span></td>
                <td data-label="Action">
                    <div class="actions">
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/document-templates/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr><td colspan="5" class="text-center text-muted py-4">No document templates found.</td></tr>
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
        document.getElementById('editForm').action = '/master/document-templates/' + item.id;
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_department_id')) {
            if (typeof setDepartmentSelectorValue === 'function') {
                setDepartmentSelectorValue('edit_department_id', item.department_id || '');
            } else {
                document.getElementById('edit_department_id').value = item.department_id || '';
            }
        }
        if (document.getElementById('edit_is_default')) document.getElementById('edit_is_default').checked = item.is_default == 1;
        if (document.getElementById('edit_language')) document.getElementById('edit_language').value = item.language || '';
        if (document.getElementById('edit_bank_details')) document.getElementById('edit_bank_details').value = item.bank_details || '';
        if (document.getElementById('edit_description')) document.getElementById('edit_description').value = item.description || '';
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card" style="max-width: 950px;">
        <div class="modal-header">
            <h3 class="modal-title">New Document Template</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/document-templates" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <input type="hidden" name="company_id" value="{{ $companies->first()->id ?? 1 }}">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Department</label>
                        <x-department-selector name="department_id" id="create_doc_department_id" :departments="$departments" />
                    </div>
                    <div class="form-col" style="display:flex; align-items:center;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" value="1" style="width:1.2rem; height:1.2rem; accent-color:var(--primary);"> 
                            Set as Default Template
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Language</label>
                    <input type="text" name="language" class="form-control" value="English">
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Header Image (Optional)</label>
                    <input type="file" name="header_image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Footer Image (Optional)</label>
                    <input type="file" name="footer_image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Background Image (Optional)</label>
                    <input type="file" name="background_image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Bank Details</label>
                    <textarea name="bank_details" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Description (Terms/Thank you notes)</label>
                    <textarea name="description" id="create_description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Template</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card" style="max-width: 950px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Document Template</h3>
            <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editForm" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Department</label>
                        <x-department-selector name="department_id" id="edit_department_id" :departments="$departments" />
                    </div>
                    <div class="form-col" style="display:flex; align-items:center;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" id="edit_is_default" value="1" style="width:1.2rem; height:1.2rem; accent-color:var(--primary);"> 
                            Set as Default Template
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Language</label>
                    <input type="text" name="language" id="edit_language" class="form-control">
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Header Image (Optional)</label>
                    <input type="file" name="header_image" id="edit_header_image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Footer Image (Optional)</label>
                    <input type="file" name="footer_image" id="edit_footer_image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Background Image (Optional)</label>
                    <input type="file" name="background_image" id="edit_background_image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Bank Details</label>
                    <textarea name="bank_details" id="edit_bank_details" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Description (Terms/Thank you notes)</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Template</button>
            </div>
        </form>
    </div>
</div>
@endsection
