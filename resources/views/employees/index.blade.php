@extends('layouts.app')
@section('title', 'Employees')

@section('content')
<div>
    <header class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <div>
            <h1 style="font-size:1.75rem; font-weight:700; color:var(--text-heading); margin-bottom:0.25rem;">Employees</h1>
            <p style="color:var(--text-muted); font-size:0.9rem;">Manage team members and synchronize data from HR systems.</p>
        </div>
        <div>
            <button onclick="openModal('apiSetupModal')" class="btn btn-primary" style="display:flex; align-items:center; gap:0.5rem; border-radius:8px; padding:0.6rem 1rem;">
                <ion-icon name="sync-outline"></ion-icon>
                <span>Sync Employees</span>
            </button>
        </div>
    </header>

    <div class="filter-bar" style="background:var(--bg-card); padding:1rem; border-radius:12px; border:1px solid var(--border); margin-bottom:1.5rem;">
        <form action="" method="GET" style="display:flex; flex-wrap:wrap; gap:0.75rem; width:100%;">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search by name or code..." style="flex:1; min-width:200px; padding:0.6rem;">
            <select name="status" class="form-control" style="width:160px; min-width:140px; padding:0.6rem;">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="btn btn-outline" style="padding:0.6rem 1.25rem;">Filter</button>
        </form>
    </div>

    <div class="data-table-container" style="background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
        <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
                <tr>
                    <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Employee</th>
                    <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Code</th>
                    <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Job Position</th>
                    <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Status</th>
                    <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Joined At</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                <tr style="border-bottom:1px solid var(--border-light);">
                    <td style="padding:1rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <img src="{{ $emp->profile_picture_url ?? 'https://ui-avatars.com/api/?name='.urlencode($emp->full_name) }}" style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                            <div>
                                <div style="font-weight:500; color:var(--text-heading);">{{ $emp->full_name }}</div>
                                <div style="font-size:0.8rem; color:var(--text-muted);">{{ $emp->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:1rem; color:var(--text-main);">{{ $emp->employee_code ?? '-' }}</td>
                    <td style="padding:1rem; color:var(--text-main);">{{ $emp->job_position ?? '-' }}</td>
                    <td style="padding:1rem;">
                        @if($emp->status === 'active')
                            <span style="background:var(--success-light, #dcfce7); color:var(--success, #166534); padding:0.25rem 0.5rem; border-radius:4px; font-size:0.8rem; font-weight:600;">Active</span>
                        @else
                            <span style="background:#f1f5f9; color:#475569; padding:0.25rem 0.5rem; border-radius:4px; font-size:0.8rem; font-weight:600;">Inactive</span>
                        @endif
                    </td>
                    <td style="padding:1rem; color:var(--text-muted);">{{ $emp->joined_at ? $emp->joined_at->format('M d, Y') : '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">
                        <ion-icon name="people-outline" style="font-size:3rem; opacity:0.5; margin-bottom:1rem;"></ion-icon><br>
                        No employees found. Try syncing from HR system.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($employees->total() > 0)
        <div style="display:flex; flex-wrap:wrap; gap:0.75rem; justify-content:space-between; align-items:center; padding:1rem; border-top:1px solid var(--border); font-size:0.85rem; color:var(--text-muted);">
            <div>
                Showing <strong>{{ $employees->firstItem() ?? 0 }}</strong> to <strong>{{ $employees->lastItem() ?? 0 }}</strong> of <strong>{{ $employees->total() }}</strong> employees
            </div>
            @if($employees->hasPages())
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                {{-- Previous Page Link --}}
                @if ($employees->onFirstPage())
                    <span class="btn btn-outline" style="opacity:0.5; cursor:not-allowed; padding:0.35rem 0.75rem; font-size:0.85rem;">Previous</span>
                @else
                    <a href="{{ $employees->previousPageUrl() }}" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.85rem; text-decoration:none;">Previous</a>
                @endif

                <span style="padding:0 0.5rem; font-weight:600; color:var(--text-main);">Page {{ $employees->currentPage() }} of {{ $employees->lastPage() }}</span>

                {{-- Next Page Link --}}
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
    <div class="modal-card" style="max-width:500px;">
        <div class="modal-header">
            <h2 class="modal-title">HR API Setup</h2>
            <button type="button" class="btn-close" onclick="closeModal('apiSetupModal')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <form id="apiSetupForm" onsubmit="handleApiSetup(event)">
            @csrf
            <div class="modal-body" style="padding:1.5rem;">
                <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">Provide your HR system API details below to trigger an employee synchronization.</p>
                
                <div class="form-group">
                    <label class="form-label">Integration Name</label>
                    <input type="text" id="integration_name" name="name" class="form-control" value="{{ $integration->name ?? '' }}" placeholder="E.g. BambooHR" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">API URL</label>
                    <input type="url" id="integration_api_url" name="api_url" class="form-control" value="{{ $integration->api_url ?? '' }}" placeholder="https://api.hr-system.com/employees" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">HTTP Method</label>
                        <select id="integration_method" name="method" class="form-control">
                            <option value="GET" {{ ($integration->method ?? 'GET') === 'GET' ? 'selected' : '' }}>GET</option>
                            <option value="POST" {{ ($integration->method ?? '') === 'POST' ? 'selected' : '' }}>POST</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Data Path</label>
                        <input type="text" id="integration_response_data_path" name="response_data_path" class="form-control" value="{{ $integration->response_data_path ?? 'data' }}" placeholder="E.g. data">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Bearer Token</label>
                    <input type="password" id="integration_bearer_token" name="bearer_token" class="form-control" placeholder="API Token">
                </div>
                
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('apiSetupModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSyncSubmit">Sync</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function handleApiSetup(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSyncSubmit');
    btn.disabled = true;
    btn.innerText = 'Syncing...';
    
    const payload = {
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
        if (data.id) {
            return fetch(`/api/api-integrations/${data.id}/sync`, {
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
        alert('Employees synchronized successfully!');
        closeModal('apiSetupModal');
        window.location.reload();
    })
    .catch(err => {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerText = 'Sync';
    });
}
</script>
@endsection
