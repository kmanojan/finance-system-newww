(() => {
    document.querySelectorAll('[data-employee-selector]').forEach(root => {
        const field = root.querySelector('select');
        const trigger = root.querySelector('.employee-selector-trigger');
        const clear = root.querySelector('.employee-selector-clear');
        const dialog = root.querySelector('dialog');
        const search = root.querySelector('[data-employee-search]');
        const list = root.querySelector('[data-employee-list]');
        const more = root.querySelector('[data-employee-more]');
        const error = root.querySelector('.employee-selector-error');
        const choices = [...field.options].filter(option => option.value);
        let limit = 50;
        function sync() {
            const option = field.selectedOptions[0];
            root.querySelector('[data-employee-label]').textContent = option?.textContent || root.dataset.placeholder;
            const code = root.querySelector('[data-employee-code]');
            code.textContent = option?.dataset.code || '';
            code.hidden = !code.textContent;
            clear.hidden = !field.value;
            if (field.value || !field.required) {
                error.hidden = true;
                trigger.removeAttribute('aria-invalid');
            }
        }
        function close() { dialog.close(); }
        function select(value) {
            field.value = value;
            field.dispatchEvent(new Event('input', {bubbles:true}));
            field.dispatchEvent(new Event('change', {bubbles:true}));
            root.dispatchEvent(new CustomEvent('employee-selected', {detail:value, bubbles:true}));
            close(); trigger.focus();
        }
        function render() {
            const query = search.value.trim().toLocaleLowerCase();
            const matches = choices.filter(option => `${option.textContent} ${option.dataset.code}`.toLocaleLowerCase().includes(query));
            list.replaceChildren();
            matches.slice(0,limit).forEach(option => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'employee-selector-option';
                button.dataset.value = option.value;
                button.setAttribute('aria-pressed', String(field.value === option.value));
                const label = document.createElement('span'); label.textContent = option.textContent;
                const code = document.createElement('span'); code.className = 'employee-code'; code.textContent = option.dataset.code; code.hidden = !code.textContent;
                button.append(label,code); button.addEventListener('click', () => select(option.value)); list.append(button);
            });
            root.querySelector('[data-employee-count]').textContent = matches.length ? `${Math.min(limit,matches.length)} of ${matches.length} employees` : 'No employees match your search.';
            root.querySelector('[data-employee-empty]').setAttribute('aria-pressed', String(!field.value));
            more.hidden = matches.length <= limit;
        }
        trigger.addEventListener('click', () => {
            search.value = ''; limit = 50; render(); dialog.showModal(); trigger.setAttribute('aria-expanded','true'); search.focus();
        });
        clear.addEventListener('click', () => select(''));
        root.querySelector('[data-employee-empty]').addEventListener('click', () => select(''));
        root.querySelector('[data-employee-close]').addEventListener('click', close);
        dialog.addEventListener('close', () => { trigger.setAttribute('aria-expanded','false'); trigger.focus(); });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
        });
        search.addEventListener('input', () => { limit = 50; render(); });
        more.addEventListener('click', () => {
            const previous = list.children.length; limit += 50; render(); list.children[previous]?.focus();
        });
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Enter' && event.target === search) { event.preventDefault(); list.querySelector('button')?.focus(); }
            if (!['ArrowDown','ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            const buttons = [...list.querySelectorAll('button')];
            const current = buttons.indexOf(document.activeElement);
            buttons[(current + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length]?.focus();
        });
        field.addEventListener('change', sync);
        field.addEventListener('invalid', event => { event.preventDefault(); error.hidden = false; trigger.setAttribute('aria-invalid','true'); trigger.focus(); });
        field.form?.addEventListener('reset', () => setTimeout(sync,0));
        field.labels.forEach(label => { label.htmlFor = trigger.id; });
        field.tabIndex = -1; field.setAttribute('aria-hidden','true');
        root.classList.add('is-ready'); root.querySelector('.employee-selector-controls').hidden = false;
        sync();
    });
})();
