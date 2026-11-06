@props([
    'name' => 'party_id',
    'id' => null,
    'parties' => null,
    'selected' => null,
    'role' => null,
    'placeholder' => 'Select Party / Lender...',
    'onchange' => null
])

@php
    $elementId = $id ?? 'party_selector_' . uniqid();
    if (!$parties) {
        $partiesQuery = \Illuminate\Support\Facades\DB::table('parties');
        if ($role) {
            $partiesQuery->where('types', 'LIKE', '%' . $role . '%');
        }
        $parties = $partiesQuery->orderBy('name')->get();
    }
    $selectedParty = $selected ? collect($parties)->firstWhere('id', $selected) : null;
@endphp

<div class="party-selector-wrapper" id="{{ $elementId }}" data-placeholder="{{ $placeholder }}" data-onchange="{{ $onchange }}">
    <input type="hidden" name="{{ $name }}" class="party-id-hidden" value="{{ $selected ?? '' }}">
    <div class="party-select-control">
        <button type="button" class="party-select-trigger" aria-expanded="false" aria-controls="{{ $elementId }}_menu">
            <span class="selected-party-label">@if($selectedParty)<strong>{{ $selectedParty->name }}</strong> <span class="party-role">({{ str_replace(',', ', ', $selectedParty->types) }})</span>@else{{ $placeholder }}@endif</span>
            <ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon>
        </button>
        <button type="button" class="party-clear" title="Clear selected party" aria-label="Clear selected party" @if(!$selected) hidden @endif><ion-icon name="close-outline" aria-hidden="true"></ion-icon></button>
    </div>
    <div class="party-dropdown-menu" id="{{ $elementId }}_menu" hidden>
        <input type="search" class="party-search-input form-control" placeholder="Search party name or role…" aria-label="Search party name or role">
        <div class="party-options-list">
            <button type="button" class="party-option-item" data-id="" data-name="" data-types="">Clear selection</button>
            @foreach($parties as $p)
                <button type="button" class="party-option-item" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-types="{{ str_replace(',', ', ', $p->types) }}" data-search="{{ strtolower($p->name . ' ' . $p->types) }}">
                    <span><strong>{{ $p->name }}</strong>@if(!empty($p->contact_person))<span class="party-contact">{{ $p->contact_person }}</span>@endif</span>
                    <span class="badge badge-draft">{{ str_replace(',', ', ', $p->types) }}</span>
                </button>
            @endforeach
        </div>
        <p class="party-no-results text-muted" hidden>No matching parties.</p>
    </div>
</div>
@once
<script defer src="{{ asset('party-selector.js') }}?v={{ filemtime(public_path('party-selector.js')) }}"></script>
@endonce
