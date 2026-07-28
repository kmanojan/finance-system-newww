@extends('layouts.app')
@section('title', 'Reminders & Alert Engine')

@section('secondary-sidebar')
    @include('operations._sidebar')
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Reminders & Alert Engine</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Unified notification center for cheques, recurring invoices, loan interest, budget threshold alerts, and custom reminders.</p>
    </div>
    <button class="btn btn-primary-gradient btn-pill" onclick="openModal('createReminderModal')">
        <ion-icon name="add-outline" style="vertical-align:middle;"></ion-icon> Add Custom Reminder
    </button>
</header>

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #86efac;">
    {{ session('success') }}
</div>
@endif

<!-- View Controls & Filters -->
<div class="toolbar" style="margin-bottom: 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div class="toolbar-left" style="display:flex; gap:0.5rem;">
        <a href="/reminders?view=list&type={{ $typeFilter }}&status={{ $statusFilter }}" class="btn {{ $viewMode === 'list' ? 'btn-primary' : 'btn-outline' }}" style="font-size:0.85rem; padding:0.4rem 0.85rem; border-radius:8px;">
            <ion-icon name="list-outline" style="vertical-align:middle;"></ion-icon> List View
        </a>
        <a href="/reminders?view=calendar&type={{ $typeFilter }}&status={{ $statusFilter }}" class="btn {{ $viewMode === 'calendar' ? 'btn-primary' : 'btn-outline' }}" style="font-size:0.85rem; padding:0.4rem 0.85rem; border-radius:8px;">
            <ion-icon name="calendar-outline" style="vertical-align:middle;"></ion-icon> Calendar View
        </a>
    </div>

    <div class="toolbar-right" style="display:flex; gap:0.75rem;">
        <select class="form-control" style="font-size:0.85rem; padding:0.4rem 0.75rem; border-radius:8px;" onchange="location.href='/reminders?view={{ $viewMode }}&status={{ $statusFilter }}&type=' + this.value">
            <option value="all" {{ $typeFilter === 'all' || !$typeFilter ? 'selected' : '' }}>All Reminder Types</option>
            <option value="cheque" {{ $typeFilter === 'cheque' ? 'selected' : '' }}>Cheque Deposits</option>
            <option value="loan" {{ $typeFilter === 'loan' ? 'selected' : '' }}>Loan Interest Due</option>
            <option value="invoice_schedule" {{ $typeFilter === 'invoice_schedule' ? 'selected' : '' }}>Recurring Invoices</option>
            <option value="custom" {{ $typeFilter === 'custom' ? 'selected' : '' }}>Custom Reminders</option>
        </select>
    </div>
</div>

@if($viewMode === 'calendar')
<div class="card" style="padding: 1.5rem; background:var(--bg-card); border-radius:14px; border:1px solid var(--border);">
    <h3 style="font-size:1.1rem; font-weight:700; color:var(--text-heading); margin-bottom:1rem;">Monthly Calendar Plot</h3>
    <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:0.5rem; text-align:center;">
        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
            <div style="font-weight:700; font-size:0.8rem; color:var(--text-muted); padding:0.5rem; background:var(--bg-page); border-radius:6px;">{{ $day }}</div>
        @endforeach
        @for($d=1; $d<=31; $d++)
            @php
                $currentDate = date('Y-m-') . sprintf('%02d', $d);
                $dayReminders = $allReminders->where('due_date', $currentDate);
            @endphp
            <div style="min-height: 80px; padding:0.4rem; border:1px solid var(--border-light); border-radius:8px; background: {{ $dayReminders->count() > 0 ? 'var(--primary-light)' : 'transparent' }}; text-align:left;">
                <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted);">{{ $d }}</div>
                @foreach($dayReminders as $r)
                    <div style="font-size:0.68rem; font-weight:600; color:var(--primary); background:var(--bg-card); border-radius:4px; padding:0.15rem 0.3rem; margin-top:0.2rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $r->title }}">
                        ● {{ $r->title }}
                    </div>
                @endforeach
            </div>
        @endfor
    </div>
</div>
@else
<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Type</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Reminder Title</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Due Date</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Amount / Context</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Status</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($allReminders as $item)
            @php
                $isOverdue = strtotime($item->due_date) < strtotime(date('Y-m-d'));
            @endphp
            <tr style="border-bottom: 1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; text-align:left;">
                    <span class="badge" style="background:var(--primary-light); color:var(--primary); font-size:0.75rem; font-weight:700; padding:0.2rem 0.5rem; border-radius:5px; text-transform:uppercase;">
                        {{ str_replace('_', ' ', $item->type) }}
                    </span>
                </td>
                <td style="padding:0.85rem 1rem; text-align:left; font-weight:600; color:var(--text-heading);">
                    {{ $item->title }}
                    @if(isset($item->is_system) && $item->is_system && $item->link)
                        <a href="{{ $item->link }}" style="font-size:0.75rem; color:var(--primary); margin-left:0.4rem; text-decoration:none;">View Source &rarr;</a>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:center; font-weight:600; color:{{ $isOverdue ? 'var(--danger)' : 'var(--text-heading)' }};">
                    {{ $item->due_date }}
                    @if($isOverdue)
                        <span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:0.65rem; padding:0.1rem 0.3rem; border-radius:3px; margin-left:0.2rem;">OVERDUE</span>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:700; color:var(--primary);">
                    {{ $item->amount_formatted ?? '-' }}
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <span class="badge" style="background:{{ $item->status === 'settled' ? 'var(--success-light)' : 'var(--primary-light)' }}; color:{{ $item->status === 'settled' ? 'var(--success)' : 'var(--primary)' }}; font-size:0.75rem; font-weight:600; padding:0.2rem 0.5rem; border-radius:4px;">
                        {{ ucfirst($item->status) }}
                    </span>
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    @if(!isset($item->is_system) || !$item->is_system)
                    <div style="display:flex; justify-content:center; gap:0.4rem;">
                        <form action="/reminders/{{ $item->id }}/status" method="POST" style="display:inline; margin:0;">
                            @csrf
                            <input type="hidden" name="status" value="settled">
                            <button type="submit" class="action-btn" title="Mark Settled" style="color:var(--success);"><ion-icon name="checkmark-circle-outline" style="font-size:1.1rem;"></ion-icon></button>
                        </form>
                        <form action="/reminders/{{ $item->id }}/status" method="POST" style="display:inline; margin:0;">
                            @csrf
                            <input type="hidden" name="status" value="snoozed">
                            <button type="submit" class="action-btn" title="Snooze Reminder" style="color:#d97706;"><ion-icon name="time-outline" style="font-size:1.1rem;"></ion-icon></button>
                        </form>
                        <form action="/reminders/{{ $item->id }}" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Delete reminder?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete" style="color:var(--danger);"><ion-icon name="trash-outline" style="font-size:1.1rem;"></ion-icon></button>
                        </form>
                    </div>
                    @else
                        <span class="text-muted" style="font-size:0.75rem;">Auto-Managed</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @if($allReminders->isEmpty())
            <tr>
                <td colspan="6" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">
                    <ion-icon name="notifications-off-outline" style="font-size:2.5rem; opacity:0.4; margin-bottom:0.5rem;"></ion-icon><br>
                    No reminders found for the selected filter.
                </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endif

<!-- Add Reminder Modal -->
<div class="modal-backdrop" id="createReminderModal">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Create Custom Reminder</h3>
            <button type="button" class="btn-close" onclick="closeModal('createReminderModal')">&times;</button>
        </div>
        <form action="/reminders" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Reminder Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Deposit Client Cheque for Project Alpha" required>
                </div>

                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Notify Before (Days)</label>
                        <input type="number" name="notify_before_days" class="form-control" value="2" min="0">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Additional reminder details..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createReminderModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Reminder</button>
            </div>
        </form>
    </div>
</div>
@endsection
