@extends('layouts.app')
@section('title', 'Currencies & Exchange Rates - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Currencies & Exchange Rates</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Manage master currencies, configure system base currency, and track day-by-day exchange rate history.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <form action="/master/currencies/sync-rates" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-outline btn-pill" style="border-color: var(--primary); color: var(--primary);">
                <ion-icon name="sync-outline" style="vertical-align:middle;"></ion-icon> Sync Rates Now
            </button>
        </form>
        <button class="btn btn-primary-gradient btn-pill" onclick="openCreateModal()">
            <ion-icon name="add-outline" style="vertical-align:middle;"></ion-icon> Add New Currency
        </button>
    </div>
</header>

@if(session('error'))
<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; border: 1px solid #fca5a5;">
    {{ session('error') }}
</div>
@endif

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #86efac;">
    {{ session('success') }}
</div>
@endif

<!-- Summary Cards -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">System Base Currency</h3>
            <ion-icon name="cash-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $baseCurrencyCode }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Main reference currency</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Active Currencies</h3>
            <ion-icon name="pricetags-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $data->where('is_active', 1)->count() }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Configured in system</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Rate Sync API</h3>
            <ion-icon name="cloud-download-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.2rem; font-weight:800; margin-top:0.3rem;">Open ER API</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Live free daily conversion</div>
    </div>

    <div class="metric-card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">History Entries</h3>
            <ion-icon name="time-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.6rem; font-weight:800; margin-top:0.3rem;">{{ $history->count() }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Rate log snapshots</div>
    </div>
</div>

<!-- Currencies Table -->
<div class="card" style="padding:0; overflow:visible; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Currency Code</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Currency Name</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Symbol</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Rate in {{ $baseCurrencyCode }} (1 Foreign = X {{ $baseCurrencyCode }})</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Status</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            @php
                $rateObj = $latestRates->get($item->code);
            @endphp
            <tr style="border-bottom: 1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; text-align:left;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span class="badge" style="background:var(--primary-light); color:var(--primary); font-weight:800; font-size:0.85rem; padding:0.25rem 0.6rem; border-radius:6px;">
                            {{ $item->code }}
                        </span>
                        @if($item->is_base)
                            <span class="badge" style="background:rgba(16, 185, 129, 0.15); color:var(--success); font-size:0.7rem; font-weight:700; padding:0.15rem 0.4rem; border-radius:4px;">
                                BASE CURRENCY
                            </span>
                        @endif
                    </div>
                </td>
                <td style="padding:0.85rem 1rem; text-align:left; font-weight:600; color:var(--text-heading);">
                    {{ $item->name }}
                </td>
                <td style="padding:0.85rem 1rem; text-align:center; font-weight:700; color:var(--primary); font-size:1.05rem;">
                    {{ $item->symbol }}
                </td>
                <td style="padding:0.85rem 1rem; text-align:right;">
                    @if($item->code === $baseCurrencyCode)
                        <span style="font-weight:700; color:var(--success);">1 {{ $item->code }} = 1.00 {{ $baseCurrencyCode }}</span>
                    @elseif($rateObj)
                        @php
                            $invRate = $rateObj->rate > 0 ? (1 / $rateObj->rate) : 0;
                        @endphp
                        <span style="font-weight:700; color:var(--text-heading); font-size:0.9rem;">
                            1 {{ $item->code }} (1{{ $item->symbol }}) = {{ number_format($invRate, 2) }} {{ $baseCurrencyCode }}
                        </span>
                        <div style="font-size:0.75rem; color:var(--text-muted);">
                            Synced: {{ $rateObj->rate_date }}
                        </div>
                    @else
                        <span class="text-muted" style="font-size:0.8rem;">Not synced yet</span>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <span class="badge" style="background:{{ $item->is_active ? 'rgba(16, 185, 129, 0.12)' : '#f1f5f9' }}; color:{{ $item->is_active ? 'var(--success)' : '#475569' }}; font-size:0.75rem; padding:0.25rem 0.5rem; border-radius:4px; font-weight:600;">
                        {{ $item->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <div style="display:flex; justify-content:center; align-items:center; gap:0.4rem;">
                        <button type="button" class="action-btn" title="View Rate History" onclick="openHistoryModal('{{ $item->code }}')" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.5rem; cursor:pointer; color:var(--primary);">
                            <ion-icon name="time-outline" style="font-size:0.95rem;"></ion-icon>
                        </button>
                        <button type="button" class="action-btn" title="Edit Currency" onclick="openEditModal({{ json_encode($item) }})" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.5rem; cursor:pointer; color:var(--text-heading);">
                            <ion-icon name="create-outline" style="font-size:0.95rem;"></ion-icon>
                        </button>
                        @if(!$item->is_base)
                        <form action="/master/currencies/{{ $item->id }}" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Delete currency {{ $item->code }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete Currency" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.5rem; cursor:pointer; color:var(--danger);">
                                <ion-icon name="trash-outline" style="font-size:0.95rem;"></ion-icon>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
            @if($data->isEmpty())
            <tr>
                <td colspan="6" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">
                    <ion-icon name="cash-outline" style="font-size:2.5rem; opacity:0.4; margin-bottom:0.5rem;"></ion-icon><br>
                    No currencies configured.
                </td>
            </tr>
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
        document.getElementById('editForm').action = '/master/currencies/' + item.id;
        if (document.getElementById('edit_code')) document.getElementById('edit_code').value = item.code || '';
        if (document.getElementById('edit_name')) document.getElementById('edit_name').value = item.name || '';
        if (document.getElementById('edit_symbol')) document.getElementById('edit_symbol').value = item.symbol || '';
        if (document.getElementById('edit_is_active')) document.getElementById('edit_is_active').checked = item.is_active == 1;
        if (document.getElementById('edit_is_base')) document.getElementById('edit_is_base').checked = item.is_base == 1;
        openModal('editModal');
    }

    function openHistoryModal(code) {
        document.getElementById('historyModalTitle').innerText = 'Exchange Rate History: ' + code;
        const tbody = document.getElementById('historyTbody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">Loading history...</td></tr>';
        openModal('historyModal');

        fetch('/master/currencies/' + code + '/history')
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success' && res.data && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(h => {
                        let rawRate = parseFloat(h.rate);
                        let invRate = rawRate > 0 ? (1 / rawRate).toFixed(2) : '0.00';
                        html += `<tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding:0.6rem 0.8rem; font-size:0.85rem;">${h.rate_date}</td>
                            <td style="padding:0.6rem 0.8rem; font-size:0.85rem; font-weight:600; text-align:right;">1 ${h.target_currency} = ${invRate} ${h.base_currency}</td>
                            <td style="padding:0.6rem 0.8rem; font-size:0.8rem; text-align:center;"><span class="badge" style="background:var(--primary-light); color:var(--primary);">${h.source || 'api'}</span></td>
                            <td style="padding:0.6rem 0.8rem; font-size:0.8rem; color:var(--text-muted); text-align:right;">${h.created_at || ''}</td>
                        </tr>`;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No historical conversion rates recorded yet for ' + code + '. Click "Sync Rates Now" to pull latest.</td></tr>';
                }
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Failed to load history logs.</td></tr>';
            });
    }
</script>
@endsection

@section('modals')
<!-- Create Currency Modal -->
<div class="modal-backdrop" id="createModal">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Add New Master Currency</h3>
            <button type="button" class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form action="/master/currencies" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Currency Code (3 letters) *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. AUD" maxlength="3" required style="text-transform: uppercase;">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Symbol *</label>
                        <input type="text" name="symbol" class="form-control" placeholder="e.g. A$" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label">Currency Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Australian Dollar" required>
                </div>

                <div class="form-group" style="margin-top: 1.25rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                        <input type="checkbox" name="is_active" value="1" checked style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Active Status
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Currency</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Currency Modal -->
<div class="modal-backdrop" id="editModal">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Currency</h3>
            <button type="button" class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Currency Code</label>
                        <input type="text" id="edit_code" class="form-control" disabled style="background:var(--bg-page);">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Symbol *</label>
                        <input type="text" name="symbol" id="edit_symbol" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label">Currency Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>

                <div class="form-group" style="margin-top: 1.25rem; display:flex; flex-direction:column; gap:0.75rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Active Status
                    </label>

                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                        <input type="checkbox" name="is_base" id="edit_is_base" value="1" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Set as System Base Currency
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Currency</button>
            </div>
        </form>
    </div>
</div>

<!-- Rate History Modal -->
<div class="modal-backdrop" id="historyModal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="historyModalTitle">Exchange Rate History</h3>
            <button type="button" class="btn-close" onclick="closeModal('historyModal')">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 400px; overflow-y: auto; padding:0;">
            <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
                <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
                    <tr>
                        <th style="padding:0.75rem 0.8rem; text-align:left; font-size:0.78rem; color:var(--text-muted);">Rate Date</th>
                        <th style="padding:0.75rem 0.8rem; text-align:right; font-size:0.78rem; color:var(--text-muted);">Conversion Rate</th>
                        <th style="padding:0.75rem 0.8rem; text-align:center; font-size:0.78rem; color:var(--text-muted);">Source</th>
                        <th style="padding:0.75rem 0.8rem; text-align:right; font-size:0.78rem; color:var(--text-muted);">Logged At</th>
                    </tr>
                </thead>
                <tbody id="historyTbody">
                    <tr><td colspan="4" class="text-center py-4">Loading history...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('historyModal')">Close</button>
        </div>
    </div>
</div>
@endsection
