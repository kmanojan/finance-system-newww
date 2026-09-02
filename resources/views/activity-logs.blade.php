@extends('layouts.app')
@section('title', 'Audit & Activity Logs')
@section('meta_description', 'Audit trails, system security logs, user actions, and change tracking across the Apptimus Finance System.')

@section('secondary-sidebar')
    @include('operations._sidebar')
@endsection

@section('content')
<style>
.diff-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}
.diff-table th {
    background: var(--bg-page);
    padding: 0.65rem 0.85rem;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
}
.diff-table td {
    padding: 0.65rem 0.85rem;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border-light);
}
.diff-old-val {
    color: #dc2626;
    background: #fef2f2;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.82rem;
    text-decoration: line-through;
}
.diff-new-val {
    color: #059669;
    background: #ecfdf5;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.82rem;
    font-weight: 700;
}
.diff-unchanged-val {
    color: var(--text-muted);
    font-size: 0.82rem;
}
</style>

<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Audit & User Activity Logs</h1>
        <p class="subtitle" style="margin-top:0.3rem;">System-wide audit trail of user actions, data modifications, and security events.</p>
    </div>
</header>

<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:14px; border:1px solid var(--border); box-shadow:0 4px 20px rgba(0,0,0,0.02);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.78rem; text-transform:uppercase; color:var(--text-muted);">Timestamp</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.78rem; text-transform:uppercase; color:var(--text-muted);">Action</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.78rem; text-transform:uppercase; color:var(--text-muted);">Model / Entity</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.78rem; text-transform:uppercase; color:var(--text-muted);">User ID</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.78rem; text-transform:uppercase; color:var(--text-muted);">Changes Diff</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            @php
                $badgeStyle = 'background:#dbeafe; color:#1e40af;'; // Default create / info
                if (str_contains(strtolower($log->action), 'update')) $badgeStyle = 'background:#fef3c7; color:#92400e;';
                elseif (str_contains(strtolower($log->action), 'delete')) $badgeStyle = 'background:#fee2e2; color:#991b1b;';
            @endphp
            <tr style="border-bottom: 1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; font-size:0.85rem; color:var(--text-muted); font-weight:600;">{{ $log->created_at }}</td>
                <td style="padding:0.85rem 1rem;">
                    <span style="font-size:0.75rem; font-weight:700; padding:0.2rem 0.5rem; border-radius:6px; {{ $badgeStyle }}">
                        {{ $log->action }}
                    </span>
                </td>
                <td style="padding:0.85rem 1rem; font-weight:700; color:var(--text-heading);">
                    {{ $log->model_type ?? 'System' }} {{ $log->model_id ? '#' . $log->model_id : '' }}
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <span class="badge" style="background:var(--bg-page); color:var(--text-heading); border:1px solid var(--border);">User #{{ $log->user_id ?? 1 }}</span>
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    @if($log->new_value || $log->old_value)
                        <button type="button" class="btn btn-primary-gradient" style="padding:0.25rem 0.65rem; font-size:0.78rem; border-radius:6px;" onclick='viewDiff(@json($log))'>
                            View Changes
                        </button>
                    @else
                        <span class="text-muted" style="font-size:0.8rem;">-</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @if($logs->isEmpty())
            <tr><td colspan="5" class="text-center text-muted py-4" style="padding:3rem; text-align:center;">No audit logs recorded yet.</td></tr>
            @endif
        </tbody>
    </table>
    <div style="padding: 1rem;">
        {{ $logs->links() }}
    </div>
</div>

<!-- Human Readable Audit Log Diff Modal -->
<div class="modal-backdrop" id="diffModal">
    <div class="modal-card" style="max-width: 720px; width: 92vw; border-radius: 18px; box-shadow: 0 25px 50px rgba(0,0,0,0.25);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
            <div>
                <h3 class="modal-title" id="diffModalTitle" style="font-size: 1.25rem; font-weight: 800; color: var(--text-heading); margin: 0;">Audit Log Change Details</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.2rem 0 0 0;" id="diffModalSubtitle">Visual comparison of changed attributes</p>
            </div>
            <button type="button" class="btn-close" onclick="closeModal('diffModal')">&times;</button>
        </div>

        <div class="modal-body" style="padding: 1.25rem 0;" id="diffModalBody">
            <!-- Dynamically populated by JS -->
        </div>

        <div class="modal-footer" style="border-top: 1px solid var(--border); padding-top: 0.85rem; display:flex; justify-content:flex-end;">
            <button type="button" class="btn btn-primary" onclick="closeModal('diffModal')">Close</button>
        </div>
    </div>
</div>

<script>
function formatFieldName(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function parseLogJson(val) {
    if (!val) return null;
    if (typeof val === 'object') return val;
    try {
        return JSON.parse(val);
    } catch (e) {
        return val;
    }
}

function viewDiff(log) {
    document.getElementById('diffModalTitle').innerText = log.action;
    document.getElementById('diffModalSubtitle').innerText = 'Entity: ' + (log.model_type || 'System') + (log.model_id ? ' #' + log.model_id : '') + ' | Logged on ' + log.created_at;

    const oldData = parseLogJson(log.old_value) || {};
    const newData = parseLogJson(log.new_value) || {};

    let html = '';

    const actionLower = (log.action || '').toLowerCase();

    if (actionLower.includes('create')) {
        // Render Created Record Table
        html += `
            <div style="background:var(--bg-page); border:1px solid var(--border); border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.85rem; color:#059669; font-weight:700;">
                ✨ New Record Created with the following initial attributes:
            </div>
            <div style="max-height: 420px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px;">
                <table class="diff-table">
                    <thead>
                        <tr>
                            <th style="width:40%;">Attribute Name</th>
                            <th>Created Value</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        const keys = Object.keys(newData);
        if (keys.length === 0) {
            html += `<tr><td colspan="2" style="text-align:center; color:var(--text-muted);">No payload data captured.</td></tr>`;
        } else {
            keys.forEach(k => {
                if (k === '_token' || k === '_method') return;
                let val = newData[k];
                if (val === null || val === '') val = '<span class="text-muted">None</span>';
                else if (typeof val === 'object') val = JSON.stringify(val);

                html += `
                    <tr>
                        <td style="font-weight:700; color:var(--text-heading);">${formatFieldName(k)}</td>
                        <td><span class="diff-new-val">${val}</span></td>
                    </tr>
                `;
            });
        }

        html += `</tbody></table></div>`;

    } else if (actionLower.includes('delete')) {
        // Render Deleted Record Table
        html += `
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.85rem; color:#dc2626; font-weight:700;">
                🗑️ Record Removed with the following final state:
            </div>
            <div style="max-height: 420px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px;">
                <table class="diff-table">
                    <thead>
                        <tr>
                            <th style="width:40%;">Attribute Name</th>
                            <th>Deleted Value</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        const dataObj = Object.keys(oldData).length > 0 ? oldData : newData;
        const keys = Object.keys(dataObj);
        if (keys.length === 0) {
            html += `<tr><td colspan="2" style="text-align:center; color:var(--text-muted);">No payload data captured.</td></tr>`;
        } else {
            keys.forEach(k => {
                if (k === '_token' || k === '_method') return;
                let val = dataObj[k];
                if (val === null || val === '') val = '<span class="text-muted">None</span>';
                else if (typeof val === 'object') val = JSON.stringify(val);

                html += `
                    <tr>
                        <td style="font-weight:700; color:var(--text-heading);">${formatFieldName(k)}</td>
                        <td><span class="diff-old-val">${val}</span></td>
                    </tr>
                `;
            });
        }

        html += `</tbody></table></div>`;

    } else {
        // Render Updated / Modification Side-by-Side Diff Table (ONLY CHANGED FIELDS)
        html += `
            <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.85rem; color:#92400e; font-weight:700;">
                📝 Modified Fields Only (Previous vs Updated Values):
            </div>
            <div style="max-height: 420px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px;">
                <table class="diff-table">
                    <thead>
                        <tr>
                            <th style="width:30%;">Attribute Name</th>
                            <th style="width:35%;">Previous Value (Old)</th>
                            <th style="width:35%;">Updated Value (New)</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        const allKeys = Array.from(new Set([...Object.keys(oldData), ...Object.keys(newData)]));
        let changesFound = false;

        allKeys.forEach(k => {
            if (k === '_token' || k === '_method' || k === 'id' || k === 'updated_at' || k === 'created_at' || k === 'deleted_at') return;


            let oldVal = oldData[k];
            let newVal = newData[k];

            if (typeof oldVal === 'object' && oldVal !== null) oldVal = JSON.stringify(oldVal);
            if (typeof newVal === 'object' && newVal !== null) newVal = JSON.stringify(newVal);

            const isDifferent = String(oldVal ?? '') !== String(newVal ?? '');

            // Only display row if field value actually changed
            if (isDifferent) {
                changesFound = true;
                const oldStr = (oldVal === null || oldVal === undefined || oldVal === '') ? '<span class="text-muted">None</span>' : oldVal;
                const newStr = (newVal === null || newVal === undefined || newVal === '') ? '<span class="text-muted">None</span>' : newVal;

                html += `
                    <tr style="background:rgba(254, 243, 199, 0.25);">
                        <td style="font-weight:700; color:var(--text-heading);">
                            ${formatFieldName(k)}
                            <span style="font-size:0.65rem; background:#fbbf24; color:#78350f; font-weight:800; padding:0.1rem 0.35rem; border-radius:4px; margin-left:0.3rem;">CHANGED</span>
                        </td>
                        <td>
                            <span class="diff-old-val">${oldStr}</span>
                        </td>
                        <td>
                            <span class="diff-new-val">${newStr}</span>
                        </td>
                    </tr>
                `;
            }
        });

        if (!changesFound) {
            html += `<tr><td colspan="3" style="text-align:center; padding:2rem; color:var(--text-muted);">No modified fields detected for this update.</td></tr>`;
        }

        html += `</tbody></table></div>`;
    }


    document.getElementById('diffModalBody').innerHTML = html;
    openModal('diffModal');
}
</script>
@endsection
