<div class="rw-calendar-layout">
    <section class="rw-calendar" aria-labelledby="reminder-month-title">
        <header class="rw-calendar-head">
            <div>
                <h2 id="reminder-month-title">{{ $monthTitle }}</h2>
                <p>{{ $monthReminders->count() }} {{ Str::plural('reminder', $monthReminders->count()) }} across {{ $daysByDate->count() }} {{ Str::plural('day', $daysByDate->count()) }}</p>
            </div>
            <nav class="rw-month-nav" aria-label="Navigate calendar months">
                <a href="{{ $reminderUrl(['month' => substr($todayKey, 0, 7), 'date' => $todayKey]) }}" class="rw-button">Today</a>
                <a href="{{ $reminderUrl(['month' => $prevMonth]) }}" class="rw-button rw-icon" aria-label="Previous month">
                    <ion-icon name="chevron-back-outline" aria-hidden="true"></ion-icon>
                </a>
                <a href="{{ $reminderUrl(['month' => $nextMonth]) }}" class="rw-button rw-icon" aria-label="Next month">
                    <ion-icon name="chevron-forward-outline" aria-hidden="true"></ion-icon>
                </a>
            </nav>
        </header>
        <p id="reminder-calendar-instructions" class="rw-visually-hidden">Use the arrow keys to move between days. Press Enter to see the agenda for a day.</p>
        <div class="rw-calendar-grid" role="group" aria-label="{{ $monthTitle }} dates" aria-describedby="reminder-calendar-instructions" data-reminder-calendar>
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                <span class="rw-weekday" aria-hidden="true">{{ $weekday }}</span>
            @endforeach
            @for($blank = 1; $blank < $carbonMonth->dayOfWeekIso; $blank++)
                <span class="rw-calendar-blank" aria-hidden="true"></span>
            @endfor
            @foreach($monthDays as $dateKey => $day)
                @php
                    $items = $day['items'];
                    $count = $items->count();
                    $hasOverdue = $items->contains('group', 'overdue');
                @endphp
                <a class="rw-day" href="{{ $reminderUrl(['date' => $dateKey]) }}#reminder-agenda"
                    data-reminder-day="{{ $dateKey }}" aria-controls="reminder-agenda"
                    aria-label="{{ $day['date']->format('l, j F Y') }}{{ $dateKey === $todayKey ? ', today' : '' }}, {{ $count }} {{ Str::plural('reminder', $count) }}{{ $hasOverdue ? ', includes overdue reminders' : '' }}"
                    @if($dateKey === $selectedDate) aria-current="date" @endif
                    @if($dateKey === $todayKey) data-today @endif
                    @if($day['date']->isWeekend()) data-weekend @endif
                    @if($hasOverdue) data-overdue @endif>
                    <span class="rw-day-top" aria-hidden="true">
                        <span class="rw-day-number">{{ $day['date']->day }}</span>
                        @if($count)<span class="rw-day-count">{{ $count }}</span>@endif
                    </span>
                    @if($count)
                        <span class="rw-day-preview" aria-hidden="true">
                            @foreach($items->take(2) as $item)
                                <span class="rw-day-event" data-group="{{ $item->group }}" title="{{ $types[$item->type] }}: {{ $item->title }}">{{ $item->title }}</span>
                            @endforeach
                            @if($count > 2)<span class="rw-day-more">+{{ $count - 2 }} more</span>@endif
                        </span>
                    @endif
                </a>
            @endforeach
            @for($blank = $carbonMonth->copy()->endOfMonth()->dayOfWeekIso; $blank < 7; $blank++)
                <span class="rw-calendar-blank" aria-hidden="true"></span>
            @endfor
        </div>
        @if($monthReminders->isEmpty())
            <p class="rw-month-empty">No reminders match this month and these filters.</p>
        @endif
    </section>

    <section id="reminder-agenda" class="rw-agenda" aria-labelledby="reminder-agenda-title" data-reminder-agenda>
        @include('reminders.agenda', ['dayKey' => $selectedDate, 'day' => $monthDays[$selectedDate]])
    </section>
    <p class="rw-visually-hidden" aria-live="polite" data-reminder-announcement></p>

    @foreach($monthDays as $dayKey => $day)
        <template data-reminder-agenda-for="{{ $dayKey }}">
            @include('reminders.agenda')
        </template>
    @endforeach
</div>
