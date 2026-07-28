@extends('layouts.app')
@section('title', 'Audit & Activity Logs')

@section('secondary-sidebar')
    @include('operations._sidebar')
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Audit & User Activity Logs</h1>
        <p class="subtitle" style="margin-top:0.3rem;">System-wide audit trail of user actions, data modifications, and security events.</p>
    </div>
</header>

<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Timestamp</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Action</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Model / Entity</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">User ID</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Changes Diff</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr style="border-bottom: 1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; font-size:0.85rem; color:var(--text-muted);">{{ $log->created_at }}</td>
                <td style="padding:0.85rem 1rem; font-weight:700; color:var(--primary);">{{ $log->action }}</td>
                <td style="padding:0.85rem 1rem; font-weight:600;">{{ $log->model_type ?? 'System' }} {{ $log->model_id ? '#' . $log->model_id : '' }}</td>
                <td style="padding:0.85rem 1rem; text-align:center;"><span class="badge" style="background:var(--primary-light); color:var(--primary);">User #{{ $log->user_id ?? 1 }}</span></td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    @if($log->new_value || $log->old_value)
                        <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:5px;" onclick="viewDiff({{ json_encode($log) }})">View Diff</button>
                    @else
                        <span class="text-muted" style="font-size:0.8rem;">-</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @if($logs->isEmpty())
            <tr><td colspan="5" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">No audit logs recorded yet.</td></tr>
            @endif
        </tbody>
    </table>
    <div style="padding: 1rem;">
        {{ $logs->links() }}
    </div>
</div>

<div class="modal-backdrop" id="diffModal">
    <div class="modal-card" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Audit Log Change Details</h3>
            <button type="button" class="btn-close" onclick="closeModal('diffModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:1rem;">
                <strong>Old Values:</strong>
                <pre id="diffOld" style="background:var(--bg-page); padding:0.75rem; border-radius:8px; font-size:0.8rem; overflow:auto;"></pre>
            </div>
            <div>
                <strong>New Values:</strong>
                <pre id="diffNew" style="background:var(--bg-page); padding:0.75rem; border-radius:8px; font-size:0.8rem; overflow:auto;"></pre>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('diffModal')">Close</button>
        </div>
    </div>
</div>

<script>
    function viewDiff(log) {
        document.getElementById('diffOld').innerText = log.old_value ? JSON.stringify(JSON.parse(log.old_value), null, 2) : 'None';
        document.getElementById('diffNew').innerText = log.new_value ? JSON.stringify(JSON.parse(log.new_value), null, 2) : 'None';
        openModal('diffModal');
    }
</script>
@endsection
