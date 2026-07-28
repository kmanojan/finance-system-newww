@props(['name', 'departments' => [], 'selected' => null, 'id' => null, 'onchange' => null])

@php
    $modalId = 'deptSelectorModal_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . uniqid();
    $selectedId = $selected;
    
    // Build tree data structure for recursive rendering
    $buildTree = function($departments, $parentId = null) use (&$buildTree) {
        $branch = [];
        foreach ($departments as $dept) {
            if ($dept->parent_id == $parentId) {
                $children = $buildTree($departments, $dept->id);
                $dept->children = $children;
                $branch[] = $dept;
            }
        }
        return $branch;
    };
    
    $departmentTree = $buildTree($departments);
    
    $selectedName = 'Select Department...';
    $selectedCode = null;
    if ($selectedId) {
        $selDept = collect($departments)->firstWhere('id', $selectedId);
        if ($selDept) {
            $selectedName = $selDept->name;
            $selectedCode = $selDept->code ?? null;
        }
    }
@endphp

<div class="dept-selector-component" id="{{ $id ?? 'component_' . $modalId }}" data-modal-id="{{ $modalId }}" @if($onchange) data-onchange="{{ $onchange }}" @endif>
    <!-- Hidden input -->
    <div id="hidden_input_{{ $modalId }}">
        <input type="hidden" name="{{ $name }}" value="{{ $selectedId ?? '' }}">
    </div>

    <!-- Trigger Button -->
    <button type="button" class="form-control dept-selector-trigger" style="text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0.6rem 0.85rem; border-radius: 10px; background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main); transition: all 0.2s ease;" onclick="openModal('{{ $modalId }}')">
        <div style="display: flex; align-items: center; gap: 0.6rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 90%;">
            <ion-icon name="business-outline" style="color: {{ $selectedId ? 'var(--primary)' : 'var(--text-muted)' }}; font-size: 1.1rem; flex-shrink: 0;"></ion-icon>
            <span id="display_{{ $modalId }}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: {{ $selectedId ? '600' : '400' }}; color: {{ $selectedId ? 'var(--text-heading)' : 'var(--text-muted)' }}; font-size: 0.9rem;">
                {{ $selectedName }}
            </span>
            <span id="code_chip_{{ $modalId }}" class="dept-code-chip" style="display: {{ $selectedCode ? 'inline-block' : 'none' }}; font-size: 0.7rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 4px; background: var(--primary-light); color: var(--primary); text-transform: uppercase;">
                {{ $selectedCode }}
            </span>
        </div>
        <ion-icon name="chevron-down-outline" style="color: var(--text-muted); font-size: 1rem; flex-shrink: 0; transition: transform 0.2s ease;"></ion-icon>
    </button>

    <!-- Modal -->
    <div class="modal-backdrop" id="{{ $modalId }}">
        <div class="modal-card dept-selector-modal" style="max-width: 480px; width: 100%; border-radius: 18px; background: var(--bg-card); border: 1px solid var(--border); box-shadow: var(--shadow-card); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
            
            <!-- Header -->
            <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                        <ion-icon name="business-outline" style="color: var(--primary); font-size: 1.25rem;"></ion-icon>
                    </div>
                    <div>
                        <h3 class="modal-title" style="font-size: 1.1rem; font-weight: 700; color: var(--text-heading); margin: 0;">Select Department</h3>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Choose a department from organizational tree</span>
                    </div>
                </div>
                <button type="button" class="btn-close" style="color: var(--text-muted); font-size: 1.3rem; background: transparent; border: none; cursor: pointer; padding: 0.3rem; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='var(--bg-page)'; this.style.color='var(--text-heading)'" onmouseout="this.style.background='transparent'; this.style.color='var(--text-muted)'" onclick="closeModal('{{ $modalId }}')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            
            <!-- Body -->
            <div class="modal-body" style="padding: 1.25rem 1.5rem; overflow-y: hidden; display: flex; flex-direction: column; gap: 0.85rem; flex: 1;">
                
                <!-- Search Box -->
                <div class="search-input-group" style="position: relative; width: 100%;">
                    <ion-icon name="search-outline" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.15rem; pointer-events: none;"></ion-icon>
                    <input type="text" placeholder="Search department name or code..." id="search_{{ $modalId }}" class="form-control dept-search-input" style="width: 100%; padding: 0.65rem 2.5rem 0.65rem 2.75rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-page); color: var(--text-main); font-size: 0.88rem; transition: border-color 0.2s;" onkeyup="filterDepts('{{ $modalId }}')">
                    <button type="button" id="clear_search_{{ $modalId }}" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; display: none; padding: 0.2rem; align-items: center; justify-content: center; font-size: 1.1rem;" onclick="clearDeptSearch('{{ $modalId }}')">
                        <ion-icon name="close-circle-outline"></ion-icon>
                    </button>
                </div>

                <!-- Search Count Indicator -->
                <div id="search_count_{{ $modalId }}" style="display: none; font-size: 0.75rem; color: var(--text-muted); font-weight: 500; padding: 0 0.2rem;"></div>

                <!-- Tree List Container -->
                <div class="dept-list" id="list_{{ $modalId }}" style="display: flex; flex-direction: column; overflow-y: auto; max-height: 360px; padding-right: 0.35rem; gap: 0.35rem; flex: 1;">
                    
                    <!-- None / Clear Selection -->
                    <label class="dept-item dept-item-none {{ empty($selectedId) ? 'active-item' : '' }}" data-search="" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease; margin: 0; background: {{ empty($selectedId) ? 'var(--primary-light)' : 'transparent' }}; border: 1px solid {{ empty($selectedId) ? 'var(--primary)' : 'transparent' }};" onmouseover="if(!this.classList.contains('active-item')) this.style.background='var(--bg-page)'" onmouseout="if(!this.classList.contains('active-item')) this.style.background='transparent'">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <input type="radio" value="" data-name="Select Department..." data-code="" class="dept-radio_{{ $modalId }}" style="width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;" {{ empty($selectedId) ? 'checked' : '' }} onchange="updateDeptSelection('{{ $modalId }}', '{{ $name }}', this)">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <ion-icon name="remove-circle-outline" style="color: var(--text-muted); font-size: 1.1rem;"></ion-icon>
                                <span style="font-weight: 500; font-size: 0.88rem; color: {{ empty($selectedId) ? 'var(--primary)' : 'var(--text-muted)' }};">
                                    -- None (Clear Selection) --
                                </span>
                            </div>
                        </div>
                        <ion-icon name="checkmark-circle" class="check-icon" style="color: var(--primary); font-size: 1.15rem; flex-shrink: 0; display: {{ empty($selectedId) ? 'block' : 'none' }};"></ion-icon>
                    </label>

                    @php
                        $renderDeptTree = function($nodes, $level = 0) use (&$renderDeptTree, $modalId, $name, $selectedId) {
                            $html = '';
                            foreach ($nodes as $node) {
                                $isSelected = ($node->id == $selectedId);
                                $paddingLeft = ($level * 1.4) + 0.85; // Base padding + level offset
                                $isChild = ($level > 0);
                                
                                $activeClass = $isSelected ? ' active-item' : '';
                                $bgStyle = $isSelected ? 'var(--primary-light)' : 'transparent';
                                $borderStyle = $isSelected ? 'var(--primary)' : 'transparent';
                                $nameColor = $isSelected ? 'var(--primary)' : 'var(--text-heading)';
                                $iconName = !empty($node->children) ? 'business-outline' : ($isChild ? 'git-branch-outline' : 'folder-outline');
                                $iconColor = $isSelected ? 'var(--primary)' : ($isChild ? 'var(--text-muted)' : 'var(--primary)');
                                
                                $html .= '<label class="dept-item' . $activeClass . '" data-id="' . $node->id . '" data-parent-id="' . ($node->parent_id ?? '') . '" data-search="' . htmlspecialchars(strtolower($node->name . ' ' . ($node->code ?? '')), ENT_QUOTES) . '" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem 0.65rem ' . $paddingLeft . 'rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease; margin: 0; background: ' . $bgStyle . '; border: 1px solid ' . $borderStyle . '; position: relative;" onmouseover="if(!this.classList.contains(\'active-item\')) this.style.background=\'var(--bg-page)\'" onmouseout="if(!this.classList.contains(\'active-item\')) this.style.background=\'transparent\'">';
                                
                                $html .= '<div style="display: flex; align-items: center; gap: 0.75rem; overflow: hidden; width: 100%;">';
                                $html .= '<input type="radio" value="' . $node->id . '" data-name="' . htmlspecialchars($node->name, ENT_QUOTES) . '" data-code="' . htmlspecialchars($node->code ?? '', ENT_QUOTES) . '" class="dept-radio_' . $modalId . '" style="width: 1.05rem; height: 1.05rem; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;" ' . ($isSelected ? 'checked' : '') . ' onchange="updateDeptSelection(\'' . $modalId . '\', \'' . $name . '\', this)">';
                                
                                if ($isChild) {
                                    $html .= '<span style="color: var(--border); font-family: monospace; font-size: 0.85rem; user-select: none; flex-shrink: 0;">└─</span>';
                                }

                                $html .= '<ion-icon name="' . $iconName . '" style="color: ' . $iconColor . '; font-size: 1.1rem; flex-shrink: 0;"></ion-icon>';
                                
                                $html .= '<div class="dept-info" style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;">';
                                $html .= '<span style="font-weight: ' . ($isSelected || !$isChild ? '600' : '500') . '; font-size: 0.88rem; color: ' . $nameColor . '; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . htmlspecialchars($node->name, ENT_QUOTES) . '</span>';
                                
                                if (!empty($node->code)) {
                                    $html .= '<span style="font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 4px; background: ' . ($isSelected ? 'var(--primary)' : 'var(--bg-page)') . '; color: ' . ($isSelected ? '#ffffff' : 'var(--text-muted)') . '; border: 1px solid var(--border-light); text-transform: uppercase; flex-shrink: 0;">' . htmlspecialchars($node->code, ENT_QUOTES) . '</span>';
                                }

                                if (!empty($node->children)) {
                                    $html .= '<span style="font-size: 0.68rem; padding: 0.1rem 0.4rem; border-radius: 10px; background: var(--bg-page); color: var(--text-muted); border: 1px solid var(--border-light); flex-shrink: 0;">' . count($node->children) . ' sub</span>';
                                }
                                $html .= '</div>';
                                $html .= '</div>';

                                $html .= '<ion-icon name="checkmark-circle" class="check-icon" style="color: var(--primary); font-size: 1.15rem; flex-shrink: 0; margin-left: 0.5rem; display: ' . ($isSelected ? 'block' : 'none') . ';"></ion-icon>';
                                
                                $html .= '</label>';
                                
                                if (!empty($node->children)) {
                                    $html .= $renderDeptTree($node->children, $level + 1);
                                }
                            }
                            return $html;
                        };
                    @endphp
                    {!! $renderDeptTree($departmentTree) !!}
                </div>

                <!-- Empty State -->
                <div id="empty_{{ $modalId }}" style="display: none; padding: 2rem 1rem; text-align: center; color: var(--text-muted);">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-page); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                        <ion-icon name="search-outline" style="font-size: 1.5rem; color: var(--text-muted);"></ion-icon>
                    </div>
                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-heading); margin-bottom: 0.25rem;">No departments found</div>
                    <p style="font-size: 0.8rem; margin: 0 0 1rem 0;">No department matches your search term.</p>
                    <button type="button" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.35rem 0.85rem; border-radius: 8px;" onclick="clearDeptSearch('{{ $modalId }}')">Clear Search</button>
                </div>

            </div>
        </div>
    </div>
</div>

@once
<style>
    .dept-list::-webkit-scrollbar { width: 6px; }
    .dept-list::-webkit-scrollbar-track { background: transparent; }
    .dept-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    .dept-list::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

    .dept-selector-trigger:hover {
        border-color: var(--primary) !important;
    }

    .dept-search-input:focus {
        border-color: var(--primary) !important;
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-light) !important;
    }
</style>
<script>
    function filterDepts(modalId) {
        const input = document.getElementById('search_' + modalId).value.toLowerCase().trim();
        const list = document.getElementById('list_' + modalId);
        const clearBtn = document.getElementById('clear_search_' + modalId);
        const countEl = document.getElementById('search_count_' + modalId);
        const emptyEl = document.getElementById('empty_' + modalId);
        const items = list.querySelectorAll('.dept-item[data-search]');
        
        if (input.length > 0) {
            clearBtn.style.display = 'flex';
        } else {
            clearBtn.style.display = 'none';
            countEl.style.display = 'none';
        }

        let matchCount = 0;
        const matchedParentIds = new Set();

        // First pass: find all directly matching items and record their parent IDs
        items.forEach(item => {
            const searchData = item.getAttribute('data-search') || '';
            if (input === '' || searchData.includes(input)) {
                item.setAttribute('data-matched', 'true');
                if (input !== '' && item.getAttribute('data-id')) {
                    matchCount++;
                    let parentId = item.getAttribute('data-parent-id');
                    while (parentId) {
                        matchedParentIds.add(parentId);
                        const parentEl = list.querySelector(`.dept-item[data-id="${parentId}"]`);
                        parentId = parentEl ? parentEl.getAttribute('data-parent-id') : null;
                    }
                }
            } else {
                item.setAttribute('data-matched', 'false');
            }
        });

        // Second pass: show matching items or items that are parents of matching children
        items.forEach(item => {
            const isMatched = item.getAttribute('data-matched') === 'true';
            const itemId = item.getAttribute('data-id');
            const isParentOfMatch = itemId && matchedParentIds.has(itemId);

            if (input === '' || isMatched || isParentOfMatch) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });

        if (input !== '') {
            countEl.innerText = `${matchCount} department${matchCount === 1 ? '' : 's'} found`;
            countEl.style.display = 'block';
            emptyEl.style.display = matchCount === 0 ? 'block' : 'none';
            if (matchCount === 0) list.style.display = 'none';
            else list.style.display = 'flex';
        } else {
            emptyEl.style.display = 'none';
            list.style.display = 'flex';
        }
    }

    function clearDeptSearch(modalId) {
        const input = document.getElementById('search_' + modalId);
        if (input) {
            input.value = '';
            filterDepts(modalId);
            input.focus();
        }
    }

    function updateDeptSelection(modalId, inputName, clickedElement) {
        const list = document.getElementById('list_' + modalId);
        const labels = list.querySelectorAll('.dept-item');
        const radios = document.querySelectorAll('.dept-radio_' + modalId);
        
        radios.forEach(r => {
            if (r !== clickedElement) r.checked = false;
        });

        labels.forEach(label => {
            const radio = label.querySelector('input[type="radio"]');
            const checkIcon = label.querySelector('.check-icon');
            if (radio && radio.checked) {
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
        
        const hiddenContainer = document.getElementById('hidden_input_' + modalId);
        const display = document.getElementById('display_' + modalId);
        const codeChip = document.getElementById('code_chip_' + modalId);
        
        hiddenContainer.innerHTML = '';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName;
        input.value = clickedElement.value;
        hiddenContainer.appendChild(input);
        
        if (clickedElement.value === '') {
            display.innerText = 'Select Department...';
            display.style.color = 'var(--text-muted)';
            display.style.fontWeight = '400';
            if (codeChip) codeChip.style.display = 'none';
        } else {
            display.innerText = clickedElement.dataset.name;
            display.style.color = 'var(--text-heading)';
            display.style.fontWeight = '600';
            if (codeChip) {
                if (clickedElement.dataset.code) {
                    codeChip.innerText = clickedElement.dataset.code;
                    codeChip.style.display = 'inline-block';
                } else {
                    codeChip.style.display = 'none';
                }
            }
        }
        
        const container = document.querySelector('.dept-selector-component[data-modal-id="' + modalId + '"]');
        if (container && container.hasAttribute('data-onchange')) {
            const onchangeCallback = container.getAttribute('data-onchange');
            if (onchangeCallback) {
                eval(onchangeCallback);
            }
        }
        
        closeModal(modalId);
    }

    window.setDepartmentSelectorValue = function(containerId, value) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const modalId = container.getAttribute('data-modal-id');
        
        const hiddenContainer = document.getElementById('hidden_input_' + modalId);
        if (!hiddenContainer) return;
        
        const inputName = hiddenContainer.querySelector('input') ? hiddenContainer.querySelector('input').name : 'parent_id';
        hiddenContainer.innerHTML = '<input type="hidden" name="' + inputName + '" value="' + value + '">';
        
        const display = document.getElementById('display_' + modalId);
        const codeChip = document.getElementById('code_chip_' + modalId);
        const radio = document.querySelector('.dept-radio_' + modalId + '[value="' + value + '"]');
        const list = document.getElementById('list_' + modalId);
        
        // Reset all items
        document.querySelectorAll('.dept-radio_' + modalId).forEach(r => r.checked = false);
        if (list) {
            list.querySelectorAll('.dept-item').forEach(label => {
                label.classList.remove('active-item');
                label.style.background = 'transparent';
                label.style.borderColor = 'transparent';
                const checkIcon = label.querySelector('.check-icon');
                if (checkIcon) checkIcon.style.display = 'none';
            });
        }
        
        if (value === '' || !radio) {
            display.innerText = '-- None (Top Level) --';
            display.style.color = 'var(--text-muted)';
            display.style.fontWeight = '400';
            if (codeChip) codeChip.style.display = 'none';

            const clearLabel = list ? list.querySelector('.dept-item-none') : null;
            if (clearLabel) {
                clearLabel.classList.add('active-item');
                clearLabel.style.background = 'var(--primary-light)';
                clearLabel.style.borderColor = 'var(--primary)';
                const checkIcon = clearLabel.querySelector('.check-icon');
                if (checkIcon) checkIcon.style.display = 'block';
                const clearRadio = clearLabel.querySelector('input[type="radio"]');
                if (clearRadio) clearRadio.checked = true;
            }
        } else {
            radio.checked = true;
            display.innerText = radio.dataset.name;
            display.style.color = 'var(--text-heading)';
            display.style.fontWeight = '600';
            if (codeChip) {
                if (radio.dataset.code) {
                    codeChip.innerText = radio.dataset.code;
                    codeChip.style.display = 'inline-block';
                } else {
                    codeChip.style.display = 'none';
                }
            }

            const activeLabel = radio.closest('.dept-item');
            if (activeLabel) {
                activeLabel.classList.add('active-item');
                activeLabel.style.background = 'var(--primary-light)';
                activeLabel.style.borderColor = 'var(--primary)';
                const checkIcon = activeLabel.querySelector('.check-icon');
                if (checkIcon) checkIcon.style.display = 'block';
            }
        }
    };
</script>
@endonce
