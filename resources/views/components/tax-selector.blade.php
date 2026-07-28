@props([
    'name' => 'tax_type_id',
    'selected' => null,
    'category' => null,
    'appliesTo' => null,
    'multiple' => false,
    'onchange' => null,
    'id' => null,
    'class' => 'form-control',
    'required' => false
])

@php
    $query = \Illuminate\Support\Facades\DB::table('tax_types')->where('is_active', 1);

    if ($category) {
        $query->where('category', $category);
    }
    if ($appliesTo) {
        $query->where('applies_to', $appliesTo);
    }

    $taxTypesList = $query->orderBy('category')->orderBy('name')->get();

    $modalId = 'taxSelectorModal_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . uniqid();

    $selectedIds = is_array($selected) ? $selected : ($selected ? [$selected] : []);
    
    // Find default if nothing selected
    if (empty($selectedIds) && !$multiple) {
        $defaultTax = collect($taxTypesList)->firstWhere('is_default', 1);
        if ($defaultTax) {
            $selectedIds = [$defaultTax->id];
        }
    }

    $selectedTaxObjs = collect($taxTypesList)->whereIn('id', $selectedIds);
@endphp

<div class="tax-selector-component" id="{{ $id ?? 'component_' . $modalId }}" data-modal-id="{{ $modalId }}" data-multiple="{{ $multiple ? '1' : '0' }}" @if($onchange) data-onchange="{{ $onchange }}" @endif style="position: relative;">
    <!-- Hidden input(s) -->
    <div id="hidden_inputs_{{ $modalId }}">
        @if($multiple)
            @foreach($selectedIds as $sId)
                <input type="hidden" name="{{ $name }}[]" value="{{ $sId }}" class="tax-hidden-val">
            @endforeach
        @else
            <input type="hidden" name="{{ $name }}" value="{{ $selectedIds[0] ?? '' }}" class="tax-hidden-val" {{ $required ? 'required' : '' }}>
        @endif
    </div>

    <!-- Trigger Button -->
    <button type="button" class="{{ $class }} tax-selector-trigger" style="text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0.55rem 0.8rem; border-radius: 10px; background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main); transition: all 0.2s ease;" onclick="openModal('{{ $modalId }}')">
        <div style="display: flex; align-items: center; gap: 0.55rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 90%;">
            <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 700; font-size: 0.75rem; padding: 0.15rem 0.45rem; border-radius: 5px; text-transform: uppercase; flex-shrink: 0;" id="badge_{{ $modalId }}">
                @if($selectedTaxObjs->count() > 0)
                    {{ $selectedTaxObjs->count() === 1 ? strtoupper($selectedTaxObjs->first()->category) : $selectedTaxObjs->count() . ' Taxes' }}
                @else
                    Tax
                @endif
            </span>
            <span id="display_{{ $modalId }}" style="font-weight: 600; color: var(--text-heading); font-size: 0.88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                @if($selectedTaxObjs->count() > 0)
                    {{ $selectedTaxObjs->map(fn($t) => $t->name . ' (' . $t->rate . '%)')->implode(', ') }}
                @else
                    Select Tax Rate...
                @endif
            </span>
        </div>
        <ion-icon name="chevron-down-outline" style="color: var(--text-muted); font-size: 0.95rem; flex-shrink: 0;"></ion-icon>
    </button>

    <!-- Modal -->
    <div class="modal-backdrop" id="{{ $modalId }}">
        <div class="modal-card tax-selector-modal" style="max-width: 460px; width: 100%; border-radius: 18px; background: var(--bg-card); border: 1px solid var(--border); box-shadow: var(--shadow-card); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
            
            <!-- Header -->
            <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.2rem 1.4rem; display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                        <ion-icon name="receipt-outline" style="color: var(--primary); font-size: 1.2rem;"></ion-icon>
                    </div>
                    <div>
                        <h3 class="modal-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-heading); margin: 0;">Select Statutory Tax</h3>
                        <span style="font-size: 0.73rem; color: var(--text-muted);">{{ $multiple ? 'Select one or multiple tax rates' : 'Select tax rate for line item or payment' }}</span>
                    </div>
                </div>
                <button type="button" class="btn-close" style="color: var(--text-muted); font-size: 1.25rem; background: transparent; border: none; cursor: pointer; padding: 0.3rem; display: flex; align-items: center; justify-content: center; border-radius: 8px;" onclick="closeModal('{{ $modalId }}')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            
            <!-- Search -->
            <div style="padding: 0.8rem 1.4rem; border-bottom: 1px solid var(--border-light); background: var(--bg-card);">
                <div class="search-input" style="width: 100%; margin: 0;">
                    <ion-icon name="search-outline"></ion-icon>
                    <input type="text" id="search_{{ $modalId }}" placeholder="Search VAT, WHT, or tax rate..." style="width: 100%; padding-left: 2.2rem;" onkeyup="filterTaxTypes('{{ $modalId }}')">
                </div>
            </div>

            <!-- List -->
            <div class="modal-body" style="padding: 0.8rem; overflow-y: auto; flex-grow: 1; max-height: 400px;" id="list_{{ $modalId }}">
                @foreach($taxTypesList as $tax)
                    @php
                        $isSelected = in_array($tax->id, $selectedIds);
                    @endphp
                    <div class="tax-item-row" data-id="{{ $tax->id }}" data-name="{{ strtolower($tax->name) }}" data-category="{{ $tax->category }}" data-rate="{{ $tax->rate }}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: 12px; margin-bottom: 0.4rem; cursor: pointer; border: 1px solid {{ $isSelected ? 'var(--primary)' : 'transparent' }}; background: {{ $isSelected ? 'var(--primary-light)' : 'var(--bg-card)' }}; transition: all 0.15s ease;" onclick="toggleTaxSelection('{{ $modalId }}', {{ $tax->id }}, '{{ addslashes($tax->name) }}', '{{ strtoupper($tax->category) }}', {{ $tax->rate }}, {{ $multiple ? 'true' : 'false' }})">
                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: var(--primary);">
                                {{ strtoupper($tax->category) }}
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-heading);">
                                    {{ $tax->name }}
                                </div>
                                <div style="font-size: 0.73rem; color: var(--text-muted);">
                                    Applies to: {{ ucfirst(str_replace('_', ' ', $tax->applies_to)) }}
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <span class="font-bold" style="color: var(--primary); font-size: 0.95rem;">{{ number_format($tax->rate, 2) }}%</span>
                            <div class="tax-check-icon" style="display: {{ $isSelected ? 'flex' : 'none' }}; width: 22px; height: 22px; border-radius: 50%; background: var(--primary); color: white; align-items: center; justify-content: center; font-size: 0.8rem;">
                                <ion-icon name="checkmark-outline"></ion-icon>
                            </div>
                        </div>
                    </div>
                @endforeach
                @if($taxTypesList->isEmpty())
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.85rem;">
                        No tax rates configured in Master Data.
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="modal-footer" style="padding: 0.8rem 1.4rem; border-top: 1px solid var(--border-light); background: var(--bg-card); display: flex; justify-content: flex-end; gap: 0.6rem;">
                <button type="button" class="btn btn-primary" onclick="closeModal('{{ $modalId }}')">Done</button>
            </div>

        </div>
    </div>
</div>

@once
<script>
    function filterTaxTypes(modalId) {
        const query = document.getElementById('search_' + modalId).value.toLowerCase();
        const items = document.querySelectorAll('#list_' + modalId + ' .tax-item-row');
        items.forEach(item => {
            const name = item.getAttribute('data-name');
            const category = item.getAttribute('data-category');
            if (name.includes(query) || category.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function toggleTaxSelection(modalId, taxId, taxName, category, rate, isMultiple) {
        const component = document.querySelector(`[data-modal-id="${modalId}"]`);
        const hiddenInputsContainer = document.getElementById('hidden_inputs_' + modalId);
        const nameAttr = hiddenInputsContainer.querySelector('input')?.getAttribute('name')?.replace('[]', '') || 'tax_type_id';
        const displayEl = document.getElementById('display_' + modalId);
        const badgeEl = document.getElementById('badge_' + modalId);

        if (!isMultiple) {
            // Single Selection
            hiddenInputsContainer.innerHTML = `<input type="hidden" name="${nameAttr}" value="${taxId}">`;
            displayEl.innerText = `${taxName} (${rate}%)`;
            badgeEl.innerText = category;

            // Highlight UI row
            document.querySelectorAll('#list_' + modalId + ' .tax-item-row').forEach(row => {
                const checkIcon = row.querySelector('.tax-check-icon');
                if (row.getAttribute('data-id') == taxId) {
                    row.style.borderColor = 'var(--primary)';
                    row.style.background = 'var(--primary-light)';
                    if (checkIcon) checkIcon.style.display = 'flex';
                } else {
                    row.style.borderColor = 'transparent';
                    row.style.background = 'var(--bg-card)';
                    if (checkIcon) checkIcon.style.display = 'none';
                }
            });

            closeModal(modalId);
        } else {
            // Multiple Selection
            const itemRow = document.querySelector('#list_' + modalId + ' .tax-item-row[data-id="' + taxId + '"]');
            const checkIcon = itemRow.querySelector('.tax-check-icon');
            const existingInput = hiddenInputsContainer.querySelector(`input[value="${taxId}"]`);

            if (existingInput) {
                existingInput.remove();
                itemRow.style.borderColor = 'transparent';
                itemRow.style.background = 'var(--bg-card)';
                if (checkIcon) checkIcon.style.display = 'none';
            } else {
                hiddenInputsContainer.insertAdjacentHTML('beforeend', `<input type="hidden" name="${nameAttr}[]" value="${taxId}" class="tax-hidden-val">`);
                itemRow.style.borderColor = 'var(--primary)';
                itemRow.style.background = 'var(--primary-light)';
                if (checkIcon) checkIcon.style.display = 'flex';
            }

            const activeInputs = hiddenInputsContainer.querySelectorAll('input');
            badgeEl.innerText = activeInputs.length > 0 ? `${activeInputs.length} Taxes` : 'Tax';
            displayEl.innerText = activeInputs.length > 0 ? `${activeInputs.length} tax rate(s) selected` : 'Select Tax Rate...';
        }

        // Trigger onchange callback if provided
        const onchangeCallback = component?.getAttribute('data-onchange');
        if (onchangeCallback && typeof window[onchangeCallback] === 'function') {
            window[onchangeCallback]({ taxId, taxName, category, rate });
        }
    }
</script>
@endonce
