@props(['name', 'clients' => [], 'multiple' => false, 'selected' => []])

@php
    $modalId = 'clientSelectorModal_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . uniqid();
    $selectedArray = is_array($selected) ? $selected : ($selected ? [$selected] : []);
@endphp

<div class="client-selector-component" id="component_{{ $modalId }}">
    <!-- Hidden input(s) -->
    <div id="hidden_inputs_{{ $modalId }}">
        @if(empty($selectedArray))
            @if(!$multiple)
                <input type="hidden" name="{{ $name }}" value="">
            @endif
        @else
            @foreach($selectedArray as $sel)
                <input type="hidden" name="{{ $multiple ? $name.'[]' : $name }}" value="{{ $sel }}">
            @endforeach
        @endif
    </div>

    <!-- Trigger Button -->
    <button type="button" class="form-control client-selector-trigger" style="text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0.6rem 0.85rem; border-radius: 10px; background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main); transition: all 0.2s ease;" onclick="openModal('{{ $modalId }}')">
        <div style="display: flex; align-items: center; gap: 0.6rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 90%;">
            <ion-icon name="people-outline" style="color: {{ count($selectedArray) > 0 ? 'var(--primary)' : 'var(--text-muted)' }}; font-size: 1.1rem; flex-shrink: 0;"></ion-icon>
            <span id="display_{{ $modalId }}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: {{ count($selectedArray) > 0 ? '600' : '400' }}; color: {{ count($selectedArray) > 0 ? 'var(--text-heading)' : 'var(--text-muted)' }}; font-size: 0.9rem;">
                @if(count($selectedArray) > 0)
                    {{ count($selectedArray) }} selected
                @else
                    Select Client...
                @endif
            </span>
        </div>
        <ion-icon name="chevron-down-outline" style="color: var(--text-muted); font-size: 1rem; flex-shrink: 0;"></ion-icon>
    </button>

    <!-- Modal -->
    <div class="modal-backdrop" id="{{ $modalId }}">
        <div class="modal-card client-selector-modal" style="max-width: 480px; width: 100%; border-radius: 18px; background: var(--bg-card); border: 1px solid var(--border); box-shadow: var(--shadow-card); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
            
            <!-- Header -->
            <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                        <ion-icon name="people-outline" style="color: var(--primary); font-size: 1.25rem;"></ion-icon>
                    </div>
                    <div>
                        <h3 class="modal-title" style="font-size: 1.1rem; font-weight: 700; color: var(--text-heading); margin: 0;">Select Client</h3>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Select client account(s)</span>
                    </div>
                </div>
                <button type="button" class="btn-close" style="color: var(--text-muted); font-size: 1.3rem; background: transparent; border: none; cursor: pointer; padding: 0.3rem; display: flex; align-items: center; justify-content: center; border-radius: 8px;" onclick="closeModal('{{ $modalId }}')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            
            <!-- Body -->
            <div class="modal-body" style="padding: 1.25rem 1.5rem; overflow-y: hidden; display: flex; flex-direction: column; gap: 0.85rem; flex: 1;">
                
                <!-- Search Input -->
                <div class="search-input-group" style="position: relative; width: 100%;">
                    <ion-icon name="search-outline" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.15rem; pointer-events: none;"></ion-icon>
                    <input type="text" placeholder="Search client name, email, or phone..." id="search_{{ $modalId }}" class="form-control client-search-input" style="width: 100%; padding: 0.65rem 2.5rem 0.65rem 2.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-page); color: var(--text-main); font-size: 0.88rem;" onkeyup="filterClients('{{ $modalId }}')">
                    <button type="button" id="clear_search_{{ $modalId }}" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; display: none; padding: 0.2rem; align-items: center; justify-content: center; font-size: 1.1rem;" onclick="clearClientSearch('{{ $modalId }}')">
                        <ion-icon name="close-circle-outline"></ion-icon>
                    </button>
                </div>

                <!-- Match Counter -->
                <div id="search_count_{{ $modalId }}" style="display: none; font-size: 0.75rem; color: var(--text-muted); font-weight: 500; padding: 0 0.2rem;"></div>

                <!-- Client List -->
                <div class="client-list" id="list_{{ $modalId }}" style="display: flex; flex-direction: column; gap: 0.35rem; max-height: 320px; overflow-y: auto; padding-right: 0.35rem; flex: 1;">
                    @foreach($clients as $client)
                    @php
                        $isSelected = in_array($client->id, $selectedArray);
                        $bgStyle = $isSelected ? 'var(--primary-light)' : 'transparent';
                        $borderStyle = $isSelected ? 'var(--primary)' : 'transparent';
                        $nameColor = $isSelected ? 'var(--primary)' : 'var(--text-heading)';
                    @endphp
                    <label class="client-item {{ $isSelected ? 'active-item' : '' }}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease; margin: 0; background: {{ $bgStyle }}; border: 1px solid {{ $borderStyle }};" onmouseover="if(!this.classList.contains('active-item')) this.style.background='var(--bg-page)'" onmouseout="if(!this.classList.contains('active-item')) this.style.background='transparent'">
                        <div style="display: flex; align-items: center; gap: 0.75rem; overflow: hidden; flex: 1;">
                            @if($multiple)
                                <input type="checkbox" value="{{ $client->id }}" data-name="{{ htmlspecialchars($client->name, ENT_QUOTES) }}" class="client-checkbox_{{ $modalId }}" style="width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;" {{ $isSelected ? 'checked' : '' }} onchange="updateSelection('{{ $modalId }}', true, '{{ $name }}', this)">
                            @else
                                <input type="radio" value="{{ $client->id }}" data-name="{{ htmlspecialchars($client->name, ENT_QUOTES) }}" class="client-radio_{{ $modalId }}" style="width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;" {{ $isSelected ? 'checked' : '' }} onchange="updateSelection('{{ $modalId }}', false, '{{ $name }}', this)">
                            @endif
                            
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-page); border: 1px solid var(--border-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <ion-icon name="business-outline" style="color: {{ $isSelected ? 'var(--primary)' : 'var(--text-muted)' }}; font-size: 1rem;"></ion-icon>
                            </div>

                            <div class="client-info" style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
                                <div style="font-weight: 600; font-size: 0.88rem; color: {{ $nameColor }}; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $client->name }}</div>
                                <div style="font-size: 0.73rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    @if(!empty($client->email)) {{ $client->email }} @endif
                                    @if(!empty($client->phone)) {{ !empty($client->email) ? '· ' : '' }}{{ $client->phone }} @endif
                                    @if(empty($client->email) && empty($client->phone)) Client ID: #{{ $client->id }} @endif
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
                        <ion-icon name="people-outline" style="font-size: 1.5rem; color: var(--text-muted);"></ion-icon>
                    </div>
                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-heading); margin-bottom: 0.25rem;">No clients found</div>
                    <p style="font-size: 0.8rem; margin: 0 0 1rem 0;">No client matches your search term.</p>
                    <button type="button" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.35rem 0.85rem; border-radius: 8px;" onclick="clearClientSearch('{{ $modalId }}')">Clear Search</button>
                </div>

            </div>
            
            @if($multiple)
            <div class="modal-footer" style="border-top: 1px solid var(--border-light); padding: 1rem 1.5rem; background: var(--bg-card);">
                <button type="button" class="btn btn-primary-gradient" style="width: 100%; border-radius: 10px; padding: 0.65rem; font-weight: 600;" onclick="closeModal('{{ $modalId }}')">Done</button>
            </div>
            @endif
        </div>
    </div>
</div>

@once
<style>
    .client-list::-webkit-scrollbar { width: 6px; }
    .client-list::-webkit-scrollbar-track { background: transparent; }
    .client-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    .client-list::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

    .client-selector-trigger:hover {
        border-color: var(--primary) !important;
    }

    .client-search-input:focus {
        border-color: var(--primary) !important;
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-light) !important;
    }
</style>
<script>
    function filterClients(modalId) {
        const input = document.getElementById('search_' + modalId).value.toLowerCase().trim();
        const list = document.getElementById('list_' + modalId);
        const clearBtn = document.getElementById('clear_search_' + modalId);
        const countEl = document.getElementById('search_count_' + modalId);
        const emptyEl = document.getElementById('empty_' + modalId);
        const labels = list.querySelectorAll('.client-item');
        
        if (input.length > 0) {
            clearBtn.style.display = 'flex';
        } else {
            clearBtn.style.display = 'none';
            countEl.style.display = 'none';
        }

        let matchCount = 0;
        labels.forEach(label => {
            const nameEl = label.querySelector('.client-info');
            const text = nameEl ? nameEl.innerText.toLowerCase() : '';
            if (input === '' || text.includes(input)) {
                label.style.display = 'flex';
                if (input !== '') matchCount++;
            } else {
                label.style.display = 'none';
            }
        });

        if (input !== '') {
            countEl.innerText = `${matchCount} client${matchCount === 1 ? '' : 's'} found`;
            countEl.style.display = 'block';
            emptyEl.style.display = matchCount === 0 ? 'block' : 'none';
            list.style.display = matchCount === 0 ? 'none' : 'flex';
        } else {
            emptyEl.style.display = 'none';
            list.style.display = 'flex';
        }
    }

    function clearClientSearch(modalId) {
        const input = document.getElementById('search_' + modalId);
        if (input) {
            input.value = '';
            filterClients(modalId);
            input.focus();
        }
    }

    function updateSelection(modalId, isMultiple, inputName, clickedElement = null) {
        const list = document.getElementById('list_' + modalId);
        if (!isMultiple && clickedElement) {
            const radios = document.querySelectorAll('.client-radio_' + modalId);
            radios.forEach(r => {
                if (r !== clickedElement) r.checked = false;
            });
        }
        
        if (list) {
            const labels = list.querySelectorAll('.client-item');
            labels.forEach(label => {
                const input = label.querySelector('input[type="radio"], input[type="checkbox"]');
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
        let selectedNames = [];
        
        if (isMultiple) {
            const checkboxes = document.querySelectorAll('.client-checkbox_' + modalId + ':checked');
            checkboxes.forEach(cb => {
                selectedNames.push(cb.dataset.name);
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName + '[]';
                input.value = cb.value;
                hiddenContainer.appendChild(input);
            });
        } else {
            const radio = document.querySelector('.client-radio_' + modalId + ':checked');
            if (radio) {
                selectedNames.push(radio.dataset.name);
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = radio.value;
                hiddenContainer.appendChild(input);
            }
            closeModal(modalId);
        }
        
        if (selectedNames.length === 0) {
            display.innerText = 'Select Client...';
            display.style.color = 'var(--text-muted)';
            display.style.fontWeight = '400';
            if (!isMultiple) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = '';
                hiddenContainer.appendChild(input);
            }
        } else if (selectedNames.length <= 2) {
            display.innerText = selectedNames.join(', ');
            display.style.color = 'var(--text-heading)';
            display.style.fontWeight = '600';
        } else {
            display.innerText = selectedNames.length + ' selected';
            display.style.color = 'var(--text-heading)';
            display.style.fontWeight = '600';
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const components = document.querySelectorAll('.client-selector-component');
        components.forEach(comp => {
            const modalId = comp.id.replace('component_', '');
            const isMultiple = document.querySelector('.client-checkbox_' + modalId) !== null;
            let inputName = '';
            
            const originalInput = document.querySelector('#hidden_inputs_' + modalId + ' input');
            if (originalInput) {
                inputName = originalInput.name.replace('[]', '');
                updateSelection(modalId, isMultiple, inputName);
            }
        });
    });
</script>
@endonce
