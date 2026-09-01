@props([
    'name' => 'category_id',
    'id' => null,
    'categories' => null,
    'selected' => null,
    'type' => null, // 'income', 'expense', or null for all
    'placeholder' => 'Select Category...',
    'required' => false,
    'onchange' => null
])

@php
    $elementId = $id ?? 'cat_selector_' . uniqid();
    if (!$categories) {
        $catQuery = \Illuminate\Support\Facades\DB::table('categories');
        if ($type) {
            $catQuery->where('type', $type);
        }
        $categories = $catQuery->orderBy('name')->get();
    }
    $selectedCategory = $selected ? collect($categories)->firstWhere('id', $selected) : null;
@endphp

<div class="category-selector-wrapper" id="{{ $elementId }}" data-type="{{ $type ?? 'all' }}" @if($onchange) data-onchange="{{ $onchange }}" @endif style="position:relative; width:100%;">
    <input type="hidden" name="{{ $name }}" class="category-id-hidden" value="{{ $selected ?? '' }}" @if($required) required @endif>
    
    <button type="button" class="form-control category-select-trigger" onclick="toggleCategoryDropdown('{{ $elementId }}')" style="display:flex; align-items:center; justify-content:space-between; padding:0.6rem 0.85rem; background:var(--bg-card); border:1px solid var(--border); border-radius:8px; cursor:pointer; min-height:40px; text-align:left; width:100%;">
        <div style="display:flex; align-items:center; gap:0.5rem; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">
            <ion-icon name="folder-outline" class="cat-trigger-icon" style="color:{{ $selectedCategory ? 'var(--primary)' : 'var(--text-muted)' }}; font-size:1.1rem; flex-shrink:0;"></ion-icon>
            <span class="selected-category-label" style="font-size:0.85rem; color:{{ $selectedCategory ? 'var(--text-heading)' : 'var(--text-muted)' }}; font-weight:{{ $selectedCategory ? '600' : '400' }}; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                @if($selectedCategory)
                    {{ $selectedCategory->name }}
                @else
                    {{ $placeholder }}
                @endif
            </span>
            <span class="cat-type-badge badge" style="font-size:0.68rem; padding:0.1rem 0.4rem; border-radius:4px; display:{{ $selectedCategory ? 'inline-block' : 'none' }}; background:{{ $selectedCategory && $selectedCategory->type === 'income' ? '#dcfce7' : '#fee2e2' }}; color:{{ $selectedCategory && $selectedCategory->type === 'income' ? '#15803d' : '#b91c1c' }}; text-transform:capitalize;">
                {{ $selectedCategory ? $selectedCategory->type : '' }}
            </span>
        </div>
        <ion-icon name="chevron-down-outline" style="color:var(--text-muted); font-size:1rem; flex-shrink:0;"></ion-icon>
    </button>

    <div class="category-dropdown-menu" style="display:none; position:absolute; top:105%; left:0; right:0; background:var(--bg-card); border:1px solid var(--border); border-radius:10px; box-shadow:var(--shadow-card, 0 10px 25px rgba(0,0,0,0.25)); z-index:99999; max-height:260px; overflow-y:auto; padding:0.5rem;">
        <div style="padding:0.25rem 0.25rem 0.5rem 0.25rem;">
            <input type="text" class="category-search-input form-control" placeholder="Search category..." onkeyup="filterCategoryList('{{ $elementId }}', this.value)" style="padding:0.4rem 0.75rem; font-size:0.82rem; border-radius:6px;">
        </div>
        
        <div class="category-options-list" style="display:flex; flex-direction:column; gap:0.25rem;">
            @foreach($categories as $cat)
                <div class="category-option-item" 
                     data-id="{{ $cat->id }}" 
                     data-name="{{ e($cat->name) }}" 
                     data-type="{{ $cat->type }}"
                     data-search="{{ strtolower($cat->name . ' ' . $cat->type) }}" 
                     onclick="selectCategoryOption('{{ $elementId }}', '{{ $cat->id }}', '{{ e($cat->name) }}', '{{ $cat->type }}')" 
                     style="padding:0.45rem 0.65rem; border-radius:6px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:background 0.15s ease;"
                     onmouseover="this.style.background='var(--bg-page)'"
                     onmouseout="this.style.background='transparent'">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <ion-icon name="pricetag-outline" style="font-size:0.95rem; color:var(--text-muted);"></ion-icon>
                        <span style="color:var(--text-heading); font-size:0.85rem; font-weight:500;">{{ $cat->name }}</span>
                    </div>
                    <span class="badge" style="font-size:0.68rem; padding:0.1rem 0.35rem; border-radius:4px; background:{{ $cat->type === 'income' ? '#dcfce7' : '#fee2e2' }}; color:{{ $cat->type === 'income' ? '#15803d' : '#b91c1c' }}; text-transform:capitalize;">
                        {{ $cat->type }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</div>

@once
<script>
    function toggleCategoryDropdown(wrapperId) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;
        const menu = wrapper.querySelector('.category-dropdown-menu');
        const isOpen = menu.style.display === 'block';
        document.querySelectorAll('.category-dropdown-menu').forEach(m => m.style.display = 'none');
        if (!isOpen) {
            menu.style.display = 'block';
            const searchInput = wrapper.querySelector('.category-search-input');
            if (searchInput) {
                searchInput.value = '';
                filterCategoryList(wrapperId, '');
                setTimeout(() => searchInput.focus(), 50);
            }
        }
    }

    function filterCategoryList(wrapperId, query) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;
        const q = query.toLowerCase().trim();
        const activeType = wrapper.getAttribute('data-type') || 'all';

        wrapper.querySelectorAll('.category-option-item').forEach(item => {
            const searchData = item.getAttribute('data-search') || '';
            const itemType = item.getAttribute('data-type') || '';
            const matchesType = (activeType === 'all' || !activeType || itemType === activeType);
            const matchesQuery = (!q || searchData.includes(q));

            if (matchesType && matchesQuery) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function selectCategoryOption(wrapperId, id, name, type) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;
        const hidden = wrapper.querySelector('.category-id-hidden');
        hidden.value = id;
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
        hidden.dispatchEvent(new Event('change', { bubbles: true }));

        const label = wrapper.querySelector('.selected-category-label');
        const badge = wrapper.querySelector('.cat-type-badge');
        const icon = wrapper.querySelector('.cat-trigger-icon');

        if (id) {
            label.innerText = name;
            label.style.color = 'var(--text-heading)';
            label.style.fontWeight = '600';
            if (icon) icon.style.color = 'var(--primary)';
            if (badge) {
                badge.innerText = type;
                badge.style.display = 'inline-block';
                badge.style.background = type === 'income' ? '#dcfce7' : '#fee2e2';
                badge.style.color = type === 'income' ? '#15803d' : '#b91c1c';
            }
        } else {
            label.innerText = 'Select Category...';
            label.style.color = 'var(--text-muted)';
            label.style.fontWeight = '400';
            if (badge) badge.style.display = 'none';
            if (icon) icon.style.color = 'var(--text-muted)';
        }

        const menu = wrapper.querySelector('.category-dropdown-menu');
        if (menu) menu.style.display = 'none';

        if (wrapper.hasAttribute('data-onchange')) {
            const onchangeCode = wrapper.getAttribute('data-onchange');
            if (onchangeCode) eval(onchangeCode);
        }
    }

    window.filterCategorySelectorByType = function(wrapperId, type) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;
        wrapper.setAttribute('data-type', type || 'all');
        
        // Auto-select first matching category if current category does not match type
        const hidden = wrapper.querySelector('.category-id-hidden');
        const items = Array.from(wrapper.querySelectorAll('.category-option-item'));
        const matchingItems = items.filter(item => {
            const itemType = item.getAttribute('data-type');
            return !type || type === 'all' || itemType === type;
        });

        const currentId = hidden ? hidden.value : '';
        const currentItem = matchingItems.find(item => item.getAttribute('data-id') === currentId);

        if (!currentItem && matchingItems.length > 0) {
            const first = matchingItems[0];
            selectCategoryOption(wrapperId, first.getAttribute('data-id'), first.getAttribute('data-name'), first.getAttribute('data-type'));
        }
    };

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.category-selector-wrapper')) {
            document.querySelectorAll('.category-dropdown-menu').forEach(m => m.style.display = 'none');
        }
    });
</script>
@endonce
