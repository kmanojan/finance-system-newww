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
        <a href="/reminders?view=list&month={{ $monthKey }}&type={{ $typeFilter }}&status={{ $statusFilter }}" class="btn {{ $viewMode === 'list' ? 'btn-primary' : 'btn-outline' }}" style="font-size:0.85rem; padding:0.4rem 0.85rem; border-radius:8px;">
            <ion-icon name="list-outline" style="vertical-align:middle;"></ion-icon> List View
        </a>
        <a href="/reminders?view=calendar&month={{ $monthKey }}&type={{ $typeFilter }}&status={{ $statusFilter }}" class="btn {{ $viewMode === 'calendar' ? 'btn-primary' : 'btn-outline' }}" style="font-size:0.85rem; padding:0.4rem 0.85rem; border-radius:8px;">
            <ion-icon name="calendar-outline" style="vertical-align:middle;"></ion-icon> Calendar View
        </a>
    </div>

    <div class="toolbar-right" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <!-- Status Filter -->
        <select class="form-control" style="font-size:0.85rem; padding:0.4rem 0.75rem; border-radius:8px; width:auto;" onchange="location.href='/reminders?view={{ $viewMode }}&month={{ $monthKey }}&type={{ $typeFilter }}&status=' + this.value">
            <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending Reminders</option>
            <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed / Paid</option>
            <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
        </select>

        <!-- Type Filter -->
        <select class="form-control" style="font-size:0.85rem; padding:0.4rem 0.75rem; border-radius:8px; width:auto;" onchange="location.href='/reminders?view={{ $viewMode }}&month={{ $monthKey }}&status={{ $statusFilter }}&type=' + this.value">
            <option value="all" {{ $typeFilter === 'all' || !$typeFilter ? 'selected' : '' }}>All Types (Invoices, Loans, Milestones, Cheques, Custom)</option>
            <option value="invoice" {{ $typeFilter === 'invoice' ? 'selected' : '' }}>Client Invoices</option>
            <option value="loan" {{ $typeFilter === 'loan' ? 'selected' : '' }}>Loan Interest Due</option>
            <option value="milestone" {{ $typeFilter === 'milestone' ? 'selected' : '' }}>Payment Milestones</option>
            <option value="cheque" {{ $typeFilter === 'cheque' ? 'selected' : '' }}>Cheque Deposits</option>
            <option value="custom" {{ $typeFilter === 'custom' ? 'selected' : '' }}>Custom Reminders</option>
        </select>
    </div>
</div>


@if($viewMode === 'calendar')
<style>
.cal-container {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
.cal-grid-equal {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.6rem;
}
.cal-weekday-header {
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    padding: 0.6rem;
    background: var(--bg-page);
    border-radius: 8px;
    text-align: center;
}
.cal-day-equal-box {
    height: 125px;
    min-height: 125px;
    box-sizing: border-box;
    padding: 0.45rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg-card);
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    overflow: hidden;
    position: relative;
    cursor: pointer;
    transition: all 0.15s ease;
}
.cal-day-equal-box:not(.is-empty):hover {
    transform: translateY(-2px);
    border-color: var(--primary);
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.cal-day-equal-box.is-today {
    border: 2px solid var(--primary);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.06) 0%, rgba(99, 102, 241, 0.09) 100%);
}
.cal-day-equal-box.is-empty {
    border: 1px dashed var(--border-light);
    background: var(--bg-alt);
    opacity: 0.3;
    cursor: default;
}
.cal-box-events-scroll {
    margin-top: 0.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    overflow-y: auto;
    max-height: 85px;
}
.cal-box-events-scroll::-webkit-scrollbar {
    width: 3px;
}
.cal-box-events-scroll::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}
.cal-mini-badge {
    font-size: 0.66rem;
    font-weight: 600;
    color: var(--text-heading);
    background: var(--bg-page);
    border: 1px solid var(--border-light);
    border-left: 3.5px solid var(--primary);
    border-radius: 5px;
    padding: 0.2rem 0.35rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    text-decoration: none;
    transition: transform 0.15s ease;
}
.cal-mini-badge:hover {
    transform: translateY(-1px);
    border-color: var(--primary);
}
</style>

<div class="cal-container">
    <!-- Header Navigation -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <div style="display:flex; gap:0.25rem;">
                <a href="/reminders?view=calendar&month={{ $prevMonth }}&type={{ $typeFilter }}&status={{ $statusFilter }}" class="btn btn-outline" style="padding:0.4rem 0.75rem; border-radius:8px; font-size:0.85rem;">
                    <ion-icon name="chevron-back-outline"></ion-icon> Prev
                </a>
                <a href="/reminders?view=calendar&month={{ $nextMonth }}&type={{ $typeFilter }}&status={{ $statusFilter }}" class="btn btn-outline" style="padding:0.4rem 0.75rem; border-radius:8px; font-size:0.85rem;">
                    Next <ion-icon name="chevron-forward-outline"></ion-icon>
                </a>
            </div>
            <h2 style="font-size:1.35rem; font-weight:800; color:var(--text-heading); margin:0;">{{ $monthTitle }}</h2>
        </div>
        
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <a href="/reminders?view=calendar&month={{ date('Y-m') }}&type={{ $typeFilter }}&status={{ $statusFilter }}" class="btn btn-outline" style="font-size:0.85rem; padding:0.4rem 0.85rem; border-radius:8px;">
                Today
            </a>
        </div>
    </div>

    @php
        $daysInMonth = $carbonMonth->daysInMonth;
        $startDayOfWeek = $carbonMonth->copy()->startOfMonth()->dayOfWeek;
    @endphp

    <div class="cal-grid-equal">
        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
            <div class="cal-weekday-header">{{ $day }}</div>
        @endforeach

        <!-- Padding empty cells -->
        @for($pad = 0; $pad < $startDayOfWeek; $pad++)
            <div class="cal-day-equal-box is-empty"></div>
        @endfor

        <!-- Month Days Grid -->
        @for($d=1; $d<=$daysInMonth; $d++)
            @php
                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = ($currentDate === date('Y-m-d'));
                $dayReminders = $allReminders->where('due_date', $currentDate);
                $hasEvents = $dayReminders->count() > 0;
            @endphp
            <div class="cal-day-equal-box {{ $isToday ? 'is-today' : '' }}" 
                 onclick="openDayEventsModal('{{ $currentDate }}', '{{ date('l, F j, Y', strtotime($currentDate)) }}')"
                 title="Click to view activities for {{ date('M j, Y', strtotime($currentDate)) }}">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.85rem; font-weight:800; color:{{ $isToday ? 'var(--primary)' : 'var(--text-heading)' }};">{{ $d }}</span>
                    @if($hasEvents)
                        <span style="font-size:0.62rem; background:var(--primary); color:white; font-weight:800; border-radius:10px; padding:0.1rem 0.4rem;">
                            {{ $dayReminders->count() }}
                        </span>
                    @endif
                </div>

                @if($hasEvents)
                    <div class="cal-box-events-scroll">
                        @foreach($dayReminders as $r)
                            @php
                                $borderColor = '#2563eb'; // blue invoice
                                $icon = 'document-text-outline';
                                
                                if ($r->type === 'loan') {
                                    $borderColor = '#d97706'; // amber loan
                                    $icon = 'cash-outline';
                                } elseif ($r->type === 'milestone') {
                                    $borderColor = '#8b5cf6'; // purple milestone
                                    $icon = 'flag-outline';
                                } elseif ($r->type === 'cheque') {
                                    $borderColor = '#059669'; // emerald cheque
                                    $icon = 'card-outline';
                                } elseif ($r->type === 'custom') {
                                    $borderColor = '#6366f1'; // indigo custom
                                    $icon = 'notifications-outline';
                                }
                            @endphp

                            @if(isset($r->link) && $r->link)
                                <a href="{{ $r->link }}" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" class="cal-mini-badge" style="border-left-color: {{ $borderColor }};" title="{{ $r->title }} - {{ $r->amount_formatted }}">
                                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:62%;">
                                        <ion-icon name="{{ $icon }}" style="vertical-align:middle; color:{{ $borderColor }}; margin-right:0.15rem;"></ion-icon>
                                        {{ $r->title }}
                                    </span>
                                    @if(!empty($r->amount_formatted) && $r->amount_formatted !== '-')
                                        <span style="font-weight:800; color:var(--primary); font-size:0.62rem; background:rgba(37, 99, 235, 0.12); padding:0.05rem 0.25rem; border-radius:3px; flex-shrink:0;">
                                            {{ $r->amount_formatted }}
                                        </span>
                                    @endif
                                </a>
                            @else
                                <div class="cal-mini-badge" style="border-left-color: {{ $borderColor }};" title="{{ $r->title }} - {{ $r->amount_formatted }}">
                                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:62%;">
                                        <ion-icon name="{{ $icon }}" style="vertical-align:middle; color:{{ $borderColor }}; margin-right:0.15rem;"></ion-icon>
                                        {{ $r->title }}
                                    </span>
                                    @if(!empty($r->amount_formatted) && $r->amount_formatted !== '-')
                                        <span style="font-weight:800; color:var(--primary); font-size:0.62rem; background:rgba(37, 99, 235, 0.12); padding:0.05rem 0.25rem; border-radius:3px; flex-shrink:0;">
                                            {{ $r->amount_formatted }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
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
                        <a href="{{ $item->link }}" target="_blank" rel="noopener noreferrer" style="font-size:0.75rem; color:var(--primary); margin-left:0.4rem; text-decoration:none;">View Source &rarr;</a>
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

<!-- Day Events Detail Popup Modal -->
<div class="modal-backdrop" id="dayEventsModal">
    <div class="modal-card" style="max-width: 640px; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
            <div>
                <h3 class="modal-title" id="dayModalTitle" style="font-size: 1.25rem; font-weight: 800; color: var(--text-heading); margin: 0;">Date Activities & Reminders</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.2rem 0 0 0;" id="dayModalSubtitle">Scheduled items for this date</p>
            </div>
            <button type="button" class="btn-close" onclick="closeModal('dayEventsModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.25rem 0; max-height: 60vh; overflow-y: auto;" id="dayModalBody">
            <!-- Dynamically populated by JS -->
        </div>
        <div class="modal-footer" style="border-top: 1px solid var(--border); padding-top: 0.75rem; display:flex; justify-content:space-between; align-items:center;">
            <button type="button" class="btn btn-primary" id="dayModalAddBtn" onclick="openCreateReminderForDate(currentActiveDateStr)">
                <ion-icon name="add-circle-outline"></ion-icon> Add Reminder for this Date
            </button>
            <button type="button" class="btn btn-outline" onclick="closeModal('dayEventsModal')">Close</button>
        </div>
    </div>
</div>

<script>
const allRemindersData = @json($allReminders);
let currentActiveDateStr = '{{ date("Y-m-d") }}';

function openDayEventsModal(dateStr, displayDate) {
    currentActiveDateStr = dateStr;
    const events = allRemindersData.filter(r => r.due_date === dateStr);

    document.getElementById('dayModalTitle').innerText = 'Activities for ' + (displayDate || dateStr);
    
    if (!events || events.length === 0) {
        document.getElementById('dayModalSubtitle').innerText = 'No scheduled activities for this date';
        document.getElementById('dayModalBody').innerHTML = `
            <div style="text-align:center; padding:2rem 1.5rem; color:var(--text-muted);">
                <div style="width:56px; height:56px; border-radius:50%; background:var(--bg-page); border:1px solid var(--border); display:inline-flex; align-items:center; justify-content:center; margin-bottom:0.75rem;">
                    <ion-icon name="calendar-outline" style="font-size:1.75rem; color:var(--text-muted); opacity:0.6;"></ion-icon>
                </div>
                <h4 style="margin:0 0 0.35rem 0; color:var(--text-heading); font-size:1.05rem;">No Activities Scheduled</h4>
                <p style="margin:0 0 1.25rem 0; font-size:0.85rem; color:var(--text-muted); max-width:340px; margin-left:auto; margin-right:auto;">
                    There are no invoices, loan interest schedules, milestones, or reminders due on this date.
                </p>
                <button type="button" class="btn btn-primary" onclick="openCreateReminderForDate('${dateStr}')">
                    <ion-icon name="add-outline"></ion-icon> Create Reminder for this Date
                </button>
            </div>
        `;
        openModal('dayEventsModal');
        return;
    }

    document.getElementById('dayModalSubtitle').innerText = events.length + (events.length === 1 ? ' scheduled item' : ' scheduled items') + ' (Click any item to view source in a new tab)';
    
    let html = '<div style="display:flex; flex-direction:column; gap:0.85rem; padding:0 0.5rem;">';
    
    events.forEach(r => {
        let badgeColor = '#2563eb';
        let badgeBg = '#dbeafe';
        let badgeFg = '#1e40af';
        let typeLabel = 'Client Invoice';
        
        if (r.type === 'loan') {
            badgeColor = '#d97706';
            badgeBg = '#fef3c7';
            badgeFg = '#92400e';
            typeLabel = 'Loan Interest / Principal';
        } else if (r.type === 'milestone') {
            badgeColor = '#8b5cf6';
            badgeBg = '#f3e8ff';
            badgeFg = '#6b21a8';
            typeLabel = 'Payment Milestone';
        } else if (r.type === 'cheque') {
            badgeColor = '#059669';
            badgeBg = '#d1fae5';
            badgeFg = '#065f46';
            typeLabel = 'Cheque Clearance';
        } else if (r.type === 'custom') {
            badgeColor = '#6366f1';
            badgeBg = '#e0e7ff';
            badgeFg = '#3730a3';
            typeLabel = 'Custom Reminder';
        }

        const isOverdue = new Date(r.due_date) < new Date(new Date().toDateString()) && r.status !== 'completed' && r.status !== 'settled';
        const linkAttr = r.link ? `href="${r.link}" target="_blank" rel="noopener noreferrer"` : 'href="javascript:void(0)"';
        
        html += `
            <div style="background:var(--bg-card); border:1px solid var(--border); border-left:4px solid ${badgeColor}; border-radius:12px; padding:1rem; transition:transform 0.15s ease, box-shadow 0.15s ease;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                            <span class="badge" style="background:${badgeBg}; color:${badgeFg}; font-size:0.7rem; font-weight:800; padding:0.15rem 0.5rem; border-radius:6px; text-transform:uppercase;">
                                ${typeLabel}
                            </span>
                            <span style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">Due: ${r.due_date}</span>
                            @if(true)
                            ${isOverdue ? '<span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:0.65rem; padding:0.1rem 0.35rem; border-radius:4px; font-weight:700;">OVERDUE</span>' : ''}
                            ${(r.status === 'completed' || r.status === 'settled') ? '<span class="badge" style="background:#dcfce7; color:#166534; font-size:0.65rem; padding:0.1rem 0.35rem; border-radius:4px; font-weight:700;">SETTLED</span>' : ''}
                            @endif
                        </div>
                        <h4 style="margin:0 0 0.3rem 0; font-size:0.95rem; font-weight:700; color:var(--text-heading); line-height:1.3;">
                            ${escapeHtml(r.title)}
                        </h4>
                        ${r.notes ? `<div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.25rem;">${escapeHtml(r.notes)}</div>` : ''}
                    </div>

                    ${(r.amount_formatted && r.amount_formatted !== '-') ? `
                        <div style="text-align:right; background:var(--bg-page); padding:0.4rem 0.65rem; border-radius:8px; border:1px solid var(--border-light); flex-shrink:0;">
                            <div style="font-size:0.62rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Amount Due</div>
                            <div style="font-size:1.05rem; font-weight:800; color:var(--primary);">
                                ${escapeHtml(r.amount_formatted)}
                            </div>
                        </div>
                    ` : ''}
                </div>

                ${r.link ? `
                    <div style="margin-top:0.75rem; padding-top:0.5rem; border-top:1px dashed var(--border-light); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.75rem; color:var(--text-muted);">Source: System Link</span>
                        <a ${linkAttr} class="btn btn-outline" style="padding:0.25rem 0.65rem; font-size:0.75rem; display:inline-flex; align-items:center; gap:0.3rem; text-decoration:none;">
                            View Source <ion-icon name="open-outline"></ion-icon>
                        </a>
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    html += '</div>';
    
    document.getElementById('dayModalBody').innerHTML = html;
    openModal('dayEventsModal');
}

function openCreateReminderForDate(dateStr) {
    closeModal('dayEventsModal');
    const dateInput = document.querySelector('#createReminderModal input[name="due_date"]');
    if (dateInput && dateStr) {
        dateInput.value = dateStr;
    }
    openModal('createReminderModal');
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>
@endsection

