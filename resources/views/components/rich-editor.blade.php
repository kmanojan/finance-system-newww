@props([
    'name',
    'id' => null,
    'value' => '',
    'placeholder' => 'Type / for commands (/loan, /party, /employee)...',
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
        <div class="slash-menu-header">
            <span class="slash-menu-title" id="slash_title_{{ $editorId }}">
                <ion-icon name="flash-outline" style="vertical-align: middle;"></ion-icon> Slash Commands
            </span>
            <span class="slash-menu-hint">↑↓ navigate &bull; ↵ select &bull; Esc close</span>
        </div>
        <div class="slash-menu-list" id="slash_list_{{ $editorId }}">
            <!-- Dynamic Items Injected Here -->
        </div>
    </div>
</div>

<style>
/* CKEditor Custom Theme & Wrapper */
#wrap_{{ $editorId }} .ck.ck-editor__main > .ck-editor__editable {
    min-height: {{ $height }};
    background: var(--bg-page, #1a1d27) !important;
    color: var(--text-main, #cbd5e1) !important;
    border-color: var(--border, rgba(255,255,255,0.12)) !important;
    border-bottom-left-radius: 8px !important;
    border-bottom-right-radius: 8px !important;
    font-family: inherit !important;
    font-size: 0.9rem;
    line-height: 1.5;
    padding: 0.75rem 1rem;
}
#wrap_{{ $editorId }} .ck.ck-editor__main > .ck-editor__editable:focus {
    border-color: var(--primary, #8b5cf6) !important;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2) !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar {
    background: var(--bg-card, #202431) !important;
    border-color: var(--border, rgba(255,255,255,0.12)) !important;
    border-top-left-radius: 8px !important;
    border-top-right-radius: 8px !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button {
    color: var(--text-main, #cbd5e1) !important;
}
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button:hover,
#wrap_{{ $editorId }} .ck.ck-toolbar .ck-button.ck-on {
    background: rgba(139, 92, 246, 0.2) !important;
    color: var(--primary, #8b5cf6) !important;
}
#wrap_{{ $editorId }} .ck.ck-dropdown__panel {
    background: var(--bg-card, #202431) !important;
    border-color: var(--border, rgba(255,255,255,0.15)) !important;
}

/* Slash Command Menu Popover */
.slash-menu-popup {
    position: absolute;
    z-index: 999999;
    background: var(--bg-card, #202431);
    border: 1px solid var(--border, rgba(255, 255, 255, 0.18));
    border-radius: 10px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5);
    width: 380px;
    max-width: 92vw;
    max-height: 320px;
    overflow-y: auto;
    font-family: inherit;
    backdrop-filter: blur(8px);
}
.slash-menu-header {
    padding: 8px 12px;
    background: var(--bg-page, #1a1d27);
    border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.1));
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
}
.slash-menu-title {
    font-weight: 700;
    color: var(--primary, #8b5cf6);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.slash-menu-hint {
    color: var(--text-muted, #94a3b8);
    font-size: 0.7rem;
}
.slash-menu-list {
    padding: 6px;
}
.slash-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.84rem;
    color: var(--text-main, #cbd5e1);
    transition: all 0.15s ease;
    border-left: 3px solid transparent;
}
.slash-menu-item:hover, .slash-menu-item.active {
    background: rgba(139, 92, 246, 0.15);
    color: #ffffff;
    border-left-color: var(--primary, #8b5cf6);
}
.slash-menu-item-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.slash-menu-item-content {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
}
.slash-menu-item-title {
    font-weight: 600;
    color: var(--text-heading, #f8fafc);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
}
.slash-menu-item-sub {
    font-size: 0.74rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.slash-menu-empty {
    padding: 1rem;
    text-align: center;
    color: var(--text-muted, #94a3b8);
    font-size: 0.8rem;
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

// Global mentions data cache & fetcher
if (typeof window.__loadMentionsData === 'undefined') {
    window.__mentionsData = { loans: [], parties: [], employees: [] };
    window.__loadMentionsData = function() {
        if (window.__mentionsDataLoaded) return Promise.resolve(window.__mentionsData);
        return fetch('/api/rich-editor/mentions')
            .then(res => res.json())
            .then(data => {
                window.__mentionsData = data;
                window.__mentionsDataLoaded = true;
                return data;
            })
            .catch(err => {
                console.warn('Mentions data load failed, fallback to local', err);
                return window.__mentionsData;
            });
    };
    // Pre-load on background
    window.__loadMentionsData();
}

function initRichEditor(editorId, config) {
    const el = document.getElementById(editorId);
    if (!el || typeof ClassicEditor === 'undefined') return;

    // Destroy existing instance if any
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
            placeholder: config.placeholder || 'Type / for commands (/loan, /party, /employee)...'
        })
        .then(editor => {
            window.__richEditors[editorId] = editor;
            
            // Sync with textarea on changes and form submits
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
    const wrapper = document.getElementById('wrap_' + editorId);
    if (!menu || !list || !wrapper) return;

    let isMenuOpen = false;
    let selectedIndex = 0;
    let currentItems = [];
    let currentCommand = null; // 'root', 'loan', 'party', 'employee', 'all'
    let lastSlashLength = 1;

    function closeMenu() {
        menu.style.display = 'none';
        isMenuOpen = false;
        selectedIndex = 0;
        currentCommand = null;
    }

    function openMenu(x, y) {
        menu.style.display = 'block';
        isMenuOpen = true;
        const rect = wrapper.getBoundingClientRect();
        let top = (y - rect.top) + 25;
        let left = (x - rect.left);
        if (left + 380 > rect.width) {
            left = Math.max(10, rect.width - 390);
        }
        menu.style.top = Math.max(10, top) + 'px';
        menu.style.left = Math.max(10, left) + 'px';
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
                const codeBadge = `<span style="font-size:0.75rem; font-weight:700; color:var(--primary); background:rgba(139,92,246,0.12); padding:1px 6px; border-radius:4px;">${item.loan_code || 'LN-' + item.id}</span>`;
                const statusBadge = `<span style="font-size:0.68rem; padding:1px 6px; border-radius:4px; font-weight:600; background:${item.status === 'settled' ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309'}">${item.status}</span>`;
                const descSnippet = item.purpose ? `<span style="color:var(--text-muted); font-size:0.72rem;"> &bull; ${item.purpose.substring(0, 35)}${item.purpose.length > 35 ? '...' : ''}</span>` : '';

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
                            <strong style="color:var(--text-heading);">${item.currency} ${Number(item.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                            ${descSnippet}
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
                            ${item.code ? `<span style="font-size:0.7rem; color:var(--text-muted);">${item.code}</span>` : ''}
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
        if (item.type === 'command') {
            if (item.action === 'loan') {
                currentCommand = 'loan';
                renderLoanList('');
            } else if (item.action === 'party') {
                currentCommand = 'party';
                renderPartyList('');
            } else if (item.action === 'employee') {
                currentCommand = 'employee';
                renderEmployeeList('');
            }
            return;
        }

        // Insert into editor
        let htmlToInsert = '';
        if (item.type === 'loan') {
            const code = item.loan_code || ('LN-' + item.id);
            htmlToInsert = `<a href="/loans/${item.id}" target="_blank" rel="noopener noreferrer" class="mention-chip mention-loan" style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:rgba(139,92,246,0.12); color:#8b5cf6; font-weight:600; text-decoration:none; border:1px solid rgba(139,92,246,0.25);" contenteditable="false"><ion-icon name="link-outline"></ion-icon> ${code}: ${item.lender_name} (${item.currency} ${Number(item.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2})})</a>&nbsp;`;
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
        }

        closeMenu();
    }

    function renderRootCommands(query) {
        currentCommand = 'root';
        const q = (query || '').toLowerCase().trim();
        const commands = [
            {
                type: 'command',
                action: 'loan',
                title: '/loan',
                subtitle: 'Search & link a Loan by Code, Lender, Amount, Description',
                icon: 'card-outline',
                iconColor: '#8b5cf6',
                bgColor: 'rgba(139, 92, 246, 0.15)'
            },
            {
                type: 'command',
                action: 'party',
                title: '/party',
                subtitle: 'Mention a Party (Client / Vendor / Partner / Lender)',
                icon: 'business-outline',
                iconColor: '#3b82f6',
                bgColor: 'rgba(59, 130, 246, 0.15)'
            },
            {
                type: 'command',
                action: 'employee',
                title: '/employee',
                subtitle: 'Mention an Employee / Team Member',
                icon: 'people-outline',
                iconColor: '#10b981',
                bgColor: 'rgba(16, 185, 129, 0.15)'
            }
        ];

        // If query has substantive text, also perform multi-entity search
        if (q.length > 0) {
            window.__loadMentionsData().then(data => {
                const matchingLoans = filterLoans(data.loans || [], q);
                const matchingParties = filterParties(data.parties || [], q);
                const matchingEmps = filterEmployees(data.employees || [], q);

                const combined = [
                    ...commands.filter(c => c.title.toLowerCase().includes(q) || c.subtitle.toLowerCase().includes(q)),
                    ...matchingLoans.slice(0, 3),
                    ...matchingParties.slice(0, 3),
                    ...matchingEmps.slice(0, 3)
                ];
                renderItems(combined, 'Search Results');
            });
        } else {
            renderItems(commands, 'Slash Commands');
        }
    }

    function filterLoans(loans, query) {
        const q = query.toLowerCase().replace(/[,kKmMsS]/g, '').trim();
        const rawQ = query.toLowerCase().trim();
        return loans.map(l => ({ ...l, type: 'loan' })).filter(l => {
            const code = (l.loan_code || 'LN-' + l.id).toLowerCase();
            const idStr = String(l.id);
            const lender = (l.lender_name || '').toLowerCase();
            const party = (l.party_name || '').toLowerCase();
            const purpose = (l.purpose || '').toLowerCase();
            const amountStr = String(l.principal_amount || '');
            const amountFormatted = Number(l.principal_amount || 0).toLocaleString().toLowerCase();
            const status = (l.status || '').toLowerCase();

            return code.includes(rawQ) ||
                   idStr === rawQ ||
                   lender.includes(rawQ) ||
                   party.includes(rawQ) ||
                   purpose.includes(rawQ) ||
                   status.includes(rawQ) ||
                   amountStr.includes(q) ||
                   amountFormatted.includes(rawQ);
        });
    }

    function filterParties(parties, query) {
        const q = query.toLowerCase().trim();
        return parties.map(p => ({ ...p, type: 'party' })).filter(p => {
            const name = (p.name || '').toLowerCase();
            const contact = (p.contact_person || '').toLowerCase();
            const phone = (p.phone || '').toLowerCase();
            const types = (p.party_types || '').toLowerCase();
            return name.includes(q) || contact.includes(q) || phone.includes(q) || types.includes(q);
        });
    }

    function filterEmployees(employees, query) {
        const q = query.toLowerCase().trim();
        return employees.map(e => ({ ...e, type: 'employee' })).filter(e => {
            const name = (e.name || '').toLowerCase();
            const code = (e.code || '').toLowerCase();
            const job = (e.job_position || '').toLowerCase();
            return name.includes(q) || code.includes(q) || job.includes(q);
        });
    }

    function renderLoanList(query) {
        currentCommand = 'loan';
        window.__loadMentionsData().then(data => {
            const filtered = filterLoans(data.loans || [], query);
            renderItems(filtered, 'Loans (/loan)');
        });
    }

    function renderPartyList(query) {
        currentCommand = 'party';
        window.__loadMentionsData().then(data => {
            const filtered = filterParties(data.parties || [], query);
            renderItems(filtered, 'Parties (/party)');
        });
    }

    function renderEmployeeList(query) {
        currentCommand = 'employee';
        window.__loadMentionsData().then(data => {
            const filtered = filterEmployees(data.employees || [], query);
            renderItems(filtered, 'Employees (/employee)');
        });
    }

    // Keydown for Menu Navigation
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

    // Monitor typing
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

        const slashQuery = fullText.substring(lastSlashIndex); // e.g. "/loan 2000000" or "/party"
        lastSlashLength = slashQuery.length;

        // Position Menu
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

        // Parse query & route to appropriate feed
        const trimmed = slashQuery.toLowerCase();
        if (trimmed.startsWith('/loan')) {
            const sub = trimmed.replace('/loan', '').trim();
            renderLoanList(sub);
        } else if (trimmed.startsWith('/party') || trimmed.startsWith('/client') || trimmed.startsWith('/vendor')) {
            const sub = trimmed.replace(/\/party|\/client|\/vendor/, '').trim();
            renderPartyList(sub);
        } else if (trimmed.startsWith('/employee') || trimmed.startsWith('/emp') || trimmed.startsWith('/staff')) {
            const sub = trimmed.replace(/\/employee|\/emp|\/staff/, '').trim();
            renderEmployeeList(sub);
        } else {
            renderRootCommands(trimmed.replace('/', ''));
        }
    });

    // Close on blur or click outside
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
