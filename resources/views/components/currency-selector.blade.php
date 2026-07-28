@props(['name' => 'currency', 'selected' => null, 'id' => null, 'class' => 'form-control', 'onchange' => null, 'required' => false])

@php
    if (!\Illuminate\Support\Facades\Schema::hasTable('currencies') || \Illuminate\Support\Facades\DB::table('currencies')->count() === 0) {
        \App\Models\Currency::seedDefaultCurrencies();
    }

    $company = \Illuminate\Support\Facades\DB::table('companies')->first();
    $defaultBaseCurrency = $company->base_currency ?? 'LKR';
    $selectedCode = $selected ? strtoupper($selected) : $defaultBaseCurrency;

    $currenciesList = \Illuminate\Support\Facades\DB::table('currencies')
        ->where('is_active', 1)
        ->orderBy('is_base', 'desc')
        ->orderBy('code')
        ->get(['id', 'code', 'name', 'symbol', 'is_base']);

    $modalId = 'currencySelectorModal_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . uniqid();
    $selectedCurrObj = collect($currenciesList)->firstWhere('code', $selectedCode);
@endphp

<div class="currency-selector-component" id="{{ $id ?? 'component_' . $modalId }}" data-modal-id="{{ $modalId }}" @if($onchange) data-onchange="{{ $onchange }}" @endif style="position: relative;">
    <!-- Hidden input -->
    <div id="hidden_input_{{ $modalId }}">
        <input type="hidden" name="{{ $name }}" value="{{ $selectedCode }}" {{ $required ? 'required' : '' }}>
    </div>

    <!-- Trigger Button -->
    <button type="button" class="{{ $class }} currency-selector-trigger" style="text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0.55rem 0.8rem; border-radius: 10px; background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main); transition: all 0.2s ease;" onclick="openModal('{{ $modalId }}')">
        <div style="display: flex; align-items: center; gap: 0.55rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 90%;">
            <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 700; font-size: 0.75rem; padding: 0.15rem 0.45rem; border-radius: 5px; text-transform: uppercase; flex-shrink: 0;" id="code_badge_{{ $modalId }}">
                {{ $selectedCode }}
            </span>
            <span id="display_{{ $modalId }}" style="font-weight: 600; color: var(--text-heading); font-size: 0.88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                {{ $selectedCurrObj ? $selectedCurrObj->name . ' (' . $selectedCurrObj->symbol . ')' : $selectedCode }}
            </span>
        </div>
        <ion-icon name="chevron-down-outline" style="color: var(--text-muted); font-size: 0.95rem; flex-shrink: 0;"></ion-icon>
    </button>

    <!-- Modal -->
    <div class="modal-backdrop" id="{{ $modalId }}">
        <div class="modal-card currency-selector-modal" style="max-width: 440px; width: 100%; border-radius: 18px; background: var(--bg-card); border: 1px solid var(--border); box-shadow: var(--shadow-card); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
            
            <!-- Header -->
            <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.2rem 1.4rem; display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                        <ion-icon name="cash-outline" style="color: var(--primary); font-size: 1.2rem;"></ion-icon>
                    </div>
                    <div>
                        <h3 class="modal-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-heading); margin: 0;">Select Currency</h3>
                        <span style="font-size: 0.73rem; color: var(--text-muted);">Choose transaction currency</span>
                    </div>
                </div>
                <button type="button" class="btn-close" style="color: var(--text-muted); font-size: 1.25rem; background: transparent; border: none; cursor: pointer; padding: 0.3rem; display: flex; align-items: center; justify-content: center; border-radius: 8px;" onclick="closeModal('{{ $modalId }}')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            
            <!-- Body -->
            <div class="modal-body" style="padding: 1.1rem 1.4rem; overflow-y: hidden; display: flex; flex-direction: column; gap: 0.75rem; flex: 1;">
                
                <!-- Search Box -->
                <div class="search-input-group" style="position: relative; width: 100%;">
                    <ion-icon name="search-outline" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; pointer-events: none;"></ion-icon>
                    <input type="text" placeholder="Search code (USD, LKR), name, or symbol..." id="search_{{ $modalId }}" class="form-control currency-search-input" style="width: 100%; padding: 0.6rem 2.4rem 0.6rem 2.6rem; border-radius: 11px; border: 1px solid var(--border); background: var(--bg-page); color: var(--text-main); font-size: 0.85rem;" onkeyup="filterCurrencies('{{ $modalId }}')">
                    <button type="button" id="clear_search_{{ $modalId }}" style="position: absolute; right: 0.7rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; display: none; padding: 0.2rem; align-items: center; justify-content: center; font-size: 1.05rem;" onclick="clearCurrencySearch('{{ $modalId }}')">
                        <ion-icon name="close-circle-outline"></ion-icon>
                    </button>
                </div>

                <!-- Match Counter -->
                <div id="search_count_{{ $modalId }}" style="display: none; font-size: 0.73rem; color: var(--text-muted); font-weight: 500; padding: 0 0.2rem;"></div>

                <!-- Currency List -->
                <div class="currency-list" id="list_{{ $modalId }}" style="display: flex; flex-direction: column; gap: 0.3rem; max-height: 300px; overflow-y: auto; padding-right: 0.3rem; flex: 1;">
                    @foreach($currenciesList as $curr)
                    @php
                        $isSel = ($selectedCode === strtoupper($curr->code));
                        $bgStyle = $isSel ? 'var(--primary-light)' : 'transparent';
                        $borderStyle = $isSel ? 'var(--primary)' : 'transparent';
                    @endphp
                    <label class="currency-item {{ $isSel ? 'active-item' : '' }}" data-search="{{ strtolower($curr->code . ' ' . $curr->name . ' ' . $curr->symbol) }}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.8rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease; margin: 0; background: {{ $bgStyle }}; border: 1px solid {{ $borderStyle }};" onmouseover="if(!this.classList.contains('active-item')) this.style.background='var(--bg-page)'" onmouseout="if(!this.classList.contains('active-item')) this.style.background='transparent'">
                        <div style="display: flex; align-items: center; gap: 0.7rem; overflow: hidden; flex: 1;">
                            <input type="radio" value="{{ $curr->code }}" data-code="{{ $curr->code }}" data-name="{{ htmlspecialchars($curr->name, ENT_QUOTES) }}" data-symbol="{{ htmlspecialchars($curr->symbol, ENT_QUOTES) }}" class="currency-radio_{{ $modalId }}" style="width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;" {{ $isSel ? 'checked' : '' }} onchange="updateCurrencySelection('{{ $modalId }}', '{{ $name }}', this)">
                            
                            <span class="badge" style="background: {{ $isSel ? 'var(--primary)' : 'var(--bg-page)' }}; color: {{ $isSel ? '#ffffff' : 'var(--text-heading)' }}; font-weight: 700; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 5px; text-transform: uppercase; border: 1px solid var(--border-light); flex-shrink: 0; min-width: 44px; text-align: center;">
                                {{ $curr->code }}
                            </span>

                            <div style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
                                <div style="font-weight: 600; font-size: 0.86rem; color: {{ $isSel ? 'var(--primary)' : 'var(--text-heading)' }}; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 0.4rem;">
                                    <span>{{ $curr->name }}</span>
                                    <span style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem;">({{ $curr->symbol }})</span>
                                    @if($curr->is_base)
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--success); font-size: 0.65rem; font-weight: 700; padding: 0.05rem 0.35rem; border-radius: 4px;">BASE</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <ion-icon name="checkmark-circle" class="check-icon" style="color: var(--primary); font-size: 1.15rem; flex-shrink: 0; margin-left: 0.5rem; display: {{ $isSel ? 'block' : 'none' }};"></ion-icon>
                    </label>
                    @endforeach
                </div>

                <!-- Empty State -->
                <div id="empty_{{ $modalId }}" style="display: none; padding: 1.75rem 1rem; text-align: center; color: var(--text-muted);">
                    <ion-icon name="cash-outline" style="font-size: 1.6rem; opacity: 0.5; margin-bottom: 0.3rem; display: block; margin-inline: auto;"></ion-icon>
                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-heading); margin-bottom: 0.25rem;">No currencies found</div>
                    <p style="font-size: 0.78rem; margin: 0 0 0.75rem 0;">No active currency matches your search.</p>
                    <button type="button" class="btn btn-outline" style="font-size: 0.78rem; padding: 0.3rem 0.75rem; border-radius: 7px;" onclick="clearCurrencySearch('{{ $modalId }}')">Clear Search</button>
                </div>

            </div>
        </div>
    </div>
</div>

@once
<style>
    .currency-list::-webkit-scrollbar { width: 5px; }
    .currency-list::-webkit-scrollbar-track { background: transparent; }
    .currency-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    .currency-list::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

    .currency-selector-trigger:hover {
        border-color: var(--primary) !important;
    }

    .currency-search-input:focus {
        border-color: var(--primary) !important;
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-light) !important;
    }
</style>
<script>
    function filterCurrencies(modalId) {
        const input = document.getElementById('search_' + modalId).value.toLowerCase().trim();
        const list = document.getElementById('list_' + modalId);
        const clearBtn = document.getElementById('clear_search_' + modalId);
        const countEl = document.getElementById('search_count_' + modalId);
        const emptyEl = document.getElementById('empty_' + modalId);
        const labels = list.querySelectorAll('.currency-item');
        
        if (input.length > 0) {
            clearBtn.style.display = 'flex';
        } else {
            clearBtn.style.display = 'none';
            countEl.style.display = 'none';
        }

        let matchCount = 0;
        labels.forEach(label => {
            const text = label.getAttribute('data-search') || '';
            if (input === '' || text.includes(input)) {
                label.style.display = 'flex';
                if (input !== '') matchCount++;
            } else {
                label.style.display = 'none';
            }
        });

        if (input !== '') {
            countEl.innerText = `${matchCount} currency${matchCount === 1 ? '' : 'ies'} found`;
            countEl.style.display = 'block';
            emptyEl.style.display = matchCount === 0 ? 'block' : 'none';
            list.style.display = matchCount === 0 ? 'none' : 'flex';
        } else {
            emptyEl.style.display = 'none';
            list.style.display = 'flex';
        }
    }

    function clearCurrencySearch(modalId) {
        const input = document.getElementById('search_' + modalId);
        if (input) {
            input.value = '';
            filterCurrencies(modalId);
            input.focus();
        }
    }

    function updateCurrencySelection(modalId, inputName, radio) {
        const list = document.getElementById('list_' + modalId);
        const radios = document.querySelectorAll('.currency-radio_' + modalId);
        radios.forEach(r => {
            if (r !== radio) r.checked = false;
        });

        if (list) {
            const labels = list.querySelectorAll('.currency-item');
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

        const hiddenContainer = document.getElementById('hidden_input_' + modalId);
        const display = document.getElementById('display_' + modalId);
        const codeBadge = document.getElementById('code_badge_' + modalId);
        
        hiddenContainer.innerHTML = '';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName;
        input.value = radio.value;
        hiddenContainer.appendChild(input);
        
        if (codeBadge) codeBadge.innerText = radio.dataset.code;
        if (display) display.innerText = `${radio.dataset.name} (${radio.dataset.symbol})`;
        
        const container = document.querySelector('.currency-selector-component[data-modal-id="' + modalId + '"]');
        if (container && container.hasAttribute('data-onchange')) {
            const onchangeCallback = container.getAttribute('data-onchange');
            if (onchangeCallback) {
                eval(onchangeCallback);
            }
        }

        closeModal(modalId);
    }
</script>
@endonce
