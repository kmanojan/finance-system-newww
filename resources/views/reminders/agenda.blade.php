<header class="rw-agenda-head">
    <div>
        <h2 id="reminder-agenda-title" tabindex="-1" data-agenda-heading>{{ $day['date']->format('D, j M') }}</h2>
        <p data-agenda-count>{{ $day['items']->count() }} {{ Str::plural('reminder', $day['items']->count()) }}</p>
    </div>
    @if($canManage && $day['items']->isNotEmpty())
        <button type="button" class="rw-button rw-icon" data-reminder-create="{{ $dayKey }}" aria-label="Add reminder for {{ $day['date']->format('j F Y') }}" title="Add reminder for this day">
            <ion-icon name="add-outline" aria-hidden="true"></ion-icon>
        </button>
    @endif
</header>
@if($day['items']->isEmpty())
    <div class="rw-empty rw-agenda-empty">
        <p>No reminders for this day.</p>
        @if($canManage)
            <button type="button" class="rw-button" data-reminder-create="{{ $dayKey }}">Add for this day</button>
        @endif
    </div>
@else
    <ul class="rw-rows">
        @foreach($day['items'] as $item)
            @include('reminders.row', ['agenda' => true])
        @endforeach
    </ul>
@endif
