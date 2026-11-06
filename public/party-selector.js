/* Shared party selection: each instance owns its value, placeholder and callback. */
(() => {
    const wrappers = () => document.querySelectorAll('.party-selector-wrapper');
    function close(wrapper, restoreFocus = false) {
        wrapper.querySelector('.party-dropdown-menu').hidden = true;
        wrapper.querySelector('.party-select-trigger').setAttribute('aria-expanded', 'false');
        if (restoreFocus) wrapper.querySelector('.party-select-trigger').focus();
    }
    window.filterPartyList = (wrapperId, query) => {
        const wrapper = document.getElementById(wrapperId);
        const q = query.toLocaleLowerCase().trim();
        let matches = 0;
        wrapper.querySelectorAll('.party-option-item').forEach(option => {
            option.hidden = !!option.dataset.id && !(option.dataset.search || '').toLocaleLowerCase().includes(q);
            if (option.dataset.id && !option.hidden) matches++;
        });
        wrapper.querySelector('.party-no-results').hidden = matches > 0;
    };
    window.togglePartyDropdown = wrapperId => {
        const wrapper = document.getElementById(wrapperId);
        if (wrapper.querySelector('.party-id-hidden').disabled) return;
        const opening = wrapper.querySelector('.party-dropdown-menu').hidden;
        wrappers().forEach(other => close(other));
        if (opening) {
            wrapper.querySelector('.party-dropdown-menu').hidden = false;
            wrapper.querySelector('.party-select-trigger').setAttribute('aria-expanded', 'true');
            const search = wrapper.querySelector('.party-search-input');
            search.value = '';
            filterPartyList(wrapperId, '');
            search.focus();
        }
    };
    window.selectPartyOption = (wrapperId, id, name = '', types = '') => {
        const wrapper = document.getElementById(wrapperId);
        const input = wrapper.querySelector('.party-id-hidden');
        if (input.disabled) return;
        const previous = [...wrapper.querySelectorAll('.party-option-item')].find(option => option.dataset.id === input.value);
        const lender = wrapper.closest('form')?.querySelector('input[name="lender_name"]');
        if (lender) {
            if (id) lender.value = name;
            else if (lender.value === previous?.dataset.name) lender.value = '';
        }
        input.value = id;
        const label = wrapper.querySelector('.selected-party-label');
        label.replaceChildren();
        if (id) {
            const strong = document.createElement('strong');
            strong.textContent = name;
            label.append(strong);
            if (types) {
                const role = document.createElement('span');
                role.className = 'party-role';
                role.textContent = ` (${types})`;
                label.append(role);
            }
        } else label.textContent = wrapper.dataset.placeholder;
        wrapper.querySelector('.party-clear').hidden = !id;
        close(wrapper, true);
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
        const callback = window[wrapper.dataset.onchange];
        if (typeof callback === 'function') callback(id, name);
    };
    wrappers().forEach(wrapper => {
        wrapper.querySelector('.party-select-trigger').addEventListener('click', () => togglePartyDropdown(wrapper.id));
        wrapper.querySelector('.party-clear').addEventListener('click', () => selectPartyOption(wrapper.id, '', '', ''));
        wrapper.querySelector('.party-search-input').addEventListener('input', event => filterPartyList(wrapper.id, event.target.value));
        wrapper.querySelectorAll('.party-option-item').forEach(option => option.addEventListener('click', () => selectPartyOption(wrapper.id, option.dataset.id, option.dataset.name, option.dataset.types)));
        wrapper.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !wrapper.querySelector('.party-dropdown-menu').hidden) {
                event.preventDefault(); event.stopPropagation(); close(wrapper, true);
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (wrapper.querySelector('.party-dropdown-menu').hidden) togglePartyDropdown(wrapper.id);
                else {
                    const options = [...wrapper.querySelectorAll('.party-option-item')].filter(option => !option.hidden);
                    const index = options.indexOf(document.activeElement);
                    const next = index < 0 ? (event.key === 'ArrowDown' ? 0 : options.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                    options[next]?.focus();
                }
            }
        });
        wrapper.addEventListener('focusout', () => setTimeout(() => { if (!wrapper.contains(document.activeElement)) close(wrapper); }, 0));
        wrapper.closest('form')?.addEventListener('reset', () => setTimeout(() => {
            const input = wrapper.querySelector('.party-id-hidden');
            const option = [...wrapper.querySelectorAll('.party-option-item')].find(option => option.dataset.id === input.value);
            selectPartyOption(wrapper.id, input.value, option?.dataset.name || '', option?.dataset.types || '');
        }, 0));
    });
    document.addEventListener('click', event => wrappers().forEach(wrapper => { if (!wrapper.contains(event.target)) close(wrapper); }));
})();
