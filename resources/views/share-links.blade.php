@extends('layouts.app')
@section('title', 'Share Links')

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Share Links</h1>
        <p class="subtitle">Securely share read-only project views with Clients and Partners.</p>
    </div>
    <button class="btn btn-primary btn-pill mobile-hide" onclick="openModal('createShareLinkModal')">
        <ion-icon name="add-outline"></ion-icon> Generate Link
    </button>
</header>

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
    {{ session('success') }}
    @if(session('generated_link'))
        <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" readonly value="{{ session('generated_link') }}" class="form-control" style="max-width: 400px; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.9rem; border-color: #bbf7d0;">
            <button type="button" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.9rem; border-color: #166534; color: #166534;" onclick="navigator.clipboard.writeText('{{ session('generated_link') }}'); alert('Copied to clipboard!')">Copy Link</button>
        </div>
    @endif
</div>
@endif

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Shared Record</th>
                <th>Audience</th>
                <th>Status</th>
                <th>Expires On</th>
                <th>Views</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shareLinks as $link)
            <tr>
                <td data-label="Shared Record">
                    <span class="font-medium">{{ ucfirst($link->shareable_type) }}</span><br>
                    <span class="text-muted" style="font-size: 0.85rem;">{{ $link->shareable_name }}</span>
                </td>
                <td data-label="Audience">
                    <span class="badge" style="background:#f1f5f9;color:#475569;">{{ ucfirst($link->audience) }}</span>
                </td>
                <td data-label="Status">
                    @if($link->status === 'active')
                        <span class="badge" style="background:#dcfce7;color:#166534;">Active</span>
                    @elseif($link->status === 'revoked')
                        <span class="badge" style="background:#fee2e2;color:#991b1b;">Revoked</span>
                    @else
                        <span class="badge" style="background:#fef3c7;color:#92400e;">Expired</span>
                    @endif
                </td>
                <td data-label="Expires On">
                    {{ $link->expires_at ? \Carbon\Carbon::parse($link->expires_at)->format('Y-m-d') : 'Never' }}
                </td>
                <td data-label="Views">
                    <span class="font-medium">{{ $link->visit_count }}</span>
                    @if($link->last_viewed)
                    <br><span class="text-muted" style="font-size: 0.8rem;">Last: {{ \Carbon\Carbon::parse($link->last_viewed)->diffForHumans() }}</span>
                    @endif
                </td>
                <td data-label="Actions">
                    <div class="actions">
                        @if($link->status === 'active')
                        <button class="action-btn" title="Copy Link" onclick="copyToClipboard('{{ url('/share/'.$link->token) }}')">
                            <ion-icon name="copy-outline"></ion-icon>
                        </button>
                        <form action="/share-links/{{ $link->id }}/revoke" method="POST" style="display:inline;" onsubmit="return confirm('Revoke this link?');">
                            @csrf
                            <button type="submit" class="action-btn" title="Revoke"><ion-icon name="close-circle-outline"></ion-icon></button>
                        </form>
                        @endif
                        <form action="/share-links/{{ $link->id }}/regenerate" method="POST" style="display:inline;" onsubmit="return confirm('Regenerate this link? The old one will break.');">
                            @csrf
                            <button type="submit" class="action-btn" title="Regenerate"><ion-icon name="refresh-outline"></ion-icon></button>
                        </form>
                        <a href="{{ url('/share/'.$link->token) }}" target="_blank" class="action-btn" title="Open Link"><ion-icon name="open-outline"></ion-icon></a>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($shareLinks->isEmpty())
            <tr><td colspan="6" class="text-center text-muted py-4">No active share links.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection

@section('modals')
<div class="modal-backdrop" id="createShareLinkModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Generate Share Link</h3>
            <button class="btn-close" onclick="closeModal('createShareLinkModal')">&times;</button>
        </div>
        <form action="/share-links" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Shareable Type</label>
                        <select name="shareable_type" id="shareableTypeSelect" class="form-control" onchange="toggleShareableLists()" required>
                            <option value="project">Project</option>
                            <option value="party">Party</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Audience</label>
                        <select name="audience" id="audienceSelect" class="form-control" required>
                            <option value="client">Client</option>
                            <option value="partner">Partner</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;" id="projectSelectGroup">
                    <label class="form-label">Project</label>
                    <select name="shareable_id_project" class="form-control">
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-top: 1.5rem; display:none;" id="partySelectGroup">
                    <label class="form-label">Party</label>
                    <select name="shareable_id_party" class="form-control">
                        @foreach($parties as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->types }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Expires On (Optional)</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ \Carbon\Carbon::now()->addDays(30)->format('Y-m-d') }}">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Password Protection (Optional)</label>
                        <input type="text" name="password" class="form-control" placeholder="Leave empty for open link">
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; color: var(--text-main);">
                        <input type="checkbox" name="allow_downloads" value="1" checked> Allow PDF Downloads
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createShareLinkModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Generate Link</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert("Link copied to clipboard!");
        });
    }

    function toggleShareableLists() {
        const type = document.getElementById('shareableTypeSelect').value;
        const projectGroup = document.getElementById('projectSelectGroup');
        const partyGroup = document.getElementById('partySelectGroup');
        const audienceSelect = document.getElementById('audienceSelect');

        if (type === 'project') {
            projectGroup.style.display = 'block';
            projectGroup.querySelector('select').name = 'shareable_id';
            partyGroup.style.display = 'none';
            partyGroup.querySelector('select').name = 'shareable_id_party';
            audienceSelect.value = 'client';
        } else {
            projectGroup.style.display = 'none';
            projectGroup.querySelector('select').name = 'shareable_id_project';
            partyGroup.style.display = 'block';
            partyGroup.querySelector('select').name = 'shareable_id';
            audienceSelect.value = 'partner';
        }
    }
    // init
    toggleShareableLists();
</script>
@endsection
