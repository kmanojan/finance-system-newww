@extends('layouts.app')
@section('title', 'Employees')

@section('secondary-sidebar')
@include('navigation.people')
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('people-register.css') }}?v={{ hash_file('sha256', public_path('people-register.css')) }}">
@php
    $hasFilters = request()->filled('search') || request()->filled('status');
    $money = fn ($amount) => number_format($amount, 2);
    $metrics = ['net_salary' => 'Take-home pay', 'ctc' => 'Employer cost'];
    $moreMetrics = ['basic_salary' => 'Basic pay', 'gross_salary' => 'Gross pay', 'total_employer_cost' => 'Employer contributions'];
@endphp
<div class="pi-page pi-employees" data-employee-register>
    <header class="pi-header">
        <div><h1>Employees</h1><p>Find an employee and review their monthly salary profile.</p></div>
        <div class="pi-actions">
            <details class="pi-menu">
                <summary class="pi-button">More actions <ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon></summary>
                <div class="pi-menu-links">
                    <a href="/payroll">Monthly payroll</a>
                    <a href="/master/salary-components">Salary components</a>
                    <button type="button" onclick="openModal('apiSetupModal')">Sync HR employees</button>
                </div>
            </details>
            <a class="pi-button pi-primary" href="/employees/create"><ion-icon name="add-outline" aria-hidden="true"></ion-icon> Add employee</a>
        </div>
    </header>

    @if(session('success'))<p class="pi-notice" role="status">{{ session('success') }}</p>@endif

    <section class="pi-overview pi-employee-overview" aria-label="Active employee salary estimates">
        <div class="pi-highlight">
            <span>Active employees</span>
            <strong>{{ number_format($activeCount) }}</strong>
            <p>Across the employee directory</p>
        </div>
        <div class="pi-estimates">
            <h2>Monthly salary estimates</h2>
            <p>From current salary profiles for active employees.</p>
            @forelse($salaryTotals as $currency => $totals)
                <dl class="pi-metrics" aria-label="Monthly estimates in {{ $currency }}">
                    @foreach($metrics as $key => $label)
                        <div><dt>{{ $label }}</dt><dd><small>{{ $currency }}</small> {{ $money($totals[$key] / 100) }}</dd></div>
                    @endforeach
                </dl>
            @empty
                <p class="pi-estimate-empty">Salary estimates appear when employees are active.</p>
            @endforelse
            @if($salaryTotals)
                <details class="pi-estimate-details">
                    <summary>More salary totals</summary>
                    @foreach($salaryTotals as $currency => $totals)
                        <dl class="pi-metrics" aria-label="Additional estimates in {{ $currency }}">
                            @foreach($moreMetrics as $key => $label)
                                <div><dt>{{ $label }}</dt><dd><small>{{ $currency }}</small> {{ $money($totals[$key] / 100) }}</dd></div>
                            @endforeach
                        </dl>
                    @endforeach
                </details>
            @endif
        </div>
    </section>

    <section class="pi-register" aria-labelledby="employee-register-title">
        <header class="pi-register-header">
            <h2 id="employee-register-title">Employee directory</h2>
            <p>{{ number_format($employees->total()) }} {{ Str::plural('employee', $employees->total()) }}{{ $hasFilters ? ' matching your filters' : ' in total' }}</p>
        </header>
        <form method="get" action="/employees" class="pi-filters" aria-label="Filter employees">
            <div class="pi-field pi-search"><label for="employee-search">Find an employee</label><input id="employee-search" name="search" type="search" maxlength="200" placeholder="Name or code" value="{{ request('search') }}"></div>
            <div class="pi-field"><label for="employee-status">Employment status</label><select name="status" id="employee-status"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></div>
            <div class="pi-actions pi-filter-actions">
                <button type="submit" class="pi-button">Apply filters</button>
                @if($hasFilters)<a href="/employees" class="pi-button pi-quiet">Clear filters</a>@endif
            </div>
        </form>
        @if($employees->count())
            <div class="pi-columns" aria-hidden="true"><span>Employee</span><span>Gross pay</span><span>Estimated take-home pay</span><span>Employer cost</span><span>Actions</span></div>
        @endif
        @forelse($employees as $employee)
            @php
                $salary = $employee->salary_breakdown;
                $currency = $salary['currency'];
                $initials = Str::of($employee->full_name)->squish()->explode(' ')->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
            @endphp
            <article class="pi-entry" aria-labelledby="employee-row-{{ $employee->id }}">
                <div class="pi-row">
                    <div class="pi-identity">
                        <span class="pi-mark pi-avatar" aria-hidden="true">{{ $initials }}@if($employee->profile_picture_url)<img src="{{ $employee->profile_picture_url }}" alt="" loading="lazy" width="40" height="40">@endif</span>
                        <div>
                            <h3 id="employee-row-{{ $employee->id }}"><a href="/employees/{{ $employee->id }}">{{ $employee->full_name }}</a></h3>
                            <p class="pi-subline">{{ $employee->employee_code ?: 'EMP-'.$employee->id }} @if($employee->job_position)· {{ $employee->job_position }}@endif</p>
                            <span class="pi-status pi-state-{{ $employee->status }}">{{ ucfirst($employee->status) }}</span>
                        </div>
                    </div>
                    @foreach(['gross_salary' => 'Gross pay', 'net_salary' => 'Estimated take-home pay', 'ctc' => 'Employer cost'] as $key => $label)
                        <div @class(['pi-money', 'pi-key-money' => $key === 'net_salary', 'pi-employer' => $key === 'ctc'])><span class="pi-label">{{ $label }}</span><span class="pi-amount"><small>{{ $currency }}</small> {{ $money($salary[$key]) }}</span></div>
                    @endforeach
                    <div class="pi-row-actions">
                        <a href="/employees/{{ $employee->id }}" class="pi-button pi-quiet" aria-label="Open profile for {{ $employee->full_name }}">Open</a>
                        <button type="button" class="pi-button pi-icon" hidden data-profile-toggle aria-expanded="false" aria-controls="employee-pay-{{ $employee->id }}" aria-label="Salary breakdown for {{ $employee->full_name }}"><ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon></button>
                    </div>
                </div>
                <div class="pi-breakdown" id="employee-pay-{{ $employee->id }}">
                    @foreach(['Earnings' => ['Basic pay' => 'basic_salary', 'Allowances' => 'total_allowances', 'EPF eligible earnings' => 'epf_base_salary'], 'Employee deductions' => ['Total deductions' => 'total_deductions', 'Employee EPF' => 'employee_epf', 'APIT' => 'apit_tax'], 'Employer contributions' => ['Total contributions' => 'total_employer_cost', 'Employer EPF' => 'employer_epf', 'ETF' => 'employer_etf']] as $heading => $fields)
                        <section><h4>{{ $heading }}</h4><dl>
                            @foreach($fields as $label => $key)<div><dt>{{ $label }}</dt><dd>{{ $currency }} {{ $money($salary[$key]) }}</dd></div>@endforeach
                        </dl></section>
                    @endforeach
                    <footer class="pi-party-action"><x-employee-party-action :employee="$employee" compact /></footer>
                </div>
            </article>
        @empty
            <div class="pi-empty"><h3>{{ $hasFilters ? 'No matching employees' : 'No employees yet' }}</h3><p>{{ $hasFilters ? 'Try another search or reset the filters.' : 'Add an employee to set up their salary profile.' }}</p><a class="pi-button" href="{{ $hasFilters ? '/employees' : '/employees/create' }}">{{ $hasFilters ? 'Clear filters' : 'Add employee' }}</a></div>
        @endforelse
        @if($employees->count() || $employees->hasPages())
            <footer class="pi-pagination">
                @if($employees->count())<span>Showing {{ $employees->firstItem() }}–{{ $employees->lastItem() }} of {{ $employees->total() }} employees</span>@endif
                {{ $employees->links() }}
            </footer>
        @endif
    </section>
</div>
<script src="{{ asset('employee-register.js') }}?v={{ hash_file('sha256', public_path('employee-register.js')) }}" defer></script>
@endsection

@section('modals')
<!-- HR API Setup Modal -->
<div class="modal-backdrop" id="apiSetupModal">
    <div class="modal-card" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="sync-outline" style="color:var(--primary); font-size:1.3rem;"></ion-icon>
                <span>HR Employee Synchronization</span>
            </h3>
            <button type="button" class="btn-close" aria-label="Close HR sync" onclick="closeModal('apiSetupModal')">&times;</button>
        </div>
        <form id="apiSetupForm" onsubmit="handleApiSetup(event)">
            @csrf
            <div class="modal-body" style="padding:1.5rem;">
                @if(isset($integration) && $integration)
                <div style="background:var(--bg-page); border:1px solid var(--border-light); border-radius:8px; padding:0.75rem 1rem; margin-bottom:1.25rem; font-size:0.85rem;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="color:var(--text-muted);">Current Integration:</span>
                        <strong style="color:var(--text-heading);">{{ $integration->name }}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="color:var(--text-muted);">Last Synced:</span>
                        <span style="color:var(--text-main);">{{ $integration->last_synced_at ? \Carbon\Carbon::parse($integration->last_synced_at)->diffForHumans() : 'Never' }}</span>
                    </div>
                    @if($integration->last_sync_status)
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:var(--text-muted);">Status:</span>
                        <span class="badge {{ $integration->last_sync_status === 'success' ? 'badge-success' : 'badge-danger' }}" style="font-size:0.75rem;">
                            {{ ucfirst($integration->last_sync_status) }}
                        </span>
                    </div>
                    @endif
                </div>
                @endif

                <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">Sync employee profiles and employment status from HR. Nexus imports all active and inactive employees across every page. Existing salary settings and payment history are preserved.</p>
                
                <input type="hidden" id="integration_id" value="{{ $integration->id ?? '' }}">

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" for="integration_name">Integration Name *</label>
                    <input type="text" id="integration_name" name="name" class="form-control" value="{{ $integration->name ?? '' }}" placeholder="E.g. Nexus / BambooHR" required>
                </div>
                
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" for="integration_api_url">API URL *</label>
                    <input type="url" id="integration_api_url" name="api_url" class="form-control" value="{{ $integration->url ?? '' }}" placeholder="https://api.hr-system.com/employees" required>
                </div>
                
                <div class="form-row" style="margin-bottom:1rem;">
                    <div class="form-col">
                        <label class="form-label" for="integration_method">HTTP Method *</label>
                        <select id="integration_method" name="method" class="form-control">
                            <option value="GET" {{ ($integration->method ?? 'GET') === 'GET' ? 'selected' : '' }}>GET</option>
                            <option value="POST" {{ ($integration->method ?? '') === 'POST' ? 'selected' : '' }}>POST</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" for="integration_response_data_path">Data Path</label>
                        <input type="text" id="integration_response_data_path" name="response_data_path" class="form-control" value="{{ $integration->response_path ?? 'data' }}" placeholder="E.g. data">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" for="integration_bearer_token">Bearer Token</label>
                    <input type="password" id="integration_bearer_token" name="bearer_token" class="form-control" placeholder="{{ isset($integration) && $integration->bearer_token ? '•••••••••••••••• (Leave blank to keep existing token)' : 'Enter API Bearer Token' }}">
                    @if(isset($integration) && $integration->bearer_token)
                        <small style="color:var(--text-muted); font-size:0.75rem;">Existing token is active. Leave blank to keep it.</small>
                    @endif
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:0.75rem; padding:1.25rem 1.5rem; border-top:1px solid var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="closeModal('apiSetupModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSyncSubmit" data-no-spinner style="display:inline-flex; align-items:center; gap:0.4rem;">
                    <ion-icon name="sync-outline"></ion-icon>
                    <span>Sync Now</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function handleApiSetup(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSyncSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-icon"></span> Syncing...';
    
    const integrationId = document.getElementById('integration_id').value;
    const payload = {
        id: integrationId || null,
        name: document.getElementById('integration_name').value,
        url: document.getElementById('integration_api_url').value,
        method: document.getElementById('integration_method').value,
        response_path: document.getElementById('integration_response_data_path').value,
        bearer_token: document.getElementById('integration_bearer_token').value
    };

    fetch('/employee-integrations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
    })
    .then(async r => {
        const data = await r.json();
        if (!r.ok) {
            const errorMsg = data.errors ? Object.values(data.errors).flat().join(', ') : (data.message || 'Validation failed');
            throw new Error(errorMsg);
        }
        return data;
    })
    .then(data => {
        const syncId = data.id || integrationId;
        if (syncId) {
            return fetch(`/employee-integrations/${syncId}/sync`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
        } else {
            throw new Error('Could not save integration config');
        }
    })
    .then(async r => {
        const syncRes = await r.json();
        if (!r.ok || syncRes.status !== 'success') {
            throw new Error(syncRes.error || syncRes.message || 'Sync failed on remote server.');
        }
        const message = Number.isInteger(syncRes.employee_count) ? `${syncRes.employee_count} employees synchronized successfully.` : 'Employees synchronized successfully!';
        if (window.showToast) {
            window.showToast(message, 'success');
        } else {
            alert(message);
        }
        closeModal('apiSetupModal');
        setTimeout(() => { window.location.reload(); }, 600);
    })
    .catch(err => {
        alert('Sync Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<ion-icon name="sync-outline"></ion-icon> <span>Sync Now</span>';
    });
}
</script>
@endsection
