@props([
    'name' => 'party_id',
    'id' => null,
    'parties' => null,
    'selected' => null,
    'role' => null,
    'placeholder' => 'Select Party / Lender...',
    'onchange' => null,
    'variant' => null
])

@php
    $elementId = $id ?? 'party_selector_' . uniqid();
    $projectPicker = $variant === 'project';
    if ($parties === null) {
        $partiesQuery = \Illuminate\Support\Facades\DB::table('parties');
        if ($role) {
            $partiesQuery->where('types', 'LIKE', '%' . $role . '%');
        }
        $parties = $partiesQuery->orderBy('name')->get();
    }
    if ($projectPicker) {
        $parties = collect($parties)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
    }
    $selectedParty = $selected ? collect($parties)->firstWhere('id', $selected) : null;
@endphp

<div class="party-selector-wrapper{{ $projectPicker ? ' project-party-picker' : '' }}" id="{{ $elementId }}" data-placeholder="{{ $placeholder }}" data-onchange="{{ $onchange }}">
    <input type="hidden" name="{{ $name }}" class="party-id-hidden" value="{{ $selected ?? '' }}">
    <div class="party-select-control">
        <button type="button" class="party-select-trigger" id="{{ $elementId }}_trigger" aria-expanded="false"
            @if($projectPicker) aria-labelledby="{{ $elementId }}_label {{ $elementId }}_value" @endif aria-controls="{{ $elementId }}_menu">
            <span class="selected-party-label" id="{{ $elementId }}_value">@if($selectedParty)<strong>{{ $selectedParty->name }}</strong> <span class="party-role">({{ str_replace(',', ', ', $selectedParty->types) }})</span>@elseif($projectPicker && $selected)<strong>Unavailable party</strong>@else{{ $placeholder }}@endif</span>
            <ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon>
        </button>
        <button type="button" class="party-clear" title="Clear selected party" aria-label="Clear selected party" @if(!$selected) hidden @endif><ion-icon name="close-outline" aria-hidden="true"></ion-icon></button>
    </div>
    <div class="party-dropdown-menu" id="{{ $elementId }}_menu" hidden>
        @if($projectPicker)
            <div class="project-party-search">
                <ion-icon name="search-outline" aria-hidden="true"></ion-icon>
                <input type="search" class="party-search-input form-control" autocomplete="off" placeholder="Name, email, phone or role" aria-label="Search by name, email, phone or role" aria-controls="{{ $elementId }}_options">
            </div>
            <p class="project-party-count" role="status" data-party-count>{{ $parties->count() }} {{ Str::plural('result', $parties->count()) }}</p>
        @else
            <input type="search" class="party-search-input form-control" placeholder="Search party name or role…" aria-label="Search party name or role">
        @endif
        <div class="party-options-list" id="{{ $elementId }}_options">
            @unless($projectPicker)
                <button type="button" class="party-option-item" data-id="" data-name="" data-types="">Clear selection</button>
            @endunless
            @foreach($parties as $p)
                @php
                    $roles = str_replace(',', ', ', $p->types);
                    $contact = $projectPicker ? array_filter([$p->contact_person ?? null, $p->email ?? null, $p->phone ?? null]) : [];
                    $searchText = implode(' ', [$p->name, $roles, ...$contact]);
                @endphp
                <button type="button" class="party-option-item" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-types="{{ $roles }}" data-search="{{ $searchText }}"
                    @if($projectPicker) data-phone="{{ $p->phone ?? '' }}" aria-pressed="{{ (string) $selected === (string) $p->id ? 'true' : 'false' }}" @endif>
                    @if($projectPicker)
                        <span class="project-party-identity">
                            <strong>{{ $p->name }}</strong>
                            @if($contact)<span class="party-contact">{{ implode(' · ', $contact) }}</span>@endif
                        </span>
                        <span class="project-party-meta">
                            @if($roles)<span class="project-party-roles">{{ Str::headline($roles) }}</span>@endif
                            <ion-icon name="checkmark-outline" class="project-party-check" aria-hidden="true"></ion-icon>
                        </span>
                    @else
                        <span><strong>{{ $p->name }}</strong>@if(!empty($p->contact_person))<span class="party-contact">{{ $p->contact_person }}</span>@endif</span>
                        <span class="badge badge-draft">{{ $roles }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        @if($projectPicker)
            <div class="party-no-results project-party-empty" hidden>
                <span>{{ $parties->isEmpty() ? 'No parties are available for this field.' : 'No matching parties.' }}</span>
                @unless($parties->isEmpty())
                    <button type="button" class="project-party-clear-search" data-party-clear-search>Clear search</button>
                @endunless
            </div>
        @else
            <p class="party-no-results text-muted" hidden>No matching parties.</p>
        @endif
    </div>
</div>
@if($projectPicker)
    @once
        <link rel="stylesheet" href="{{ asset('project-party-picker.css') }}?v={{ hash_file('sha256', public_path('project-party-picker.css')) }}">
    @endonce
@endif
@once
<script defer src="{{ asset('party-selector.js') }}?v={{ hash_file('sha256', public_path('party-selector.js')) }}"></script>
@endonce
