@props(['name', 'budgetItems' => [], 'selected' => null, 'id' => null])

@php
    $modalId = $id ?? ('budgetItemSelectorModal_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . uniqid());
    $selectedArray = is_array($selected) ? $selected : ($selected ? [$selected] : []);
    $selectedItem = null;
    if ($selected) {
        foreach ($budgetItems as $item) {
            if ($item->id == $selected) {
                $selectedItem = $item;
                break;
            }
        }
    }
@endphp

<div class="budget-item-selector-component" id="component_{{ $modalId }}">
    <!-- Hidden input -->
    <div id="hidden_inputs_{{ $modalId }}">
        <input type="hidden" name="{{ $name }}" value="{{ $selected ?? '' }}">
    </div>

    <!-- Trigger Button -->
    <button type="button" class="form-control budget-item-selector-trigger" style="text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0.6rem 0.85rem; border-radius: 10px; background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main); transition: all 0.2s ease;" onclick="openModal('{{ $modalId }}')">
        <div style="display: flex; align-items: center; gap: 0.6rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 90%;">
            <ion-icon name="calculator-outline" style="color: {{ $selectedItem ? 'var(--primary)' : 'var(--text-muted)' }}; font-size: 1.1rem; flex-shrink: 0;"></ion-icon>
            <span id="display_{{ $modalId }}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: {{ $selectedItem ? '600' : '400' }}; color: {{ $selectedItem ? 'var(--text-heading)' : 'var(--text-muted)' }}; font-size: 0.9rem;">
                @if($selectedItem)
                    {{ $selectedItem->budget_name }} &gt; {{ $selectedItem->group_name }} &gt; {{ $selectedItem->item_name }}
                @else
                    Select Budget Line Item...
                @endif
            </span>
        </div>
        <ion-icon name="chevron-down-outline" style="color: var(--text-muted); font-size: 1rem; flex-shrink: 0;"></ion-icon>
    </button>

    <!-- Modal -->
    <div class="modal-backdrop" id="{{ $modalId }}">
        <div class="modal-card budget-item-modal" style="max-width: 520px; width: 100%; border-radius: 18px; background: var(--bg-card); border: 1px solid var(--border); box-shadow: var(--shadow-card); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
            
            <!-- Header -->
            <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                        <ion-icon name="calculator-outline" style="color: var(--primary); font-size: 1.25rem;"></ion-icon>
                    </div>
                    <div>
                        <h3 class="modal-title" style="font-size: 1.1rem; font-weight: 700; color: var(--text-heading); margin: 0;">Select Budget Line Item</h3>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Choose budget line item for financial allocation</span>
                    </div>
                </div>
                <button type="button" class="btn-close" style="color: var(--text-muted); font-size: 1.3rem; background: transparent; border: none; cursor: pointer; padding: 0.3rem; display: flex; align-items: center; justify-content: center; border-radius: 8px;" onclick="closeModal('{{ $modalId }}')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            
            <!-- Body -->
            <div class="modal-body" style="padding: 1.25rem 1.5rem; overflow-y: hidden; display: flex; flex-direction: column; gap: 0.85rem; flex: 1;">
                
                <!-- Search Box -->
                <div class="search-input-group" style="position: relative; width: 100%;">
                    <ion-icon name="search-outline" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.15rem; pointer-events: none;"></ion-icon>
                    <input type="text" placeholder="Search budget name, group, or line item..." id="search_{{ $modalId }}" class="form-control budget-search-input" style="width: 100%; padding: 0.65rem 2.5rem 0.65rem 2.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-page); color: var(--text-main); font-size: 0.88rem;" onkeyup="filterBudgetItems('{{ $modalId }}')">
                    <button type="button" id="clear_search_{{ $modalId }}" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; display: none; padding: 0.2rem; align-items: center; justify-content: center; font-size: 1.1rem;" onclick="clearBudgetSearch('{{ $modalId }}')">
                        <ion-icon name="close-circle-outline"></ion-icon>
                    </button>
                </div>

                <!-- Match Counter -->
                <div id="search_count_{{ $modalId }}" style="display: none; font-size: 0.75rem; color: var(--text-muted); font-weight: 500; padding: 0 0.2rem;"></div>

                <!-- Budget Item List -->
                <div class="budget-item-list" id="list_{{ $modalId }}" style="display: flex; flex-direction: column; gap: 0.35rem; max-height: 320px; overflow-y: auto; padding-right: 0.35rem; flex: 1;">
                    
                    <!-- None Selection -->
                    <label class="budget-item-row {{ !$selected ? 'active-item' : '' }}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease; margin: 0; background: {{ !$selected ? 'var(--primary-light)' : 'transparent' }}; border: 1px solid {{ !$selected ? 'var(--primary)' : 'transparent' }};" onmouseover="if(!this.classList.contains('active-item')) this.style.background='var(--bg-page)'" onmouseout="if(!this.classList.contains('active-item')) this.style.background='transparent'">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <input type="radio" value="" data-path="Select Budget Line Item..." class="budget-item-radio_{{ $modalId }}" style="width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;" {{ !$selected ? 'checked' : '' }} onchange="updateBudgetItemSelection('{{ $modalId }}', '{{ $name }}', this)">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <ion-icon name="remove-circle-outline" style="color: var(--text-muted); font-size: 1.1rem;"></ion-icon>
                                <span style="font-weight: 500; font-size: 0.88rem; color: {{ !$selected ? 'var(--primary)' : 'var(--text-muted)' }};">
                                    -- No Budget (None) --
                                </span>
                            </div>
                        </div>
                        <ion-icon name="checkmark-circle" class="check-icon" style="color: var(--primary); font-size: 1.15rem; flex-shrink: 0; display: {{ !$selected ? 'block' : 'none' }};"></ion-icon>
                    </label>

                    @foreach($budgetItems as $item)
                    @php
                        $isSelected = ($selected == $item->id);
                        $path = $item->budget_name . ' > ' . $item->group_name . ' > ' . $item->item_name;
                        $bgStyle = $isSelected ? 'var(--primary-light)' : 'transparent';
                        $borderStyle = $isSelected ? 'var(--primary)' : 'transparent';
                    @endphp
                    <label class="budget-item-row {{ $isSelected ? 'active-item' : '' }}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease; margin: 0; background: {{ $bgStyle }}; border: 1px solid {{ $borderStyle }};" onmouseover="if(!this.classList.contains('active-item')) this.style.background='var(--bg-page)'" onmouseout="if(!this.classList.contains('active-item')) this.style.background='transparent'">
                        <div style="display: flex; align-items: center; gap: 0.75rem; overflow: hidden; flex: 1;">
                            <input type="radio" value="{{ $item->id }}" data-path="{{ htmlspecialchars($path, ENT_QUOTES) }}" class="budget-item-radio_{{ $modalId }}" style="width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;" {{ $isSelected ? 'checked' : '' }} onchange="updateBudgetItemSelection('{{ $modalId }}', '{{ $name }}', this)">
                            
                            <div class="budget-item-info" style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
                                <div style="font-weight: 600; font-size: 0.88rem; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 0.4rem;">
                                    <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 700; font-size: 0.72rem; padding: 0.1rem 0.4rem; border-radius: 4px; flex-shrink: 0;">{{ $item->budget_name }}</span>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">&gt;</span>
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $item->group_name }} &gt; {{ $item->item_name }}</span>
                                </div>
                            </div>
                        </div>

                        <ion-icon name="checkmark-circle" class="check-icon" style="color: var(--primary); font-size: 1.15rem; flex-shrink: 0; margin-left: 0.5rem; display: {{ $isSelected ? 'block' : 'none' }};"></ion-icon>
                    </label>
                    @endforeach
                </div>

                <!-- Empty State -->
                <div id="empty_{{ $modalId }}" style="display: none; padding: 2rem 1rem; text-align: center; color: var(--text-muted);">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-page); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                        <ion-icon name="calculator-outline" style="font-size: 1.5rem; color: var(--text-muted);"></ion-icon>
                    </div>
                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-heading); margin-bottom: 0.25rem;">No budget items found</div>
                    <p style="font-size: 0.8rem; margin: 0 0 1rem 0;">No budget line item matches your search term.</p>
                    <button type="button" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.35rem 0.85rem; border-radius: 8px;" onclick="clearBudgetSearch('{{ $modalId }}')">Clear Search</button>
                </div>

            </div>
        </div>
    </div>
</div>

@once
<style>
    .budget-item-list::-webkit-scrollbar { width: 6px; }
    .budget-item-list::-webkit-scrollbar-track { background: transparent; }
    .budget-item-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    .budget-item-list::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

    .budget-item-selector-trigger:hover {
        border-color: var(--primary) !important;
    }

    .budget-search-input:focus {
        border-color: var(--primary) !important;
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-light) !important;
    }
</style>
<script>
    function filterBudgetItems(modalId) {
        const input = document.getElementById('search_' + modalId).value.toLowerCase().trim();
        const list = document.getElementById('list_' + modalId);
        const clearBtn = document.getElementById('clear_search_' + modalId);
        const countEl = document.getElementById('search_count_' + modalId);
        const emptyEl = document.getElementById('empty_' + modalId);
        const labels = list.querySelectorAll('.budget-item-row');
        
        if (input.length > 0) {
            clearBtn.style.display = 'flex';
        } else {
            clearBtn.style.display = 'none';
            countEl.style.display = 'none';
        }

        let matchCount = 0;
        labels.forEach(label => {
            const infoEl = label.querySelector('.budget-item-info') || label;
            const text = infoEl ? infoEl.innerText.toLowerCase() : '';
            if (input === '' || text.includes(input)) {
                label.style.display = 'flex';
                if (input !== '') matchCount++;
            } else {
                label.style.display = 'none';
            }
        });

        if (input !== '') {
            countEl.innerText = `${matchCount} budget item${matchCount === 1 ? '' : 's'} found`;
            countEl.style.display = 'block';
            emptyEl.style.display = matchCount === 0 ? 'block' : 'none';
            list.style.display = matchCount === 0 ? 'none' : 'flex';
        } else {
            emptyEl.style.display = 'none';
            list.style.display = 'flex';
        }
    }

    function clearBudgetSearch(modalId) {
        const input = document.getElementById('search_' + modalId);
        if (input) {
            input.value = '';
            filterBudgetItems(modalId);
            input.focus();
        }
    }

    function updateBudgetItemSelection(modalId, inputName, radio) {
        const list = document.getElementById('list_' + modalId);
        const radios = document.querySelectorAll('.budget-item-radio_' + modalId);
        radios.forEach(r => {
            if (r !== radio) r.checked = false;
        });

        if (list) {
            const labels = list.querySelectorAll('.budget-item-row');
            labels.forEach(label => {
                const input = label.querySelector('input[type="radio"]');
                const checkIcon = label.querySelector('.check-icon');
                if (input && input.checked) {
                    label.classList.add('active-item');
                    label.style.background = 'var(--primary-light)';
                    label.style.borderColor = 'var(--primary)';
                    if (checkIcon) checkIcon.style.display = 'block';
                } else {
                    label.classList.remove('active-item');
                    label.style.background = 'transparent';
                    label.style.borderColor = 'transparent';
                    if (checkIcon) checkIcon.style.display = 'none';
                }
            });
        }

        const hiddenContainer = document.getElementById('hidden_inputs_' + modalId);
        const display = document.getElementById('display_' + modalId);
        
        hiddenContainer.innerHTML = '';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName;
        input.value = radio.value;
        hiddenContainer.appendChild(input);
        
        if (radio.value === '' || radio.dataset.path === 'Select Budget Line Item...') {
            display.innerText = 'Select Budget Line Item...';
            display.style.color = 'var(--text-muted)';
            display.style.fontWeight = '400';
        } else {
            display.innerText = radio.dataset.path;
            display.style.color = 'var(--text-heading)';
            display.style.fontWeight = '600';
        }
        
        closeModal(modalId);
    }
</script>
@endonce
