@extends('layouts.app')
@section('title', 'Projects & Financial Tracking')
@section('meta_description', 'Manage client projects, project milestones, direct and indirect cost allocations, and profitability analysis.')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Projects</h2>
    <nav class="nav-links">
        <a href="#" class="nav-link active">Active Projects</a>
        <a href="#" class="nav-link">Completed</a>
        <a href="#" class="nav-link">Timesheets</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Projects Dashboard</h1>
        <p class="subtitle">Track budgets, timesheets, and deliverables.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openModal('createProjectModal')">
        <ion-icon name="add-outline"></ion-icon> New Project
    </button>
</header>

<div class="toolbar">
    <div class="toolbar-left" style="display: flex; gap: 1rem; align-items: center;">
        <form id="filterForm" action="/projects" method="GET" style="margin: 0; min-width: 250px;">
            <x-department-selector name="department_id" id="filter_department_id" :departments="$departments" :selected="$deptId ?? null" onchange="document.getElementById('filterForm').submit();" />
        </form>
    </div>
    <div class="toolbar-right">
        <div class="search-input">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" placeholder="Search by Project Title">
        </div>
    </div>
</div>

@if($projects->isEmpty())
    <x-empty-state 
        icon="briefcase-outline" 
        title="No Projects Found" 
        description="Track client deliverables, payment milestones, timesheets, and profitability." 
        actionModal="createProjectModal" 
        actionText="Create First Project" 
    />
@else
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Project Name</th>
                <th>Client</th>
                <th>Department</th>
                <th class="text-right">Budget Limit</th>
                <th class="text-center">Status</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projects as $project)
            <tr>
                <td data-label="Project Name">
                    <a href="/projects/{{ $project->id }}" class="font-medium" style="color:var(--text-heading); text-decoration:none;">{{ $project->name }}</a>
                    @if(isset($project->milestones_due_today_count) && $project->milestones_due_today_count > 0)
                        <span class="badge badge-danger" style="font-size:0.65rem; margin-left:6px;">Milestone Due Today</span>
                    @endif
                </td>
                <td data-label="Client"><span class="text-muted">{{ $project->client_name ?? '-' }}</span></td>
                <td data-label="Department"><span class="text-muted">{{ $project->department_name ?? '-' }}</span></td>
                <td data-label="Budget Limit" class="amount-cell"><span class="text-muted tabular-nums">{{ $project->currency }} {{ number_format($project->budget_limit, 2) }}</span></td>
                <td data-label="Status" class="text-center">
                    @if($project->status === 'active')
                        <span class="badge badge-success">Active</span>
                    @elseif($project->status === 'completed')
                        <span class="badge badge-neutral">Completed</span>
                    @else
                        <span class="badge badge-warning">{{ ucfirst($project->status) }}</span>
                    @endif
                </td>
                <td data-label="Action" class="text-center">
                    <div class="actions" style="justify-content:center;">
                        <a href="/projects/{{ $project->id }}" class="action-btn" title="View Project"><ion-icon name="eye-outline"></ion-icon></a>
                        <button type="button" class="action-btn" title="Edit Project" onclick="openModal('editProjectModal_{{ $project->id }}')"><ion-icon name="create-outline"></ion-icon></button>
                        <form id="delete_project_{{ $project->id }}" action="/projects/{{ $project->id }}" method="POST" style="display:inline; margin:0;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="action-btn action-danger" title="Delete Project" onclick="return confirmAction({title:'Delete Project?', message:'Delete {{ addslashes($project->name) }} and all associated milestones?', confirmText:'Delete Project', formId:'delete_project_{{ $project->id }}'})">
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
<div class="modal-backdrop" id="createProjectModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Project</h3>
            <button class="btn-close" onclick="closeModal('createProjectModal')">&times;</button>
        </div>
        <form action="/projects" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Project Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Department</label>
                        <x-department-selector name="department_id" :departments="$departments" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <x-currency-selector name="currency" :selected="$baseCurrency ?? 'LKR'" required />
                    </div>
                </div>
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Client (Optional)</label>
                        <x-client-selector name="client_id" :clients="$clients" :multiple="false" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="on-hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Budget Limit</label>
                    <x-amount-input name="budget_limit" class="form-control" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createProjectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Project</button>
            </div>
        </form>
    </div>
</div>

@foreach($projects as $project)
@php
    $clientPivot = DB::table('project_party')->where('project_id', $project->id)->where('role', 'client')->first();
    $partnerPivot = DB::table('project_party')->where('project_id', $project->id)->where('role', 'partner')->first();
@endphp
<div class="modal-backdrop" id="editProjectModal_{{ $project->id }}">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Project</h3>
            <button class="btn-close" onclick="closeModal('editProjectModal_{{ $project->id }}')">&times;</button>
        </div>
        <form action="/projects/{{ $project->id }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Project Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $project->name }}" required>
                </div>
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Client (Optional)</label>
                        <select name="client_id" class="form-control">
                            <option value="">-- No Client --</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" {{ ($clientPivot && $clientPivot->party_id == $c->id) ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Budget Limit</label>
                        <x-amount-input name="budget_limit" class="form-control" value="{{ $project->budget_limit }}" required="true" />
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $project->start_date }}">
                    </div>
                    <div class="form-col">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $project->end_date }}">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="draft" {{ $project->status === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ $project->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ $project->status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="on-hold" {{ $project->status === 'on-hold' ? 'selected' : '' }}>On Hold</option>
                        <option value="cancelled" {{ $project->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editProjectModal_{{ $project->id }}')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endforeach

@endsection
