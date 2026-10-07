@extends('layouts.app')
@section('title', 'Employees & Salary Management')

@section('content')
<div>
    <!-- Page Header -->
    <header class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <h1 style="font-size:1.75rem; font-weight:700; color:var(--text-heading); margin-bottom:0.25rem;">Employees & Salary Management</h1>
            <p style="color:var(--text-muted); font-size:0.9rem;">Configure employee basic salary, allowances, EPF 8%/12%, ETF 3%, APIT tax, and net payroll.</p>
        </div>
        <div style="display:flex; gap:0.75rem; align-items:center;">
            <a href="/master/salary-components" class="btn btn-outline" style="display:flex; align-items:center; gap:0.5rem; border-radius:8px; padding:0.6rem 1rem; text-decoration:none;">
                <ion-icon name="options-outline"></ion-icon>
                <span>Salary Heads Master</span>
            </a>
            <button onclick="openModal('apiSetupModal')" class="btn btn-primary" style="display:flex; align-items:center; gap:0.5rem; border-radius:8px; padding:0.6rem 1rem;">
                <ion-icon name="sync-outline"></ion-icon>
                <span>Sync HR Employees</span>
            </button>
        </div>
    </header>

    @if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.3);">
        {{ session('success') }}
    </div>
    @endif

    <!-- Top Executive Payroll KPI Tiles -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
        <!-- Active Employees -->
        <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:1.1rem;">
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="people-outline" style="color:var(--primary); font-size:1.1rem;"></ion-icon>
                Active Team
            </div>
            <div class="tabular-nums" style="font-size:1.6rem; font-weight:700; color:var(--text-heading); margin-top:0.4rem;">
                {{ $kpis['total_employees'] ?? $employees->total() }}
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">Headcount</div>
        </div>

        <!-- Total Basic Payroll -->
        <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:1.1rem;">
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="cash-outline" style="color:var(--text-muted); font-size:1.1rem;"></ion-icon>
                Total Basic
            </div>
            <div class="tabular-nums" style="font-size:1.45rem; font-weight:700; color:var(--text-heading); margin-top:0.4rem;">
                {{ number_format($kpis['total_basic_payroll'] ?? 0, 0) }}
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">LKR Monthly</div>
        </div>

        <!-- Total Gross Payroll -->
        <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:1.1rem;">
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="add-circle-outline" style="color:var(--success); font-size:1.1rem;"></ion-icon>
                Gross Payroll
            </div>
            <div class="tabular-nums" style="font-size:1.45rem; font-weight:700; color:var(--text-heading); margin-top:0.4rem;">
                {{ number_format($kpis['total_gross_payroll'] ?? 0, 0) }}
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">Basic + Allowances</div>
        </div>

        <!-- Net Take-Home (Payable) -->
        <div style="background:rgba(16, 185, 129, 0.08); border:1px solid rgba(16, 185, 129, 0.3); border-radius:10px; padding:1.1rem;">
            <div style="font-size:0.75rem; color:var(--success); font-weight:700; text-transform:uppercase; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="wallet-outline" style="color:var(--success); font-size:1.1rem;"></ion-icon>
                Net Take-Home
            </div>
            <div class="tabular-nums" style="font-size:1.55rem; font-weight:800; color:var(--success); margin-top:0.4rem;">
                {{ number_format($kpis['total_net_payable'] ?? 0, 0) }}
            </div>
            <div style="font-size:0.75rem; color:var(--success); margin-top:0.2rem;">After EPF 8% & Tax</div>
        </div>

        <!-- Statutory Employer EPF & ETF (15%) -->
        <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:1.1rem;">
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="shield-checkmark-outline" style="color:var(--primary); font-size:1.1rem;"></ion-icon>
                Employer EPF/ETF
            </div>
            <div class="tabular-nums" style="font-size:1.45rem; font-weight:700; color:var(--primary); margin-top:0.4rem;">
                {{ number_format($kpis['total_employer_epf_etf'] ?? 0, 0) }}
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">15% Statutory Cost</div>
        </div>

        <!-- Total Monthly Cost to Company (CTC) -->
        <div style="background:rgba(139, 92, 246, 0.08); border:1px solid rgba(139, 92, 246, 0.3); border-radius:10px; padding:1.1rem;">
            <div style="font-size:0.75rem; color:var(--primary); font-weight:700; text-transform:uppercase; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="business-outline" style="color:var(--primary); font-size:1.1rem;"></ion-icon>
                Total CTC
            </div>
            <div class="tabular-nums" style="font-size:1.55rem; font-weight:800; color:var(--primary); margin-top:0.4rem;">
                {{ number_format($kpis['total_ctc'] ?? 0, 0) }}
            </div>
            <div style="font-size:0.75rem; color:var(--primary); margin-top:0.2rem;">Total Company Cost</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar" style="background:var(--bg-card); padding:1rem; border-radius:12px; border:1px solid var(--border); margin-bottom:1.5rem;">
        <form action="" method="GET" style="display:flex; flex-wrap:wrap; gap:0.75rem; width:100%;">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search by name, employee code, or role..." style="flex:1; min-width:200px; padding:0.6rem;">
            <select name="status" class="form-control" style="width:160px; min-width:140px; padding:0.6rem;">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.25rem;">Filter</button>
            @if(request('search') || request('status'))
                <a href="/employees" class="btn btn-outline" style="padding:0.6rem 1rem; text-decoration:none;">Reset</a>
            @endif
        </form>
    </div>

    <!-- Employees & Salary Details Table -->
    <div class="data-table-container" style="background:var(--bg-card); border-radius:12px; border:1px solid var(--border); overflow-x:auto;">
        <table class="data-table" style="width:100%; border-collapse:collapse; min-width:1050px;">
            <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
                <tr>
                    <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted); width:240px;">Employee</th>
                    <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted);">Basic Salary</th>
                    <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted);">Allowances (+)</th>
                    <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted);">Gross Salary</th>
                    <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted);">Deductions (-)</th>
                    <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted); background:rgba(16, 185, 129, 0.04);">Net Take-Home</th>
                    <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted);">Employer (15%)</th>
                    <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted); background:rgba(139, 92, 246, 0.04);">Total CTC</th>
                    <th style="padding:1rem; text-align:center; font-weight:600; color:var(--text-muted); width:130px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                @php
                    $breakdown = $emp->salary_breakdown;
                @endphp
                <tr style="border-bottom:1px solid var(--border-light);">
                    <!-- Employee Column -->
                    <td style="padding:0.9rem 1rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <img src="{{ $emp->profile_picture_url ?? 'https://ui-avatars.com/api/?name='.urlencode($emp->full_name).'&background=8b5cf6&color=fff' }}" 
                                 style="width:38px; height:38px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                            <div>
                                <a href="/employees/{{ $emp->id }}" style="font-weight:600; color:var(--text-heading); text-decoration:none;">
                                    {{ $emp->full_name }}
                                </a>
                                <div style="font-size:0.75rem; color:var(--text-muted); display:flex; gap:0.4rem; align-items:center; margin-top:2px;">
                                    <span>{{ $emp->employee_code ?? 'EMP-'.$emp->id }}</span>
                                    <span>•</span>
                                    <span>{{ $emp->job_position ?? 'Staff' }}</span>
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Basic Salary -->
                    <td style="padding:0.9rem 1rem; text-align:right;" class="tabular-nums">
                        <span style="font-weight:600; color:var(--text-heading); font-size:0.95rem;">
                            {{ number_format($breakdown['basic_salary'], 2) }}
                        </span>
                        <div style="font-size:0.7rem; color:var(--text-muted);">{{ $breakdown['currency'] }}</div>
                    </td>

                    <!-- Allowances -->
                    <td style="padding:0.9rem 1rem; text-align:right;" class="tabular-nums">
                        @if($breakdown['total_allowances'] > 0)
                            <span style="font-weight:600; color:var(--success); font-size:0.95rem;">
                                + {{ number_format($breakdown['total_allowances'], 2) }}
                            </span>
                            <div style="font-size:0.7rem; color:var(--text-muted);">
                                {{ count($breakdown['earnings']) }} item(s)
                            </div>
                        @else
                            <span style="color:var(--text-light); font-size:0.85rem;">-</span>
                        @endif
                    </td>

                    <!-- Gross Salary -->
                    <td style="padding:0.9rem 1rem; text-align:right;" class="tabular-nums">
                        <span style="font-weight:700; color:var(--text-heading); font-size:0.95rem;">
                            {{ number_format($breakdown['gross_salary'], 2) }}
                        </span>
                        <div style="font-size:0.7rem; color:var(--text-muted);" title="EPF Base Earnings">
                            EPF: {{ number_format($breakdown['epf_base_salary'], 0) }}
                        </div>
                    </td>

                    <!-- Deductions -->
                    <td style="padding:0.9rem 1rem; text-align:right;" class="tabular-nums">
                        @if($breakdown['total_deductions'] > 0)
                            <span style="font-weight:600; color:var(--danger); font-size:0.95rem;">
                                - {{ number_format($breakdown['total_deductions'], 2) }}
                            </span>
                            <div style="font-size:0.7rem; color:var(--text-muted);">
                                EPF 8%: {{ number_format($breakdown['employee_epf'], 0) }}
                                @if($breakdown['apit_tax'] > 0)
                                    | Tax: {{ number_format($breakdown['apit_tax'], 0) }}
                                @endif
                            </div>
                        @else
                            <span style="color:var(--text-light); font-size:0.85rem;">0.00</span>
                        @endif
                    </td>

                    <!-- Net Take-Home Pay (Highlight) -->
                    <td style="padding:0.9rem 1rem; text-align:right; background:rgba(16, 185, 129, 0.04);" class="tabular-nums">
                        <span class="badge badge-success" style="font-size:0.95rem; font-weight:700; padding:0.35rem 0.65rem; border-radius:6px;">
                            {{ number_format($breakdown['net_salary'], 2) }}
                        </span>
                        <div style="font-size:0.7rem; color:var(--success); margin-top:2px;">Disbursable</div>
                    </td>

                    <!-- Employer EPF & ETF (15%) -->
                    <td style="padding:0.9rem 1rem; text-align:right;" class="tabular-nums">
                        @if($breakdown['total_employer_cost'] > 0)
                            <span style="font-weight:600; color:var(--primary); font-size:0.95rem;">
                                {{ number_format($breakdown['total_employer_cost'], 2) }}
                            </span>
                            <div style="font-size:0.7rem; color:var(--text-muted);">
                                EPF 12%: {{ number_format($breakdown['employer_epf'], 0) }} | ETF 3%: {{ number_format($breakdown['employer_etf'], 0) }}
                            </div>
                        @else
                            <span style="color:var(--text-light); font-size:0.85rem;">0.00</span>
                        @endif
                    </td>

                    <!-- Total CTC (Cost to Company) -->
                    <td style="padding:0.9rem 1rem; text-align:right; background:rgba(139, 92, 246, 0.04);" class="tabular-nums">
                        <span style="font-weight:800; color:var(--primary); font-size:1rem;">
                            {{ number_format($breakdown['ctc'], 2) }}
                        </span>
                        <div style="font-size:0.7rem; color:var(--text-muted);">Gross + 15% ER</div>
                    </td>

                    <!-- Actions -->
                    <td style="padding:0.9rem 1rem; text-align:center;">
                        <a href="/employees/{{ $emp->id }}" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.8rem; display:inline-flex; align-items:center; gap:0.35rem; border-radius:6px; text-decoration:none;" title="Configure Salary & Components">
                            <ion-icon name="create-outline"></ion-icon>
                            <span>Config</span>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center; padding:3.5rem; color:var(--text-muted);">
                        <ion-icon name="people-outline" style="font-size:3.5rem; opacity:0.3; margin-bottom:1rem;"></ion-icon><br>
                        <span style="font-size:1.1rem; font-weight:600; color:var(--text-heading);">No employees found</span><br>
                        Click "Sync HR Employees" to pull staff records from your HR system.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <!-- Pagination -->
        @if($employees->total() > 0)
        <div style="display:flex; flex-wrap:wrap; gap:0.75rem; justify-content:space-between; align-items:center; padding:1rem 1.25rem; border-top:1px solid var(--border); font-size:0.85rem; color:var(--text-muted);">
            <div>
                Showing <strong>{{ $employees->firstItem() ?? 0 }}</strong> to <strong>{{ $employees->lastItem() ?? 0 }}</strong> of <strong>{{ $employees->total() }}</strong> employees
            </div>
            @if($employees->hasPages())
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                @if ($employees->onFirstPage())
                    <span class="btn btn-outline" style="opacity:0.5; cursor:not-allowed; padding:0.35rem 0.75rem; font-size:0.85rem;">Previous</span>
                @else
                    <a href="{{ $employees->previousPageUrl() }}" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.85rem; text-decoration:none;">Previous</a>
                @endif

                <span style="padding:0 0.5rem; font-weight:600; color:var(--text-main);">Page {{ $employees->currentPage() }} of {{ $employees->lastPage() }}</span>

                @if ($employees->hasMorePages())
                    <a href="{{ $employees->nextPageUrl() }}" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.85rem; text-decoration:none;">Next</a>
                @else
                    <span class="btn btn-outline" style="opacity:0.5; cursor:not-allowed; padding:0.35rem 0.75rem; font-size:0.85rem;">Next</span>
                @endif
            </div>
            @endif
        </div>
        @endif
    </div>
</div>
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
            <button type="button" class="btn-close" onclick="closeModal('apiSetupModal')">&times;</button>
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

                <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">Provide your HR system API credentials to pull and update employee records.</p>
                
                <input type="hidden" id="integration_id" value="{{ $integration->id ?? '' }}">

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Integration Name *</label>
                    <input type="text" id="integration_name" name="name" class="form-control" value="{{ $integration->name ?? '' }}" placeholder="E.g. Nexus / BambooHR" required>
                </div>
                
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">API URL *</label>
                    <input type="url" id="integration_api_url" name="api_url" class="form-control" value="{{ $integration->url ?? '' }}" placeholder="https://api.hr-system.com/employees" required>
                </div>
                
                <div class="form-row" style="margin-bottom:1rem;">
                    <div class="form-col">
                        <label class="form-label">HTTP Method *</label>
                        <select id="integration_method" name="method" class="form-control">
                            <option value="GET" {{ ($integration->method ?? 'GET') === 'GET' ? 'selected' : '' }}>GET</option>
                            <option value="POST" {{ ($integration->method ?? '') === 'POST' ? 'selected' : '' }}>POST</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Data Path</label>
                        <input type="text" id="integration_response_data_path" name="response_data_path" class="form-control" value="{{ $integration->response_path ?? 'data' }}" placeholder="E.g. data">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Bearer Token</label>
                    <input type="password" id="integration_bearer_token" name="bearer_token" class="form-control" placeholder="{{ isset($integration) && $integration->bearer_token ? '•••••••••••••••• (Leave blank to keep existing token)' : 'Enter API Bearer Token' }}">
                    @if(isset($integration) && $integration->bearer_token)
                        <small style="color:var(--text-muted); font-size:0.75rem;">Existing token is active. Leave blank to keep it.</small>
                    @endif
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:0.75rem; padding:1.25rem 1.5rem; border-top:1px solid var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="closeModal('apiSetupModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSyncSubmit" style="display:inline-flex; align-items:center; gap:0.4rem;">
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

    fetch('/api/api-integrations', {
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
            return fetch(`/api/api-integrations/${syncId}/sync`, {
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
        if (syncRes.status === 'failed') {
            throw new Error(syncRes.error || 'Sync failed on remote server.');
        }
        if (window.showToast) {
            window.showToast('Employees synchronized successfully!', 'success');
        } else {
            alert('Employees synchronized successfully!');
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
