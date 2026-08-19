@extends('layouts.app')
@section('title', 'Departments - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Departments</h1>
        <p class="subtitle">Manage company departments and organizational structure.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Department
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
    <div class="toolbar-left">
        <div class="btn-group" style="display:flex; gap:0.5rem;">
            <button class="btn btn-outline" onclick="toggleView('table')" id="btn-view-table" style="background:var(--primary-light); border-color:var(--primary);">Table View</button>
            <button class="btn btn-outline" onclick="toggleView('tree')" id="btn-view-tree">Tree View</button>
        </div>
    </div>
    <div class="toolbar-right">
        <div class="search-input">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" placeholder="Search departments">
        </div>
    </div>
</div>

<div class="data-table-container" id="view-table">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Parent Department</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td data-label="Name"><span class="font-medium">{{ $item->name ?? '' }}</span></td>
                <td data-label="Code"><span class="text-muted">{{ $item->code ?? '-' }}</span></td>
                <td data-label="Parent">
                    @if($item->parent_id)
                        @php $parent = DB::table('departments')->where('id', $item->parent_id)->first(); @endphp
                        <span class="text-muted">{{ $parent ? $parent->name : '-' }}</span>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td data-label="Action">
                    <div class="actions">
                        <button class="action-btn" title="Edit" onclick="openEditModal({{ json_encode($item) }})"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/departments/{{ $item->id }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr><td colspan="4" class="text-center text-muted py-4">No departments found.</td></tr>
            @endif
        </tbody>
    </table>
</div>

<div class="data-table-container" id="view-tree" style="display:none; padding:1.5rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <ul class="tree-list" style="list-style:none; padding-left:0; margin:0;">
        @php
            $renderedIds = [];
            $buildTree = function($departments, $parentId = null) use (&$buildTree, &$renderedIds) {
                $html = '';
                foreach ($departments as $dept) {
                    $effectiveParent = ($dept->parent_id == $dept->id) ? null : $dept->parent_id;
                    if ($effectiveParent == $parentId && !isset($renderedIds[$dept->id])) {
                        $renderedIds[$dept->id] = true;
                        $html .= '<li style="margin: 0.5rem 0;">';
                        $html .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem 1rem; background:var(--bg-sidebar-secondary); border:1px solid var(--border); border-radius:8px;">';
                        $html .= '<div><span class="font-medium">' . e($dept->name) . '</span> <span class="text-muted" style="font-size:0.8rem;">(' . e($dept->code ?? '-') . ')</span></div>';
                        $html .= '<div class="actions">
                            <button class="action-btn" title="Add Sub-department" onclick="openCreateModalWithParent(' . $dept->id . ')"><ion-icon name="add-outline"></ion-icon></button>
                            <button class="action-btn" title="Edit" onclick="openEditModal(' . htmlspecialchars(json_encode($dept)) . ')"><ion-icon name="create-outline"></ion-icon></button>
                            <form action="/master/departments/' . $dept->id . '" method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this record?\');">
                                ' . csrf_field() . '
                                ' . method_field("DELETE") . '
                                <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                            </form>
                        </div>';
                        $html .= '</div>';
                        
                        $children = $buildTree($departments, $dept->id);
                        if ($children) {
                            $html .= '<ul style="list-style:none; padding-left:2rem; margin-top:0.5rem; border-left:2px solid var(--border);">' . $children . '</ul>';
                        }
                        $html .= '</li>';
                    }
                }
                return $html;
            };

            $treeHtml = $buildTree($data);

            // Fallback for any orphaned departments not matched by hierarchy
            foreach ($data as $dept) {
                if (!isset($renderedIds[$dept->id])) {
                    $renderedIds[$dept->id] = true;
                    $treeHtml .= '<li style="margin: 0.5rem 0;">';
                    $treeHtml .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem 1rem; background:var(--bg-sidebar-secondary); border:1px solid var(--border); border-radius:8px;">';
                    $treeHtml .= '<div><span class="font-medium">' . e($dept->name) . '</span> <span class="text-muted" style="font-size:0.8rem;">(' . e($dept->code ?? '-') . ')</span></div>';
                    $treeHtml .= '<div class="actions">
                        <button class="action-btn" title="Add Sub-department" onclick="openCreateModalWithParent(' . $dept->id . ')"><ion-icon name="add-outline"></ion-icon></button>
                        <button class="action-btn" title="Edit" onclick="openEditModal(' . htmlspecialchars(json_encode($dept)) . ')"><ion-icon name="create-outline"></ion-icon></button>
                        <form action="/master/departments/' . $dept->id . '" method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this record?\');">
                            ' . csrf_field() . '
                            ' . method_field("DELETE") . '
                            <button type="submit" class="action-btn" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
                        </form>
                    </div>';
                    $treeHtml .= '</div>';
                    $treeHtml .= '</li>';
                }
            }
        @endphp
        {!! $treeHtml !!}

    </ul>
</div>

<script>
    function toggleView(mode) {
        var tableView = document.getElementById('view-table');
        var treeView = document.getElementById('view-tree');
        var btnTable = document.getElementById('btn-view-table');
        var btnTree = document.getElementById('btn-view-tree');

        if (mode === 'tree') {
            tableView.style.display = 'none';
            treeView.style.display = 'block';
            btnTree.style.background = 'var(--primary-light)';
            btnTree.style.borderColor = 'var(--primary)';
            btnTable.style.background = 'transparent';
            btnTable.style.borderColor = 'var(--border)';
            localStorage.setItem('department_view_mode', 'tree');
        } else {
            tableView.style.display = 'block';
            treeView.style.display = 'none';
            btnTable.style.background = 'var(--primary-light)';
            btnTable.style.borderColor = 'var(--primary)';
            btnTree.style.background = 'transparent';
            btnTree.style.borderColor = 'var(--border)';
            localStorage.setItem('department_view_mode', 'table');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var savedView = localStorage.getItem('department_view_mode');
        if (savedView === 'tree') {
            toggleView('tree');
        } else {
            toggleView('table');
        }
    });

    function openCreateModal() {
        var createForm = document.querySelector('#createModal form');
        if (createForm) createForm.reset();
        
        if (document.getElementById('create_parent_id')) {
            if (typeof setDepartmentSelectorValue === 'function') {
                setDepartmentSelectorValue('create_parent_id', '');
            } else {
                document.getElementById('create_parent_id').value = '';
            }
        }
        openModal('createModal');
    }

    function openCreateModalWithParent(parentId) {
        var createForm = document.querySelector('#createModal form');
        if (createForm) createForm.reset();
        
        if (document.getElementById('create_parent_id')) {
            if (typeof setDepartmentSelectorValue === 'function') {
                setDepartmentSelectorValue('create_parent_id', parentId);
            } else {
                document.getElementById('create_parent_id').value = parentId;
            }
        }
        openModal('createModal');
    }

    function openEditModal(item) {
        document.getElementById('editForm').action = '/master/departments/' + item.id;
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_code')) document.getElementById('edit_code').value = item.code || '';
        if (document.getElementById('edit_parent_id')) {
            if (typeof setDepartmentSelectorValue === 'function') {
                setDepartmentSelectorValue('edit_parent_id', item.parent_id || '');
            } else {
                document.getElementById('edit_parent_id').value = item.parent_id || '';
            }
        }
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Department</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/departments" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <input type="hidden" name="company_id" value="{{ $companies->first()->id ?? 1 }}">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Department Code</label>
                        <input type="text" name="code" class="form-control">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Parent Department</label>
                        <x-department-selector name="parent_id" id="create_parent_id" :departments="DB::table('departments')->get()" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Department</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Department</h3>
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
                        <label class="form-label">Department Code</label>
                        <input type="text" name="code" id="edit_code" class="form-control">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Parent Department</label>
                        <x-department-selector name="parent_id" id="edit_parent_id" :departments="DB::table('departments')->get()" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Department</button>
            </div>
        </form>
    </div>
</div>
@endsection
