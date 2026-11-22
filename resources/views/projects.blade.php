@extends('layouts.app')
@section('title', 'Projects & Financial Tracking')
@section('meta_description', 'Manage client projects, project milestones, direct and indirect cost allocations, and profitability analysis.')

@section('secondary-sidebar')
<x-secondary-sidebar title="Projects">
    <nav class="nav-links">
        <a href="/projects?status=active" class="nav-link {{ request('status') === 'active' ? 'active' : '' }}"><ion-icon name="briefcase-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Active Projects</span></a>
        <a href="/projects?status=completed" class="nav-link {{ request('status') === 'completed' ? 'active' : '' }}"><ion-icon name="checkmark-circle-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Completed</span></a>
        <a href="/projects" class="nav-link {{ !request('status') ? 'active' : '' }}"><ion-icon name="albums-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">All projects</span></a>
    </nav>
</x-secondary-sidebar>
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('project-index-search.css') }}?v={{ hash_file('sha256', public_path('project-index-search.css')) }}">
@php
    $clearSearchUrl = url('/projects').(request()->hasAny(['status', 'department_id']) ? '?'.\Illuminate\Support\Arr::query(request()->only(['status', 'department_id'])) : '');
    $hasProjectFilters = $search !== '' || $deptId || request()->filled('status');
@endphp
<header class="page-header">
    <div class="header-titles">
        <h1>Projects Dashboard</h1>
        <p class="subtitle">Track budgets, timesheets, and deliverables.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openModal('createProjectModal')">
        <ion-icon name="add-outline"></ion-icon> New Project
    </button>
</header>

<form id="filterForm" action="/projects" method="GET" class="project-index-search" role="search" aria-label="Search the Projects index">
    @if(request()->filled('status'))
        <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    <div class="project-index-search-fields">
        <div class="project-index-search-field">
            <label for="project-index-query">Search projects and parties</label>
            <div class="project-index-search-entry">
                <div class="project-index-search-input">
                    <ion-icon name="search-outline" aria-hidden="true"></ion-icon>
                    <input id="project-index-query" type="search" name="search" value="{{ $search }}" placeholder="Name, email or phone">
                </div>
                <button class="btn btn-outline" type="submit">Search</button>
            </div>
        </div>
        <div class="project-index-search-field" role="group" aria-labelledby="project-index-department">
            <span id="project-index-department">Department</span>
            <x-department-selector name="department_id" id="filter_department_id" :departments="$departments" :selected="$deptId ?? null" onchange="document.getElementById('filterForm').submit();" />
        </div>
    </div>
    <div class="project-index-search-results">
        <span><strong>{{ $projects->total() }}</strong> {{ Str::plural('project', $projects->total()) }}{{ $hasProjectFilters ? ' found' : '' }}</span>
        @if($search !== '')
            <a href="{{ $clearSearchUrl }}">Clear search</a>
        @endif
    </div>
</form>

@if($projects->isEmpty())
    @if($hasProjectFilters)
        <div class="project-index-search-empty">
            <h2>No projects match</h2>
            <p>Try another name, email or phone, or clear the filters.</p>
            <a class="btn btn-outline" href="/projects">Clear filters</a>
        </div>
    @else
        <x-empty-state
            icon="briefcase-outline"
            title="No Projects Found"
            description="Track client deliverables, payment milestones, timesheets, and profitability."
            actionModal="createProjectModal"
            actionText="Create First Project"
        />
    @endif
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
                        <span class="badge badge-danger" style="font-size:0.75rem; margin-left:6px;">Milestone Due Today</span>
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
{{ $projects->links() }}
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
