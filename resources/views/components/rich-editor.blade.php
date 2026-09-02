@props([
    'name',
    'id' => null,
    'value' => '',
    'placeholder' => 'Type / for commands (/loan, /transaction, /party, /employee)...',
    'height' => '130px',
    'required' => false,
    'class' => '',
    'enableSlash' => true,
])

@php
    $editorId = $id ?? 'rich_editor_' . str_replace(['[', ']', '.'], ['_', '', '_'], $name) . '_' . substr(md5(uniqid()), 0, 6);
    $initialValue = old($name, $value ?? $slot);
@endphp

<div class="rich-editor-wrapper" id="wrap_{{ $editorId }}" style="position: relative;">
    <textarea 
        name="{{ $name }}" 
        id="{{ $editorId }}" 
        class="rich-editor-textarea {{ $class }}" 
        style="min-height: {{ $height }}; display: none;"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
    >{!! $initialValue !!}</textarea>

    <!-- Slash Command Menu Dropdown -->
    <div id="slash_menu_{{ $editorId }}" class="slash-menu-popup" style="display: none;">
        <!-- Header -->
        <div class="slash-menu-header">
            <span class="slash-menu-title" id="slash_title_{{ $editorId }}">
                <ion-icon name="flash-outline" style="vertical-align: middle;"></ion-icon> Slash Commands
            </span>
            <span class="slash-menu-hint">↑↓ navigate &bull; ↵ select &bull; Esc close</span>
        </div>

        <!-- Prominent Always-Visible Search Bar -->
        <div id="slash_search_wrap_{{ $editorId }}" class="slash-search-bar" style="display: block !important;">
            <div class="slash-search-inner">
                <ion-icon name="search-outline" class="slash-search-icon"></ion-icon>
                <input 
                    type="text" 
                    id="slash_search_input_{{ $editorId }}" 
                    class="slash-search-input" 
                    placeholder="Search loans, transactions, parties, employees..." 
                    autocomplete="off"
                />
            </div>
        </div>

        <!-- Category Filter Tabs -->
        <div class="slash-tabs-bar" id="slash_tabs_{{ $editorId }}">
            <button type="button" class="slash-tab-pill active" data-category="all">
                <ion-icon name="grid-outline"></ion-icon> All
            </button>
            <button type="button" class="slash-tab-pill" data-category="loan">
                <ion-icon name="card-outline"></ion-icon> /loan
            </button>
            <button type="button" class="slash-tab-pill" data-category="transaction">
                <ion-icon name="receipt-outline"></ion-icon> /transaction
            </button>
            <button type="button" class="slash-tab-pill" data-category="party">
                <ion-icon name="business-outline"></ion-icon> /party
            </button>
            <button type="button" class="slash-tab-pill" data-category="employee">
                <ion-icon name="people-outline"></ion-icon> /employee
            </button>
        </div>

        <!-- Results List -->
        <div class="slash-menu-list" id="slash_list_{{ $editorId }}">
            <!-- Dynamic Items Injected Here -->
        </div>
    </div>
</div>

<style>
/* CKEditor Custom Theme & Wrapper */
#wrap_{{ $editorId }} .ck.ck-editor__main > .ck-editor__editable {
    min-height: {{ $height }};
    background: #1a1d27 !important;
    color: #cbd5e1 !important;
    border-color: rgba(255,255,255,0.12) !important;
    border-bottom-left-radius: 8px !important;
    border-bottom-right-radius: 8px !important;
    font-family: inherit !important;
    font-size: 0.9rem;
    line-height: 1.5;
    padding: 0.75rem 1rem;
}
#wrap_{{ $editorId }} .ck.ck-editor__main > .ck-editor__editable:focus {
    border-color: #8b5cf6 !important;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2) !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar {
    background: #202431 !important;
    border-color: rgba(255,255,255,0.12) !important;
    border-top-left-radius: 8px !important;
    border-top-right-radius: 8px !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button {
    color: #cbd5e1 !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button:hover,
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button.ck-on {
    background: rgba(139, 92, 246, 0.2) !important;
    color: #8b5cf6 !important;
}
#wrap_{{ $editorId }} .ck.ck-dropdown__panel {
    background: #202431 !important;
    border-color: rgba(255,255,255,0.15) !important;
}

/* Slash Command Menu Popover */
.slash-menu-popup {
    position: absolute !important;
    z-index: 9999999 !important;
    background: #202431 !important;
    border: 1px solid rgba(255, 255, 255, 0.22) !important;
    border-radius: 12px !important;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.7) !important;
    width: 440px !important;
    max-width: 94vw !important;
    max-height: 420px !important;
    font-family: inherit !important;
    backdrop-filter: blur(12px) !important;
    overflow: hidden !important;
    flex-direction: column !important;
}
.slash-menu-header {
    padding: 10px 14px !important;
    background: #141721 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    font-size: 0.75rem !important;
}
.slash-menu-title {
    font-weight: 700 !important;
    color: #8b5cf6 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}
.slash-menu-hint {
    color: #94a3b8 !important;
    font-size: 0.7rem !important;
}

/* Search Bar (Always Visible) */
.slash-search-bar {
    padding: 8px 12px !important;
    background: #1a1d27 !important;
    border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    display: block !important;
}
.slash-search-inner {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
}
.slash-search-icon {
    position: absolute !important;
    left: 10px !important;
    color: #8b5cf6 !important;
    font-size: 1.1rem !important;
    pointer-events: none !important;
}
.slash-search-input {
    width: 100% !important;
    background: #0f111a !important;
    border: 1px solid rgba(139, 92, 246, 0.4) !important;
    color: #ffffff !important;
    border-radius: 8px !important;
    padding: 8px 12px 8px 36px !important;
    font-size: 0.88rem !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: all 0.15s ease !important;
}
.slash-search-input:focus {
    border-color: #8b5cf6 !important;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3) !important;
}
.slash-search-input::placeholder {
    color: #64748b !important;
}

/* Tabs Bar */
.slash-tabs-bar {
    display: flex !important;
    gap: 6px !important;
    padding: 6px 12px !important;
    background: rgba(0,0,0,0.25) !important;
    border-bottom: 1px solid rgba(255,255,255,0.08) !important;
    overflow-x: auto !important;
}
.slash-tab-pill {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
    color: #94a3b8 !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    padding: 4px 10px !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    white-space: nowrap !important;
    transition: all 0.15s ease !important;
}
.slash-tab-pill:hover {
    background: rgba(255,255,255,0.12) !important;
    color: #ffffff !important;
}
.slash-tab-pill.active {
    background: #8b5cf6 !important;
    border-color: #8b5cf6 !important;
    color: #ffffff !important;
}

/* List Items */
.slash-menu-list {
    padding: 8px !important;
    overflow-y: auto !important;
    max-height: 250px !important;
    flex: 1 !important;
}
.slash-menu-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 8px 10px !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    font-size: 0.84rem !important;
    color: #cbd5e1 !important;
    transition: all 0.15s ease !important;
    border-left: 3px solid transparent !important;
}
.slash-menu-item:hover, .slash-menu-item.active {
    background: rgba(139, 92, 246, 0.18) !important;
    color: #ffffff !important;
    border-left-color: #8b5cf6 !important;
}
.slash-menu-item-icon {
    width: 34px !important;
    height: 34px !important;
    border-radius: 6px !important;
    background: rgba(255, 255, 255, 0.06) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1.15rem !important;
    flex-shrink: 0 !important;
}
.slash-menu-item-content {
    flex: 1 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.slash-menu-item-title {
    font-weight: 600 !important;
    color: #f8fafc !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 6px !important;
}
.slash-menu-item-sub {
    font-size: 0.74rem !important;
    color: #94a3b8 !important;
    margin-top: 2px !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}
.slash-menu-empty {
    padding: 1.5rem 1rem !important;
    text-align: center !important;
    color: #94a3b8 !important;
    font-size: 0.82rem !important;
}
.slash-menu-loading {
    padding: 1.5rem 1rem !important;
    text-align: center !important;
    color: #8b5cf6 !important;
    font-size: 0.82rem !important;
}

/* Mention Chips Styling */
.mention-chip {
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    padding: 2px 8px !important;
    border-radius: 6px !important;
    font-size: 0.85em !important;
    font-weight: 600 !important;
    line-height: 1.4 !important;
    vertical-align: middle !important;
    text-decoration: none !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}
.mention-chip.mention-loan {
    background: rgba(139, 92, 246, 0.14) !important;
    color: #8b5cf6 !important;
    border: 1px solid rgba(139, 92, 246, 0.35) !important;
}
.mention-chip.mention-loan:hover {
    background: rgba(139, 92, 246, 0.25) !important;
    border-color: #8b5cf6 !important;
    color: #7c3aed !important;
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3) !important;
    text-decoration: underline !important;
}
.mention-chip.mention-transaction {
    background: rgba(245, 158, 11, 0.14) !important;
    color: #f59e0b !important;
    border: 1px solid rgba(245, 158, 11, 0.35) !important;
}
.mention-chip.mention-transaction:hover {
    background: rgba(245, 158, 11, 0.25) !important;
    border-color: #f59e0b !important;
    color: #d97706 !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3) !important;
    text-decoration: underline !important;
}
.mention-chip.mention-party {
    background: rgba(59, 130, 246, 0.14) !important;
    color: #3b82f6 !important;
    border: 1px solid rgba(59, 130, 246, 0.35) !important;
}
.mention-chip.mention-party:hover {
    background: rgba(59, 130, 246, 0.25) !important;
    border-color: #3b82f6 !important;
    color: #2563eb !important;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3) !important;
}
.mention-chip.mention-employee {
    background: rgba(16, 185, 129, 0.14) !important;
    color: #10b981 !important;
    border: 1px solid rgba(16, 185, 129, 0.35) !important;
}
.mention-chip.mention-employee:hover {
    background: rgba(16, 185, 129, 0.25) !important;
    border-color: #10b981 !important;
    color: #059669 !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initRichEditor("{{ $editorId }}", {
        enableSlash: {{ $enableSlash ? 'true' : 'false' }},
        placeholder: "{{ $placeholder }}"
    });
});

if (typeof window.__richEditors === 'undefined') {
    window.__richEditors = {};
}

// On-demand fetcher
if (typeof window.__fetchMentionsServer === 'undefined') {
    window.__fetchMentionsServer = function(type, query, limit = 10) {
        const url = `/rich-editor/mentions?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query || '')}&limit=${limit}`;
        return fetch(url)
            .then(res => {
                if (!res.ok) return fetch(`/api/rich-editor/mentions?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query || '')}&limit=${limit}`).then(r => r.json());
                return res.json();
            })
            .catch(err => {
                console.warn('CKeditorController mentions fetch error:', err);
                return { items: [], loans: [], parties: [], employees: [], transactions: [] };
            });
    };
}

function initRichEditor(editorId, config) {
    const el = document.getElementById(editorId);
    if (!el || typeof ClassicEditor === 'undefined') return;

    if (window.__richEditors[editorId]) {
        try { window.__richEditors[editorId].destroy(); } catch(e) {}
    }

    ClassicEditor
        .create(el, {
            toolbar: [
                'heading', '|', 
                'bold', 'italic', 'underline', 'bulletedList', 'numberedList', 'blockQuote', '|', 
                'link', 'insertTable', '|', 
                'undo', 'redo'
            ],
            placeholder: config.placeholder || 'Type / for commands (/loan, /transaction, /party, /employee)...'
        })
        .then(editor => {
            window.__richEditors[editorId] = editor;
            
            editor.model.document.on('change:data', () => {
                el.value = editor.getData();
            });

            const form = el.closest('form');
            if (form) {
                form.addEventListener('submit', () => {
                    el.value = editor.getData();
                });
            }

            if (config.enableSlash) {
                setupSlashCommands(editor, editorId);
            }
        })
        .catch(err => console.error('RichEditor init error:', err));
}

// Setup Slash Command Autocomplete Logic
function setupSlashCommands(editor, editorId) {
    const menu = document.getElementById('slash_menu_' + editorId);
    const list = document.getElementById('slash_list_' + editorId);
    const titleEl = document.getElementById('slash_title_' + editorId);
    const searchInput = document.getElementById('slash_search_input_' + editorId);
    const tabsBar = document.getElementById('slash_tabs_' + editorId);
    const wrapper = document.getElementById('wrap_' + editorId);
    if (!menu || !list || !wrapper) return;

    let isMenuOpen = false;
    let selectedIndex = 0;
    let currentItems = [];
    let activeCategory = 'all'; // 'all', 'loan', 'transaction', 'party', 'employee'
    let lastSlashLength = 1;
    let searchDebounceTimer = null;

    function closeMenu() {
        menu.style.display = 'none';
        isMenuOpen = false;
        selectedIndex = 0;
        activeCategory = 'all';
        if (searchInput) searchInput.value = '';
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    }

    function openMenu(x, y) {
        menu.style.display = 'flex';
        isMenuOpen = true;
        const rect = wrapper.getBoundingClientRect();
        let top = (y - rect.top) + 25;
        let left = (x - rect.left);
        if (left + 440 > rect.width) {
            left = Math.max(10, rect.width - 450);
        }
        menu.style.top = Math.max(10, top) + 'px';
        menu.style.left = Math.max(10, left) + 'px';
    }

    function updateTabsUI(cat) {
        activeCategory = cat;
        if (tabsBar) {
            tabsBar.querySelectorAll('.slash-tab-pill').forEach(btn => {
                if (btn.dataset.category === cat) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }
    }

    function renderLoading(headerTitle) {
        if (titleEl && headerTitle) {
            titleEl.innerHTML = `<ion-icon name="flash-outline" style="vertical-align: middle;"></ion-icon> ${headerTitle}`;
        }
        list.innerHTML = `<div class="slash-menu-loading"><ion-icon name="sync-outline" class="spin" style="vertical-align:middle;"></ion-icon> Loading records...</div>`;
    }

    function renderItems(items, headerTitle) {
        currentItems = items;
        selectedIndex = 0;
        list.innerHTML = '';
        if (titleEl && headerTitle) {
            titleEl.innerHTML = `<ion-icon name="flash-outline" style="vertical-align: middle;"></ion-icon> ${headerTitle}`;
        }

        if (!items || items.length === 0) {
            list.innerHTML = `<div class="slash-menu-empty">No matching records found</div>`;
            return;
        }

        items.forEach((item, index) => {
            const itemEl = document.createElement('div');
            itemEl.className = 'slash-menu-item' + (index === 0 ? ' active' : '');
            
            if (item.type === 'command') {
                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color: ${item.iconColor}; background: ${item.bgColor};">
                        <ion-icon name="${item.icon}"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">${item.title}</div>
                        <div class="slash-menu-item-sub">${item.subtitle}</div>
                    </div>
                `;
            } else if (item.type === 'loan') {
                const codeBadge = `<span style="font-size:0.75rem; font-weight:700; color:#8b5cf6; background:rgba(139,92,246,0.15); padding:1px 6px; border-radius:4px;">${item.loan_code || 'LN-' + item.id}</span>`;
                const statusBadge = `<span style="font-size:0.68rem; padding:1px 6px; border-radius:4px; font-weight:600; background:${item.status === 'settled' ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309'}">${item.status}</span>`;
                const descSnippet = item.purpose ? `<span style="color:#94a3b8; font-size:0.72rem;"> &bull; ${item.purpose.substring(0, 35)}${item.purpose.length > 35 ? '...' : ''}</span>` : '';

                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:#8b5cf6; background:rgba(139,92,246,0.15);">
                        <ion-icon name="card-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>${codeBadge} ${item.lender_name}</span>
                            ${statusBadge}
                        </div>
                        <div class="slash-menu-item-sub">
                            <strong style="color:#f8fafc;">${item.currency} ${Number(item.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                            ${descSnippet}
                        </div>
                    </div>
                `;
            } else if (item.type === 'transaction') {
                const isInc = item.type_name === 'income' || item.type === 'income';
                const isExp = item.type_name === 'expense' || item.type === 'expense';
                const typeColor = isInc ? '#10b981' : (isExp ? '#ef4444' : '#3b82f6');
                const typeBg = isInc ? 'rgba(16,185,129,0.15)' : (isExp ? 'rgba(239,68,68,0.15)' : 'rgba(59,130,246,0.15)');
                const typeLabel = (item.type || 'TXN').toUpperCase();
                const refSnippet = item.reference_no ? `<span style="font-size:0.72rem; color:#94a3b8; background:rgba(255,255,255,0.06); padding:1px 5px; border-radius:4px;">${item.reference_no}</span>` : '';
                const dateSnippet = item.transaction_date ? `<span style="color:#94a3b8; font-size:0.72rem;"> &bull; ${item.transaction_date}</span>` : '';

                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:${typeColor}; background:${typeBg};">
                        <ion-icon name="receipt-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>
                                <span style="font-size:0.7rem; font-weight:700; color:${typeColor}; background:${typeBg}; padding:1px 5px; border-radius:4px; margin-right:4px;">${typeLabel}</span>
                                ${item.description || 'Transaction #' + item.id}
                            </span>
                            ${refSnippet}
                        </div>
                        <div class="slash-menu-item-sub">
                            <strong style="color:${typeColor};">${isInc ? '+' : (isExp ? '-' : '')}${item.currency} ${Number(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                            ${item.category_name ? `<span style="color:#94a3b8; font-size:0.72rem;"> &bull; ${item.category_name}</span>` : ''}
                            ${dateSnippet}
                        </div>
                    </div>
                `;
            } else if (item.type === 'party') {
                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:#3b82f6; background:rgba(59,130,246,0.15);">
                        <ion-icon name="business-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>${item.name}</span>
                            ${item.party_types ? `<span style="font-size:0.68rem; color:#3b82f6; background:rgba(59,130,246,0.1); padding:1px 5px; border-radius:4px;">${item.party_types}</span>` : ''}
                        </div>
                        <div class="slash-menu-item-sub">${item.contact_person || item.phone || item.email || 'Master Party'}</div>
                    </div>
                `;
            } else if (item.type === 'employee') {
                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:#10b981; background:rgba(16,185,129,0.15);">
                        <ion-icon name="person-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>${item.name}</span>
                            ${item.code ? `<span style="font-size:0.7rem; color:#94a3b8;">${item.code}</span>` : ''}
                        </div>
                        <div class="slash-menu-item-sub">${item.job_position || 'Employee'}</div>
                    </div>
                `;
            }

            itemEl.addEventListener('mouseenter', () => {
                selectedIndex = index;
                updateActiveItem();
            });

            itemEl.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                selectItem(item);
            });

            list.appendChild(itemEl);
        });
    }

    function updateActiveItem() {
        const children = list.querySelectorAll('.slash-menu-item');
        children.forEach((child, i) => {
            if (i === selectedIndex) {
                child.classList.add('active');
                child.scrollIntoView({ block: 'nearest' });
            } else {
                child.classList.remove('active');
            }
        });
    }

    function selectItem(item) {
        // When clicking a category command option
        if (item.type === 'command') {
            updateTabsUI(item.action);
            fetchCategoryData(item.action, '');
            if (searchInput) {
                searchInput.value = '';
                setTimeout(() => searchInput.focus(), 30);
            }
            return;
        }

        // Insert final selected item chip into CKEditor
        let htmlToInsert = '';
        if (item.type === 'loan') {
            const code = item.loan_code || ('LN-' + item.id);
            htmlToInsert = `<a href="/loans/${item.id}" target="_blank" rel="noopener noreferrer" class="mention-chip mention-loan" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(139,92,246,0.12); color:#8b5cf6; font-weight:600; text-decoration:none; border:1px solid rgba(139,92,246,0.25);" contenteditable="false"><ion-icon name="link-outline"></ion-icon> ${code}: ${item.lender_name} (${item.currency} ${Number(item.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2})})</a>&nbsp;`;
        } else if (item.type === 'transaction') {
            const isInc = item.type === 'income';
            const sign = isInc ? '+' : (item.type === 'expense' ? '-' : '');
            const ref = item.reference_no ? ` [${item.reference_no}]` : '';
            htmlToInsert = `<a href="/transactions" target="_blank" rel="noopener noreferrer" class="mention-chip mention-transaction" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(245,158,11,0.14); color:#f59e0b; font-weight:600; text-decoration:none; border:1px solid rgba(245,158,11,0.35);" contenteditable="false"><ion-icon name="receipt-outline"></ion-icon> TXN-${item.id}${ref}: ${item.type.toUpperCase()} ${sign}${item.currency} ${Number(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</a>&nbsp;`;
        } else if (item.type === 'party') {
            htmlToInsert = `<span class="mention-chip mention-party" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(59,130,246,0.12); color:#3b82f6; font-weight:600; border:1px solid rgba(59,130,246,0.25);" contenteditable="false"><ion-icon name="business-outline"></ion-icon> ${item.name}</span>&nbsp;`;
        } else if (item.type === 'employee') {
            htmlToInsert = `<span class="mention-chip mention-employee" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(16,185,129,0.12); color:#10b981; font-weight:600; border:1px solid rgba(16,185,129,0.25);" contenteditable="false"><ion-icon name="person-outline"></ion-icon> ${item.name}${item.code ? ' (' + item.code + ')' : ''}</span>&nbsp;`;
        }

        if (htmlToInsert) {
            editor.model.change(writer => {
                const selection = editor.model.document.selection;
                const position = selection.getFirstPosition();
                
                const range = editor.model.createRange(
                    writer.createPositionAt(position.parent, Math.max(0, position.offset - lastSlashLength)),
                    position
                );
                writer.remove(range);

                const viewFragment = editor.data.processor.toView(htmlToInsert);
                const modelFragment = editor.data.toModel(viewFragment);
                editor.model.insertContent(modelFragment, editor.model.document.selection);
            });
            editor.editing.view.focus();
        }

        closeMenu();
    }

    // Show root commands or fetch on demand
    function fetchCategoryData(category, query) {
        updateTabsUI(category);
        const titles = {
            all: 'Slash Commands & Search',
            loan: 'Loans (/loan - 10 Results)',
            transaction: 'Transactions (/transaction - 10 Results)',
            party: 'Parties (/party - 10 Results)',
            employee: 'Employees (/employee - 10 Results)'
        };
        const placeholders = {
            all: 'Search loans, transactions, parties, employees...',
            loan: 'Search loan by name, code, amount, purpose...',
            transaction: 'Search transaction by description, ref no, amount...',
            party: 'Search party by name, contact, phone, email...',
            employee: 'Search employee by name, code, position...'
        };

        if (searchInput) {
            searchInput.placeholder = placeholders[category] || 'Type to search...';
            if (query && searchInput.value !== query) {
                searchInput.value = query;
            }
        }

        // If category is 'all' and no query, show command labels
        if (category === 'all' && (!query || query.trim() === '')) {
            const commands = [
                {
                    type: 'command',
                    action: 'loan',
                    title: '/loan',
                    subtitle: 'Search & link a Loan (Code, Lender, Amount, Purpose)',
                    icon: 'card-outline',
                    iconColor: '#8b5cf6',
                    bgColor: 'rgba(139, 92, 246, 0.15)'
                },
                {
                    type: 'command',
                    action: 'transaction',
                    title: '/transaction',
                    subtitle: 'Search & link a Transaction (Income, Expense, Ref No)',
                    icon: 'receipt-outline',
                    iconColor: '#f59e0b',
                    bgColor: 'rgba(245, 158, 11, 0.15)'
                },
                {
                    type: 'command',
                    action: 'party',
                    title: '/party',
                    subtitle: 'Search & mention a Party (Client / Vendor / Partner)',
                    icon: 'business-outline',
                    iconColor: '#3b82f6',
                    bgColor: 'rgba(59, 130, 246, 0.15)'
                },
                {
                    type: 'command',
                    action: 'employee',
                    title: '/employee',
                    subtitle: 'Search & mention an Employee / Team Member',
                    icon: 'people-outline',
                    iconColor: '#10b981',
                    bgColor: 'rgba(16, 185, 129, 0.15)'
                }
            ];
            renderItems(commands, 'Slash Commands');
            return;
        }

        renderLoading(titles[category] || 'Searching...');

        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            window.__fetchMentionsServer(category === 'all' ? 'loan' : category, query, 10).then(data => {
                let items = [];
                if (category === 'loan') {
                    items = (data.loans || data.items || []).map(l => ({ ...l, type: 'loan' }));
                } else if (category === 'transaction') {
                    items = (data.transactions || data.items || []).map(t => ({ ...t, type: 'transaction' }));
                } else if (category === 'party') {
                    items = (data.parties || data.items || []).map(p => ({ ...p, type: 'party' }));
                } else if (category === 'employee') {
                    items = (data.employees || data.items || []).map(e => ({ ...e, type: 'employee' }));
                } else if (category === 'all') {
                    const l = (data.loans || []).map(x => ({ ...x, type: 'loan' }));
                    const tx = (data.transactions || []).map(x => ({ ...x, type: 'transaction' }));
                    const p = (data.parties || []).map(x => ({ ...x, type: 'party' }));
                    const em = (data.employees || []).map(x => ({ ...x, type: 'employee' }));
                    items = [...l, ...tx, ...p, ...em];
                }
                renderItems(items, titles[category]);
            });
        }, query ? 150 : 0);
    }

    // Tabs Click
    if (tabsBar) {
        tabsBar.addEventListener('click', (e) => {
            const btn = e.target.closest('.slash-tab-pill');
            if (!btn) return;
            const cat = btn.dataset.category;
            const q = searchInput ? searchInput.value : '';
            fetchCategoryData(cat, q);
            if (searchInput) searchInput.focus();
        });
    }

    // Search Input Realtime Listener
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value;
            fetchCategoryData(activeCategory, query);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % Math.max(1, currentItems.length);
                updateActiveItem();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + currentItems.length) % Math.max(1, currentItems.length);
                updateActiveItem();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentItems[selectedIndex]) {
                    selectItem(currentItems[selectedIndex]);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeMenu();
                editor.editing.view.focus();
            }
        });
    }

    // Keydown for Menu Navigation in Editor
    editor.editing.view.document.on('keydown', (evt, data) => {
        if (!isMenuOpen) return;

        if (data.keyCode === 38) { // Up
            data.preventDefault();
            evt.stop();
            selectedIndex = (selectedIndex - 1 + currentItems.length) % Math.max(1, currentItems.length);
            updateActiveItem();
        } else if (data.keyCode === 40) { // Down
            data.preventDefault();
            evt.stop();
            selectedIndex = (selectedIndex + 1) % Math.max(1, currentItems.length);
            updateActiveItem();
        } else if (data.keyCode === 13 || data.keyCode === 9) { // Enter or Tab
            data.preventDefault();
            evt.stop();
            if (currentItems[selectedIndex]) {
                selectItem(currentItems[selectedIndex]);
            }
        } else if (data.keyCode === 27) { // Escape
            data.preventDefault();
            evt.stop();
            closeMenu();
        }
    }, { priority: 'high' });

    // Monitor typing in Editor
    editor.model.document.on('change:data', () => {
        const selection = editor.model.document.selection;
        if (!selection.isCollapsed) {
            closeMenu();
            return;
        }

        const position = selection.getFirstPosition();
        const textNode = position.parent.getChild(0);
        if (!textNode || !textNode.data) {
            closeMenu();
            return;
        }

        const fullText = textNode.data.substring(0, position.offset);
        const lastSlashIndex = fullText.lastIndexOf('/');

        if (lastSlashIndex === -1 || (lastSlashIndex > 0 && fullText[lastSlashIndex - 1] !== ' ' && fullText[lastSlashIndex - 1] !== '\n')) {
            closeMenu();
            return;
        }

        const slashQuery = fullText.substring(lastSlashIndex); // e.g. "/" or "/loan" or "/transaction"
        lastSlashLength = slashQuery.length;

        // Position popup at caret
        try {
            const domSelection = window.getSelection();
            if (domSelection && domSelection.rangeCount > 0) {
                const domRange = domSelection.getRangeAt(0);
                const rect = domRange.getBoundingClientRect();
                openMenu(rect.left || 20, rect.bottom || 40);
            } else {
                openMenu(20, 40);
            }
        } catch(e) {
            openMenu(20, 40);
        }

        const trimmed = slashQuery.toLowerCase();

        // Check command triggers
        if (trimmed.startsWith('/loan')) {
            const subQuery = trimmed.replace('/loan', '').trim();
            fetchCategoryData('loan', subQuery);
        } else if (trimmed.startsWith('/transaction') || trimmed.startsWith('/txn') || trimmed.startsWith('/tx')) {
            const subQuery = trimmed.replace(/\/transaction|\/txn|\/tx/, '').trim();
            fetchCategoryData('transaction', subQuery);
        } else if (trimmed.startsWith('/party')) {
            const subQuery = trimmed.replace('/party', '').trim();
            fetchCategoryData('party', subQuery);
        } else if (trimmed.startsWith('/employee') || trimmed.startsWith('/emp')) {
            const subQuery = trimmed.replace(/\/employee|\/emp/, '').trim();
            fetchCategoryData('employee', subQuery);
        } else {
            const query = trimmed.replace('/', '').trim();
            fetchCategoryData('all', query);
        }
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            closeMenu();
        }
    });
}

// Global helper to set data in rich editor programmatically
window.setRichEditorData = function(editorId, html) {
    if (window.__richEditors && window.__richEditors[editorId]) {
        window.__richEditors[editorId].setData(html || '');
    } else {
        const el = document.getElementById(editorId);
        if (el) el.value = html || '';
    }
};

window.getRichEditor = function(editorId) {
    return (window.__richEditors && window.__richEditors[editorId]) ? window.__richEditors[editorId] : null;
};
</script>
