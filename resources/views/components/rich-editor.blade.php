@props([
    'name',
    'id' => null,
    'value' => '',
    'placeholder' => 'Type / for commands (/loan, /transaction, /payment, /project, /party, /employee)...',
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
    >{{ $initialValue }}</textarea>

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
                    placeholder="Search loans, transactions, payments, projects, parties, employees..."
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
            <button type="button" class="slash-tab-pill" data-category="payment"><ion-icon name="card-outline"></ion-icon> /payment</button>
            <button type="button" class="slash-tab-pill" data-category="project"><ion-icon name="briefcase-outline"></ion-icon> /project</button>
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
    background: var(--bg-card) !important;
    color: var(--text-main) !important;
    border-color: var(--nav-hover) !important;
    border-bottom-left-radius: 8px !important;
    border-bottom-right-radius: 8px !important;
    font-family: inherit !important;
    font-size: 0.9rem;
    line-height: 1.5;
    padding: 0.75rem 1rem;
}
#wrap_{{ $editorId }} .ck.ck-editor__main > .ck-editor__editable:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px var(--primary-light) !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar {
    background: var(--bg-card) !important;
    border-color: var(--nav-hover) !important;
    border-top-left-radius: 8px !important;
    border-top-right-radius: 8px !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button {
    color: var(--text-main) !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button:hover,
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button.ck-on {
    background: var(--primary-light) !important;
    color: var(--primary) !important;
}
#wrap_{{ $editorId }} .ck.ck-dropdown__panel {
    background: var(--bg-card) !important;
    border-color: var(--border) !important;
}

/* Slash Command Menu Popover */
.slash-menu-popup {
    position: absolute !important;
    z-index: 9999999 !important;
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    border-radius: 12px !important;
    box-shadow: 0 12px 32px #0002 !important;
    width: 440px !important;
    max-width: min(94vw, 100%) !important;
    max-height: 420px !important;
    font-family: inherit !important;
    backdrop-filter: none !important;
    overflow: hidden !important;
    flex-direction: column !important;
}
.slash-menu-header {
    padding: 10px 14px !important;
    background: var(--bg-page) !important;
    border-bottom: 1px solid var(--border-light) !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    font-size: 0.75rem !important;
}
.slash-menu-title {
    font-weight: 700 !important;
    color: var(--primary) !important;
    text-transform: none !important;
    letter-spacing: normal !important;
}
.slash-menu-hint {
    color: var(--text-muted) !important;
    font-size:0.75rem !important;
}

/* Search Bar (Always Visible) */
.slash-search-bar {
    padding: 8px 12px !important;
    background: var(--bg-card) !important;
    border-bottom: 1px solid var(--border-light) !important;
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
    color: var(--primary) !important;
    font-size: 1.1rem !important;
    pointer-events: none !important;
}
.slash-search-input {
    width: 100% !important;
    background: var(--bg-page) !important;
    border: 1px solid var(--control-border) !important;
    color: var(--text-heading) !important;
    border-radius: 8px !important;
    padding: 8px 12px 8px 36px !important;
    font-size: 0.88rem !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: all 0.15s ease !important;
}
.slash-search-input:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3) !important;
}
.slash-search-input::placeholder {
    color: var(--text-light) !important;
}

/* Tabs Bar */
.slash-tabs-bar {
    display: flex !important;
    gap: 6px !important;
    padding: 6px 12px !important;
    background: var(--bg-page) !important;
    border-bottom: 1px solid var(--border-light) !important;
    overflow-x: auto !important;
}
.slash-tab-pill {
    background: var(--bg-page) !important;
    border: 1px solid var(--border-light) !important;
    color: var(--text-muted) !important;
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
    background: var(--nav-hover) !important;
    color: var(--text-heading) !important;
}
.slash-tab-pill.active {
    background: var(--action) !important;
    border-color: var(--action) !important;
    color: var(--action-ink) !important;
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
    color: var(--text-main) !important;
    transition: all 0.15s ease !important;
    border-left: 3px solid transparent !important;
}
.slash-menu-item:hover, .slash-menu-item.active {
    background: var(--primary-light) !important;
    color: var(--text-heading) !important;
    border-left-color: var(--primary) !important;
}
.slash-menu-item-icon {
    width: 34px !important;
    height: 34px !important;
    border-radius: 6px !important;
    background: var(--bg-page) !important;
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
    color: var(--text-heading) !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 6px !important;
}
.slash-menu-item-sub {
    font-size: 0.74rem !important;
    color: var(--text-muted) !important;
    margin-top: 2px !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}
.slash-menu-empty {
    padding: 1.5rem 1rem !important;
    text-align: center !important;
    color: var(--text-muted) !important;
    font-size: 0.82rem !important;
}
.slash-menu-loading {
    padding: 1.5rem 1rem !important;
    text-align: center !important;
    color: var(--primary) !important;
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
    background: var(--primary-light) !important;
    color: var(--primary) !important;
    border: 1px solid var(--border) !important;
}
.mention-chip.mention-loan:hover {
    background: rgba(139, 92, 246, 0.25) !important;
    border-color: var(--primary) !important;
    color: var(--primary-hover) !important;
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3) !important;
    text-decoration: underline !important;
}
.mention-chip.mention-transaction {
    background: rgba(245, 158, 11, 0.14) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(245, 158, 11, 0.35) !important;
}
.mention-chip.mention-transaction:hover {
    background: rgba(245, 158, 11, 0.25) !important;
    border-color: var(--warning) !important;
    color: var(--warning) !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3) !important;
    text-decoration: underline !important;
}
.mention-chip.mention-party {
    background: rgba(59, 130, 246, 0.14) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(59, 130, 246, 0.35) !important;
}
.mention-chip.mention-party:hover {
    background: rgba(59, 130, 246, 0.25) !important;
    border-color: var(--primary) !important;
    color: var(--primary-hover) !important;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3) !important;
}
.mention-chip.mention-employee {
    background: rgba(16, 185, 129, 0.14) !important;
    color: var(--success) !important;
    border: 1px solid rgba(16, 185, 129, 0.35) !important;
}
.mention-chip.mention-employee:hover {
    background: rgba(16, 185, 129, 0.25) !important;
    border-color: var(--success) !important;
    color: var(--success) !important;
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
                return { items: [], loans: [], parties: [], employees: [], transactions: [], payments: [], projects: [] };
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
            placeholder: config.placeholder || 'Type / for commands (/loan, /transaction, /payment, /project, /party, /employee)...'
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
    let searchRevision = 0;
    const escapeMention = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));

    function closeMenu() {
        searchRevision++;
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
        const menuWidth = Math.min(440, window.innerWidth * 0.94, rect.width);
        left = Math.max(0, Math.min(left, rect.width - menuWidth));
        menu.style.top = Math.max(10, top) + 'px';
        menu.style.left = left + 'px';
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
                const codeBadge = `<span style="font-size:0.75rem; font-weight:700; color:var(--primary); background:rgba(139,92,246,0.15); padding:1px 6px; border-radius:4px;">${escapeMention(item.loan_code || 'LN-' + item.id)}</span>`;
                const statusBadge = `<span style="font-size:0.68rem; padding:1px 6px; border-radius:4px; font-weight:600; background:${item.status === 'settled' ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309'}">${escapeMention(item.status)}</span>`;
                const descSnippet = item.purpose ? `<span style="color:var(--text-muted); font-size:0.72rem;"> &bull; ${escapeMention(item.purpose.substring(0, 100))}${item.purpose.length > 100 ? '…' : ''}</span>` : '';

                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:var(--primary); background:rgba(139,92,246,0.15);">
                        <ion-icon name="card-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>${codeBadge} ${escapeMention(item.party_name || item.lender_name)}</span>
                            ${statusBadge}
                        </div>
                        <div class="slash-menu-item-sub">
                            <strong style="color:var(--text-heading);">${escapeMention(item.currency)} ${Number(item.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                            ${descSnippet}
                        </div>
                        ${item.party_name && item.lender_name && item.party_name !== item.lender_name ? `<div class="slash-menu-item-sub">Lender: ${escapeMention(item.lender_name)}</div>` : ''}
                    </div>
                `;
            } else if (item.type === 'transaction') {
                const isInc = item.type_name === 'income' || item.type === 'income';
                const isExp = item.type_name === 'expense' || item.type === 'expense';
                const typeColor = isInc ? 'var(--success)' : (isExp ? 'var(--danger)' : 'var(--primary)');
                const typeBg = isInc ? 'rgba(16,185,129,0.15)' : (isExp ? 'rgba(239,68,68,0.15)' : 'rgba(59,130,246,0.15)');
                const typeLabel = (item.type_name || 'TXN').toUpperCase();
                const refSnippet = item.reference_no ? `<span style="font-size:0.72rem; color:var(--text-muted); background:var(--bg-page); padding:1px 5px; border-radius:4px;">${escapeMention(item.reference_no)}</span>` : '';
                const dateSnippet = item.transaction_date ? `<span style="color:var(--text-muted); font-size:0.72rem;"> &bull; ${escapeMention(item.transaction_date)}</span>` : '';

                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:${typeColor}; background:${typeBg};">
                        <ion-icon name="receipt-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>
                                <span style="font-size:0.75rem; font-weight:700; color:${typeColor}; background:${typeBg}; padding:1px 5px; border-radius:4px; margin-right:4px;">${typeLabel}</span>
                                ${escapeMention(item.description || 'Transaction #' + item.id)}
                            </span>
                            ${refSnippet}
                        </div>
                        <div class="slash-menu-item-sub">
                            <strong style="color:${typeColor};">${isInc ? '+' : (isExp ? '-' : '')}${item.currency} ${Number(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                            ${item.category_name ? `<span style="color:var(--text-muted); font-size:0.72rem;"> &bull; ${escapeMention(item.category_name)}</span>` : ''}
                            ${dateSnippet}
                        </div>
                    </div>
                `;
            } else if (item.type === 'payment' || item.type === 'project') {
                const isPayment = item.type === 'payment';
                const description = isPayment ? [item.project_name, item.invoice_no, item.reference, item.date].filter(Boolean).join(' · ') : [item.company_name, item.status].filter(Boolean).join(' · ');
                itemEl.innerHTML = `<div class="slash-menu-item-icon"><ion-icon name="${isPayment ? 'card-outline' : 'briefcase-outline'}"></ion-icon></div><div class="slash-menu-item-content"><div class="slash-menu-item-title">${escapeMention(item.code)} · ${escapeMention(isPayment ? (item.project_name || item.invoice_no || 'Client receipt') : item.name)}</div><div class="slash-menu-item-sub">${escapeMention(description)}</div><div class="slash-menu-item-sub">${isPayment ? 'Recorded' : 'Budget'} · ${escapeMention(item.currency)} ${Number(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</div></div>`;
            } else if (item.type === 'party') {
                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:var(--primary); background:rgba(59,130,246,0.15);">
                        <ion-icon name="business-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>${item.name}</span>
                            ${item.party_types ? `<span style="font-size:0.68rem; color:var(--primary); background:rgba(59,130,246,0.1); padding:1px 5px; border-radius:4px;">${item.party_types}</span>` : ''}
                        </div>
                        <div class="slash-menu-item-sub">${item.contact_person || item.phone || item.email || 'Master Party'}</div>
                    </div>
                `;
            } else if (item.type === 'employee') {
                itemEl.innerHTML = `
                    <div class="slash-menu-item-icon" style="color:var(--success); background:rgba(16,185,129,0.15);">
                        <ion-icon name="person-outline"></ion-icon>
                    </div>
                    <div class="slash-menu-item-content">
                        <div class="slash-menu-item-title">
                            <span>${item.name}</span>
                            ${item.code ? `<span style="font-size:0.75rem; color:var(--text-muted);">${item.code}</span>` : ''}
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
            htmlToInsert = `<a href="/loans/${item.id}" target="_blank" rel="noopener noreferrer" class="mention-chip mention-loan" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(139,92,246,0.12); color:var(--primary); font-weight:600; text-decoration:none; border:1px solid rgba(139,92,246,0.25);" contenteditable="false"><ion-icon name="link-outline"></ion-icon> ${escapeMention(code)}: ${escapeMention(item.party_name || item.lender_name)} (${escapeMention(item.currency)} ${Number(item.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2})})</a>&nbsp;`;
        } else if (item.type === 'transaction') {
            const isInc = item.type_name === 'income';
            const sign = isInc ? '+' : (item.type_name === 'expense' ? '-' : '');
            const ref = item.reference_no ? ` [${escapeMention(item.reference_no)}]` : '';
            htmlToInsert = `<a href="/transactions?transaction_id=${Number(item.id)}" target="_blank" rel="noopener noreferrer" class="mention-chip mention-transaction" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(245,158,11,0.14); color:var(--warning); font-weight:600; text-decoration:none; border:1px solid rgba(245,158,11,0.35);" contenteditable="false"><ion-icon name="receipt-outline"></ion-icon> TXN-${item.id}${ref}: ${escapeMention((item.type_name || 'transaction').toUpperCase())} ${sign}${escapeMention(item.currency)} ${Number(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</a>&nbsp;`;
        } else if (item.type === 'payment' || item.type === 'project') {
            const isPayment = item.type === 'payment';
            const label = isPayment ? `${item.code}: ${item.project_name || item.invoice_no || 'Client receipt'} (${item.currency} ${Number(item.amount).toLocaleString(undefined, {minimumFractionDigits: 2})})` : `${item.code}: ${item.name}`;
            htmlToInsert = `<a href="/${isPayment ? 'payments' : 'projects'}/${Number(item.id)}">${escapeMention(label)}</a>&nbsp;`;
        } else if (item.type === 'party') {
            htmlToInsert = `<span class="mention-chip mention-party" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(59,130,246,0.12); color:var(--primary); font-weight:600; border:1px solid rgba(59,130,246,0.25);" contenteditable="false"><ion-icon name="business-outline"></ion-icon> ${item.name}</span>&nbsp;`;
        } else if (item.type === 'employee') {
            htmlToInsert = `<span class="mention-chip mention-employee" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(16,185,129,0.12); color:var(--success); font-weight:600; border:1px solid rgba(16,185,129,0.25);" contenteditable="false"><ion-icon name="person-outline"></ion-icon> ${item.name}${item.code ? ' (' + item.code + ')' : ''}</span>&nbsp;`;
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
        const revision = ++searchRevision;
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        updateTabsUI(category);
        const titles = {
            all: 'Slash Commands & Search',
            loan: 'Loans · up to 10 matches',
            transaction: 'Transactions (/transaction - 10 Results)',
            payment: 'Payments · up to 10 matches', project: 'Projects · up to 10 matches',
            party: 'Parties (/party - 10 Results)',
            employee: 'Employees (/employee - 10 Results)'
        };
        const placeholders = {
            all: 'Search loans, transactions, payments, projects, parties, employees...',
            loan: 'Search code, party, lender, description or amount…',
            transaction: 'Search transaction by description, ref no, amount...',
            payment: 'Search payment ID, project, invoice, bank reference or amount…',
            project: 'Search project ID, name, company or client…',
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
                {type: 'command', action: 'payment', title: '/payment', subtitle: 'Reference a recorded client / project payment', icon: 'card-outline', iconColor: 'var(--primary)', bgColor: 'var(--primary-light)'},
                {type: 'command', action: 'project', title: '/project', subtitle: 'Search and link a project', icon: 'briefcase-outline', iconColor: 'var(--primary)', bgColor: 'var(--primary-light)'},
                {
                    type: 'command',
                    action: 'loan',
                    title: '/loan',
                    subtitle: 'Search & link a Loan (Code, Lender, Amount, Purpose)',
                    icon: 'card-outline',
                    iconColor: 'var(--primary)',
                    bgColor: 'rgba(139, 92, 246, 0.15)'
                },
                {
                    type: 'command',
                    action: 'transaction',
                    title: '/transaction',
                    subtitle: 'Search & link a Transaction (Income, Expense, Ref No)',
                    icon: 'receipt-outline',
                    iconColor: 'var(--warning)',
                    bgColor: 'rgba(245, 158, 11, 0.15)'
                },
                {
                    type: 'command',
                    action: 'party',
                    title: '/party',
                    subtitle: 'Search & mention a Party (Client / Vendor / Partner)',
                    icon: 'business-outline',
                    iconColor: 'var(--primary)',
                    bgColor: 'rgba(59, 130, 246, 0.15)'
                },
                {
                    type: 'command',
                    action: 'employee',
                    title: '/employee',
                    subtitle: 'Search & mention an Employee / Team Member',
                    icon: 'people-outline',
                    iconColor: 'var(--success)',
                    bgColor: 'rgba(16, 185, 129, 0.15)'
                }
            ];
            renderItems(commands, 'Slash Commands');
            return;
        }

        renderLoading(titles[category] || 'Searching...');

        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            window.__fetchMentionsServer(category, query, 10).then(data => {
                if (revision !== searchRevision) return;
                let items = [];
                if (category === 'loan') {
                    items = (data.loans || data.items || []).map(l => ({ ...l, type: 'loan' }));
                } else if (category === 'transaction') {
                    items = (data.transactions || data.items || []).map(t => ({ ...t, type_name: t.type, type: 'transaction' }));
                } else if (category === 'payment' || category === 'project') {
                    items = (data[category === 'payment' ? 'payments' : 'projects'] || data.items || []).map(record => ({...record, type: category}));
                } else if (category === 'party') {
                    items = (data.parties || data.items || []).map(p => ({ ...p, type: 'party' }));
                } else if (category === 'employee') {
                    items = (data.employees || data.items || []).map(e => ({ ...e, type: 'employee' }));
                } else if (category === 'all') {
                    const l = (data.loans || []).map(x => ({ ...x, type: 'loan' }));
                    const tx = (data.transactions || []).map(x => ({ ...x, type_name: x.type, type: 'transaction' }));
                    const p = (data.parties || []).map(x => ({ ...x, type: 'party' }));
                    const em = (data.employees || []).map(x => ({ ...x, type: 'employee' }));
                    items = [...l, ...tx, ...(data.payments || []).map(x => ({...x, type: 'payment'})), ...(data.projects || []).map(x => ({...x, type: 'project'})), ...p, ...em];
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
        // Links/bold text split a paragraph into several model text nodes. Read all
        // text before the caret so another slash reference can follow an existing one.
        let remaining = position.offset;
        let fullText = '';
        for (const child of position.parent.getChildren()) {
            if (remaining <= 0) break;
            const length = Math.min(remaining, child.offsetSize);
            fullText += typeof child.data === 'string' ? child.data.slice(0, length) : ' ';
            remaining -= child.offsetSize;
        }
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
        } else if (trimmed.startsWith('/payment') || trimmed.startsWith('/project')) {
            const category = trimmed.startsWith('/payment') ? 'payment' : 'project';
            fetchCategoryData(category, trimmed.replace(/^\/(?:payments?|projects?)\b/, '').trim());
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
