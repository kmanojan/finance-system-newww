@extends('layouts.app')
@section('title', 'Users - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Users</h1>
        <p class="subtitle">Manage user accounts, roles, departmental assignments, and login permissions.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openCreateModal()">
        <ion-icon name="person-add-outline"></ion-icon> Add New User
    </button>
</header>

@if(session('error'))
<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 0.5rem;">
    <ion-icon name="alert-circle-outline" style="font-size: 1.25rem;"></ion-icon>
    <span>{{ session('error') }}</span>
</div>
@endif

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0; display: flex; align-items: center; gap: 0.5rem;">
    <ion-icon name="checkmark-circle-outline" style="font-size: 1.25rem;"></ion-icon>
    <span>{{ session('success') }}</span>
</div>
@endif

@if($errors->any())
<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fecaca;">
    <ul style="margin: 0; padding-left: 1.25rem;">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="GET" action="{{ route('masters.users.index') }}" class="toolbar" style="gap: 0.75rem; flex-wrap: wrap;">
    <div class="toolbar-left" style="display: flex; gap: 0.75rem; flex-wrap: wrap; flex: 1;">
        <div class="search-input" style="min-width: 220px; flex: 1;">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, phone..." onchange="this.form.submit()">
        </div>

        <select name="role" class="form-control" style="width: auto; min-width: 130px;" onchange="this.form.submit()">
            <option value="">All Roles</option>
            @foreach($roles as $key => $label)
                <option value="{{ $key }}" {{ request('role') == $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="department_id" class="form-control" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
        </select>

        <select name="status" class="form-control" style="width: auto; min-width: 130px;" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>

        @if(request('search') || request('role') || request('department_id') || request('status'))
            <a href="{{ route('masters.users.index') }}" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.25rem;">
                <ion-icon name="close-circle-outline"></ion-icon> Clear Filters
            </a>
        @endif
    </div>
</form>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Department</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Created</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td data-label="User">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; flex-shrink: 0;">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="font-medium" style="color: var(--text-heading);">
                                {{ $user->name }}
                                @if($user->id === auth()->id())
                                    <span style="font-size: 0.7rem; background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; margin-left: 4px; font-weight: 600;">You</span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem;">{{ $user->email }}</div>
                        </div>
                    </div>
                </td>
                <td data-label="Role">
                    @php
                        $roleColors = [
                            'admin' => ['bg' => '#f3e8ff', 'text' => '#7e22ce'],
                            'manager' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
                            'accountant' => ['bg' => '#fef3c7', 'text' => '#b45309'],
                            'staff' => ['bg' => '#ecfdf5', 'text' => '#047857'],
                            'viewer' => ['bg' => '#f1f5f9', 'text' => '#475569'],
                        ];
                        $roleStyle = $roleColors[strtolower($user->role)] ?? ['bg' => '#f1f5f9', 'text' => '#475569'];
                    @endphp
                    <span class="badge" style="background: {{ $roleStyle['bg'] }}; color: {{ $roleStyle['text'] }}; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 600; text-transform: capitalize;">
                        {{ $roles[$user->role] ?? ucfirst($user->role ?? 'Staff') }}
                    </span>
                </td>
                <td data-label="Department">
                    <span class="text-muted">{{ $user->department?->name ?? 'Global / None' }}</span>
                </td>
                <td data-label="Phone">
                    <span class="text-muted">{{ $user->phone ?: '-' }}</span>
                </td>
                <td data-label="Status">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="badge" style="background: {{ $user->is_active ? 'var(--success-light, #dcfce7)' : '#fee2e2' }}; color: {{ $user->is_active ? 'var(--success, #166534)' : '#991b1b' }}; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 600;">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @if($user->id !== auth()->id())
                        <form action="{{ route('masters.users.toggle_status', $user->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="action-btn" title="{{ $user->is_active ? 'Deactivate User' : 'Activate User' }}" style="color: {{ $user->is_active ? '#e11d48' : '#16a34a' }};">
                                <ion-icon name="{{ $user->is_active ? 'pause-circle-outline' : 'play-circle-outline' }}"></ion-icon>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
                <td data-label="Created">
                    <span class="text-muted" style="font-size: 0.8rem;">{{ $user->created_at ? $user->created_at->format('M d, Y') : '-' }}</span>
                </td>
                <td data-label="Action" style="text-align: right;">
                    <div class="actions" style="justify-content: flex-end;">
                        <button class="action-btn" title="Edit User" onclick="openEditModal({{ json_encode($user) }})">
                            <ion-icon name="create-outline"></ion-icon>
                        </button>
                        @if($user->id !== auth()->id())
                        <form action="{{ route('masters.users.destroy', $user->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete user \'{{ addslashes($user->name) }}\'?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete User" style="color: #e11d48;">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-muted py-4">No users found matching the criteria.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
    function openCreateModal() {
        var form = document.querySelector('#createModal form');
        if (form) form.reset();
        openModal('createModal');
    }

    function openEditModal(user) {
        document.getElementById('editForm').action = '/master/users/' + user.id;
        document.getElementById('edit_name').value = user.name || '';
        document.getElementById('edit_email').value = user.email || '';
        document.getElementById('edit_role').value = user.role || 'staff';
        document.getElementById('edit_department_id').value = user.department_id || '';
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_is_active').checked = user.is_active == 1;
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_password_confirmation').value = '';
        openModal('editModal');
    }
</script>
@endsection

@section('modals')
<!-- Create User Modal -->
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="{{ route('masters.users.store') }}" method="POST">
            @csrf
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address <span style="color:red;">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Role <span style="color:red;">*</span></label>
                        <select name="role" class="form-control" required>
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}" {{ $key == 'staff' ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">Global / None</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. +94 77 123 4567">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Password <span style="color:red;">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password <span style="color:red;">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm password" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" checked style="width: 16px; height: 16px; accent-color: var(--primary);">
                        <span style="font-weight: 500; font-size: 0.9rem;">Active Account (allowed to log in)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit User</h3>
            <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address <span style="color:red;">*</span></label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Role <span style="color:red;">*</span></label>
                        <select name="role" id="edit_role" class="form-control" required>
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="edit_department_id" class="form-control">
                            <option value="">Global / None</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>

                <div style="border-top: 1px solid var(--border-light); padding-top: 1rem; margin-top: 0.5rem;">
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Password Reset (Leave blank to keep current)</span>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" id="edit_password" class="form-control" placeholder="New password">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="password_confirmation" id="edit_password_confirmation" class="form-control" placeholder="Confirm new password">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width: 16px; height: 16px; accent-color: var(--primary);">
                        <span style="font-weight: 500; font-size: 0.9rem;">Active Account (allowed to log in)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update User</button>
            </div>
        </form>
    </div>
</div>
@endsection
