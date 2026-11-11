@extends('layouts.app')
@section('title', 'Reminders')
@section('meta_description', 'Review finance deadlines and follow-ups in the reminder list and calendar.')

@section('secondary-sidebar')
    @include('operations._sidebar')
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('reminders.css') }}?v={{ substr(sha1_file(public_path('reminders.css')), 0, 12) }}">
@php
    $user = auth()->user();
    $canManage = $user?->isAdmin() || in_array(strtolower($user?->role ?? ''), ['manager', 'accountant'], true);
    $base = ['view' => $viewMode, 'status' => $statusFilter, 'type' => $typeFilter, 'search' => $search, 'month' => $monthKey];
    $reminderUrl = fn (array $changes = []) => '/reminders?'.\Illuminate\Support\Arr::query(array_filter(array_replace($base, $changes), fn ($value) => $value !== null && $value !== ''));
    $priorityLabels = ['overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Next 7 days'];
    $groupLabels = ['overdue' => 'Overdue', 'today' => 'Due today', 'upcoming' => 'Upcoming', 'snoozed' => 'Snoozed', 'completed' => 'Completed'];
    $icons = ['invoice' => 'document-text-outline', 'loan' => 'cash-outline', 'cheque' => 'receipt-outline', 'milestone' => 'flag-outline', 'custom' => 'notifications-outline'];
@endphp

<div class="rw-workspace" data-reminder-workspace>
    <header class="rw-header">
        <div>
            <h1>Reminders</h1>
            <p>Finance deadlines and follow-ups.</p>
        </div>
        @if($canManage)
            <button type="button" class="rw-button rw-primary" data-reminder-create="{{ $viewMode === 'calendar' ? $selectedDate : $todayKey }}" data-reminder-header-create>
                <ion-icon name="add-outline" aria-hidden="true"></ion-icon> Add reminder
            </button>
        @endif
    </header>

    @if(session('success'))
        <p class="rw-notice" role="status"><ion-icon name="checkmark-circle-outline" aria-hidden="true"></ion-icon> {{ session('success') }}</p>
    @endif

    @if($viewMode === 'list' && in_array($statusFilter, ['pending', 'all'], true))
        <nav class="rw-priority" aria-label="Filter open reminders by deadline">
            @foreach($priorityLabels as $key => $label)
                <a href="{{ $reminderUrl(['when' => $window === $key ? null : $key]) }}" @if($window === $key) aria-current="true" @endif data-priority="{{ $key }}">
                    <span>{{ $label }}</span>
                    <strong @class(['rw-zero' => $priority[$key] === 0])>{{ $priority[$key] }}</strong>
                </a>
            @endforeach
        </nav>
    @endif

    <section class="rw-panel" aria-label="Reminder views">
        <div class="rw-toolbar">
            <nav class="rw-views" aria-label="Reminder view">
                <a href="{{ $reminderUrl(['view' => 'list']) }}" @if($viewMode === 'list') aria-current="page" @endif>
                    <ion-icon name="list-outline" aria-hidden="true"></ion-icon> List
                </a>
                <a href="{{ $reminderUrl(['view' => 'calendar']) }}" @if($viewMode === 'calendar') aria-current="page" @endif>
                    <ion-icon name="calendar-outline" aria-hidden="true"></ion-icon> Calendar
                </a>
            </nav>
            <form class="rw-filters" action="/reminders" method="GET" role="search" aria-label="Filter reminders">
                <input type="hidden" name="view" value="{{ $viewMode }}">
                <input type="hidden" name="month" value="{{ $monthKey }}">
                @if($viewMode === 'calendar')
                    <input type="hidden" name="date" value="{{ $selectedDate }}" data-reminder-filter-date>
                @elseif($window)
                    <input type="hidden" name="when" value="{{ $window }}">
                @endif
                <div class="rw-field rw-search">
                    <label for="reminder-search">Search</label>
                    <div>
                        <ion-icon name="search-outline" aria-hidden="true"></ion-icon>
                        <input id="reminder-search" type="search" name="search" maxlength="255" placeholder="Title, notes or amount" value="{{ $search }}">
                    </div>
                </div>
                <div class="rw-field">
                    <label for="reminder-type">Type</label>
                    <select id="reminder-type" name="type">
                        <option value="all">All types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" @selected($typeFilter === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rw-field">
                    <label for="reminder-status">Status</label>
                    <select id="reminder-status" name="status">
                        @foreach(['pending' => 'Pending', 'completed' => 'Completed', 'snoozed' => 'Snoozed', 'all' => 'All statuses'] as $key => $label)
                            <option value="{{ $key }}" @selected($statusFilter === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rw-button">Apply</button>
            </form>
        </div>

        @if($search !== '' || $typeFilter !== 'all' || $statusFilter !== 'pending' || $window)
            <div class="rw-filter-summary">
                @if($window)<span>{{ $priorityLabels[$window] }}</span>@endif
                <a href="{{ $reminderUrl(['search' => null, 'status' => 'pending', 'type' => 'all', 'when' => null, 'date' => $viewMode === 'calendar' ? $selectedDate : null]) }}">Reset filters</a>
            </div>
        @endif

        @if($viewMode === 'calendar')
            @include('reminders.calendar')
        @else
            <div class="rw-section-head">
                <h2>{{ $window ? $priorityLabels[$window] : 'Reminder list' }}</h2>
                <span>{{ $listReminders->count() }} {{ Str::plural('reminder', $listReminders->count()) }}</span>
            </div>
            @if($listReminders->isEmpty())
                <div class="rw-empty">
                    <h3>No reminders match this view</h3>
                    <p>Change the filters or add a reminder for a new follow-up.</p>
                </div>
            @else
                <div class="rw-list">
                    <div class="rw-columns rw-row-grid" aria-hidden="true">
                        <span>Reminder</span><span>Due date</span><span>Amount</span><span>Status</span><span>Actions</span>
                    </div>
                    @php
                        $groups = $listReminders->groupBy('group');
                        $groupOrder = collect(array_keys($groupLabels))->merge($groups->keys())->unique();
                    @endphp
                    @foreach($groupOrder as $group)
                        @if($groups->has($group))
                            <section class="rw-list-group" aria-labelledby="reminder-group-{{ $loop->index }}">
                                <h3 id="reminder-group-{{ $loop->index }}" data-group="{{ $group }}">{{ $groupLabels[$group] ?? Str::headline($group) }} <span>{{ $groups[$group]->count() }}</span></h3>
                                <ul class="rw-rows">
                                    @foreach($groups[$group] as $item)
                                        @include('reminders.row', ['agenda' => false])
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    @endforeach
                </div>
            @endif
        @endif
    </section>
</div>
<script src="{{ asset('reminders.js') }}?v={{ substr(sha1_file(public_path('reminders.js')), 0, 12) }}" defer></script>
@endsection

@section('modals')
@if($canManage)
    <div class="modal-backdrop" id="createReminderModal" @if($errors->hasAny(['title', 'due_date', 'notify_before_days', 'notes'])) data-reminder-reopen @endif>
        <div class="modal-card rw-create-card">
            <div class="modal-header">
                <h2 class="modal-title">Add reminder</h2>
                <button type="button" class="btn-close" aria-label="Close add reminder" onclick="closeModal('createReminderModal')">&times;</button>
            </div>
            <form action="/reminders" method="POST">
                @csrf
                <div class="modal-body rw-create-fields">
                    <div class="rw-field">
                        <label for="reminder-title">Title</label>
                        <input id="reminder-title" type="text" name="title" value="{{ old('title') }}" maxlength="255" required autofocus @error('title') aria-invalid="true" aria-describedby="reminder-title-error" @enderror>
                        @error('title')<p class="rw-field-error" id="reminder-title-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="rw-create-grid">
                        <div class="rw-field">
                            <label for="reminder-due-date">Due date</label>
                            <input id="reminder-due-date" type="date" name="due_date" value="{{ old('due_date', $viewMode === 'calendar' ? $selectedDate : $todayKey) }}" required @error('due_date') aria-invalid="true" aria-describedby="reminder-date-error" @enderror>
                            @error('due_date')<p class="rw-field-error" id="reminder-date-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="rw-field">
                            <label for="reminder-notify-days">Notify before (days)</label>
                            <input id="reminder-notify-days" type="number" name="notify_before_days" value="{{ old('notify_before_days', 2) }}" min="0" @error('notify_before_days') aria-invalid="true" aria-describedby="reminder-notify-error" @enderror>
                            @error('notify_before_days')<p class="rw-field-error" id="reminder-notify-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="rw-field">
                        <label for="reminder-notes">Notes <span>Optional</span></label>
                        <textarea id="reminder-notes" name="notes" rows="3" @error('notes') aria-invalid="true" aria-describedby="reminder-notes-error" @enderror>{{ old('notes') }}</textarea>
                        @error('notes')<p class="rw-field-error" id="reminder-notes-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="rw-button" onclick="closeModal('createReminderModal')">Cancel</button>
                    <button type="submit" class="rw-button rw-primary">Save reminder</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
