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

<div class="party-selector-wrapper" id="{{ $elementId }}" style="position:relative; width:100%;">
    <input type="hidden" name="{{ $name }}" class="party-id-hidden" value="{{ $selected ?? '' }}">
    
    <div class="party-select-trigger" onclick="togglePartyDropdown('{{ $elementId }}')" style="display:flex; align-items:center; justify-content:space-between; padding:0.65rem 1rem; background:var(--bg-card); border:1px solid var(--border); border-radius:8px; cursor:pointer; min-height:42px;">
        <div class="selected-party-label" style="font-size:0.95rem; color:{{ $selectedParty ? 'var(--text-heading)' : 'var(--text-muted)' }}; font-weight:500;">
            @if($selectedParty)
                <strong>{{ $selectedParty->name }}</strong> 
                <span style="font-size:0.75rem; color:var(--text-muted); margin-left:0.4rem;">({{ str_replace(',', ', ', $selectedParty->types) }})</span>
            @else
                {{ $placeholder }}
            @endif
        </div>
        <ion-icon name="chevron-down-outline" style="color:var(--text-muted); font-size:1.1rem;"></ion-icon>
    </div>

    <div class="party-dropdown-menu" style="display:none; position:absolute; top:105%; left:0; right:0; background:var(--bg-card); border:1px solid var(--border); border-radius:10px; box-shadow:var(--shadow-card, 0 10px 25px rgba(0,0,0,0.2)); z-index:9999; max-height:260px; overflow-y:auto; padding:0.5rem;">
        <input type="text" class="party-search-input form-control" placeholder="Search party name or role..." onkeyup="filterPartyList('{{ $elementId }}', this.value)" style="margin-bottom:0.5rem; padding:0.4rem 0.75rem; font-size:0.85rem;">
        
        <div class="party-options-list">
            <div class="party-option-item" onclick="selectPartyOption('{{ $elementId }}', '', '-- Direct / Custom Lender --', '')" style="padding:0.5rem 0.75rem; border-radius:6px; cursor:pointer; font-size:0.9rem; color:var(--text-muted);">
                <em>-- Direct / Custom Lender --</em>
            </div>
            @foreach($parties as $p)
                <div class="party-option-item" data-id="{{ $p->id }}" data-name="{{ e($p->name) }}" data-search="{{ strtolower($p->name . ' ' . $p->types) }}" onclick="selectPartyOption('{{ $elementId }}', '{{ $p->id }}', '{{ e($p->name) }}', '{{ e(str_replace(',', ', ', $p->types)) }}')" style="padding:0.5rem 0.75rem; border-radius:6px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:background 0.2s;">
                    <div>
                        <strong style="color:var(--text-heading); font-size:0.9rem;">{{ $p->name }}</strong>
                        @if(!empty($p->contact_person))
                            <div style="font-size:0.75rem; color:var(--text-muted);">{{ $p->contact_person }}</div>
                        @endif
                    </div>
                    <span class="badge badge-draft" style="font-size:0.7rem;">{{ str_replace(',', ', ', $p->types) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
if (typeof togglePartyDropdown === 'undefined') {
    function togglePartyDropdown(wrapperId) {
        const wrapper = document.getElementById(wrapperId);
        const menu = wrapper.querySelector('.party-dropdown-menu');
        const isOpen = menu.style.display === 'block';
        document.querySelectorAll('.party-dropdown-menu').forEach(m => m.style.display = 'none');
        if (!isOpen) {
            menu.style.display = 'block';
            wrapper.querySelector('.party-search-input').focus();
        }
    }

    function filterPartyList(wrapperId, query) {
        const wrapper = document.getElementById(wrapperId);
        const q = query.toLowerCase();
        wrapper.querySelectorAll('.party-option-item').forEach(item => {
            const searchData = item.getAttribute('data-search') || '';
            if (searchData.includes(q) || !q) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function selectPartyOption(wrapperId, id, name, types) {
        const wrapper = document.getElementById(wrapperId);
        wrapper.querySelector('.party-id-hidden').value = id;
        const triggerLabel = wrapper.querySelector('.selected-party-label');
        if (id) {
            triggerLabel.innerHTML = '<strong>' + name + '</strong> <span style="font-size:0.75rem; color:var(--text-muted); margin-left:0.4rem;">(' + types + ')</span>';
            // Auto fill lender_name input if present in the parent form
            const form = wrapper.closest('form');
            if (form) {
                const lenderInput = form.querySelector('input[name="lender_name"]');
                if (lenderInput) {
                    lenderInput.value = name;
                }
            }
        } else {
            triggerLabel.innerHTML = '<span style="color:var(--text-muted);">Select Party / Lender...</span>';
        }
        wrapper.querySelector('.party-dropdown-menu').style.display = 'none';
        
        @if($onchange)
            if (typeof {{ $onchange }} === 'function') {{ $onchange }}(id, name);
        @endif
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.party-selector-wrapper')) {
            document.querySelectorAll('.party-dropdown-menu').forEach(m => m.style.display = 'none');
        }
    });
}
</script>
