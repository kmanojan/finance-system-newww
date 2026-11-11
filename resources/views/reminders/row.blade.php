@php
    $due = \Carbon\Carbon::parse($item->due_date);
    $daysAway = (int) \Carbon\Carbon::parse($todayKey)->diffInDays($due, false);
    $isPending = $item->status === 'pending';
    $timing = match (true) {
        !$isPending => null,
        $daysAway < 0 => abs($daysAway).' '.Str::plural('day', abs($daysAway)).' overdue',
        $daysAway === 0 => 'Due today',
        default => 'In '.$daysAway.' '.Str::plural('day', $daysAway),
    };
    $amount = $item->amount_formatted !== '-' ? $item->amount_formatted : null;
@endphp
<li class="rw-row rw-row-grid" data-reminder-row="{{ $item->id }}">
    <div class="rw-row-main">
        <span class="rw-row-type"><ion-icon name="{{ $icons[$item->type] }}" aria-hidden="true"></ion-icon> {{ $types[$item->type] }}</span>
        @if($agenda)
            <h3>{{ $item->title ?: 'Untitled reminder' }}</h3>
        @else
            <h4>{{ $item->title ?: 'Untitled reminder' }}</h4>
        @endif
        @if(!empty($item->notes))<p class="rw-notes">{{ $item->notes }}</p>@endif
        @if(!empty($item->snoozed_until))<p class="rw-notes">Resumes {{ $item->snoozed_until->format('j M, H:i') }}</p>@endif
    </div>
    <div class="rw-row-date">
        <span class="rw-cell-label">Due date</span>
        <time datetime="{{ $item->due_date }}">{{ $due->format('j M Y') }}</time>
        @if($timing)<span class="rw-timing" data-group="{{ $item->group }}">{{ $timing }}</span>@endif
    </div>
    <div class="rw-row-amount">
        <span class="rw-cell-label">Amount</span>
        <span>{{ $amount ?? '—' }}</span>
    </div>
    <div class="rw-row-status">
        <span class="rw-status" data-group="{{ $item->group }}">{{ Str::headline($item->status) }}</span>
    </div>
    <div class="rw-row-actions">
        @if($item->link)
            <a href="{{ $item->link }}" class="rw-button rw-source" target="_blank" rel="noopener noreferrer" aria-label="View source for {{ $item->title }} (opens in a new tab)">
                View source <ion-icon name="arrow-up-outline" aria-hidden="true"></ion-icon>
            </a>
        @elseif(!$item->is_system && $canManage)
            <details class="rw-actions">
                <summary class="rw-button rw-icon" aria-label="Actions for {{ $item->title }}"><ion-icon name="ellipsis-horizontal-outline" aria-hidden="true"></ion-icon></summary>
                <div class="rw-menu">
                    <form method="POST" action="/reminders/{{ $item->manual_id }}/status">
                        @csrf
                        <input type="hidden" name="status" value="{{ $isPending ? 'settled' : 'pending' }}">
                        <button type="submit"><ion-icon name="{{ $isPending ? 'checkmark-outline' : 'refresh-outline' }}" aria-hidden="true"></ion-icon> {{ $isPending ? 'Mark complete' : 'Reopen' }}</button>
                    </form>
                    @if($isPending)
                        <form method="POST" action="/reminders/{{ $item->manual_id }}/status">
                            @csrf
                            <input type="hidden" name="status" value="snoozed">
                            <button type="submit"><ion-icon name="time-outline" aria-hidden="true"></ion-icon> Snooze for 24 hours</button>
                        </form>
                    @endif
                    <form method="POST" action="/reminders/{{ $item->manual_id }}" data-reminder-delete>
                        @csrf
                        @method('DELETE')
                        <button type="submit"><ion-icon name="trash-outline" aria-hidden="true"></ion-icon> Delete reminder</button>
                    </form>
                </div>
            </details>
        @endif
    </div>
</li>
