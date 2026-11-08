(() => {
    const form = document.querySelector('[data-payroll-review]');
    if (!form) return;
    const { runId, employees } = JSON.parse(document.getElementById('payroll-review-data').textContent);
    const selected = new Set();
    const find = name => form.querySelector(`[data-review-${name}]`);
    const search = find('search'), filter = find('filter'), rows = find('rows'), pageCheck = find('page');
    const previous = find('prev'), next = find('next'), save = find('save'), reset = find('reset');
    const money = cents => (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let page = 0;
    const pageSize = 50;
    const matches = () => {
        const words = search.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        return employees.filter(employee => (filter.value === 'all' || employee.reviewed === (filter.value === 'reviewed'))
            && words.every(word => `${employee.name} ${employee.code}`.toLowerCase().includes(word)));
    };
    const visible = () => matches().slice(page * pageSize, (page + 1) * pageSize);
    const summary = () => {
        const filtered = matches();
        const outside = selected.size - filtered.filter(employee => selected.has(employee.id)).length;
        find('count').textContent = `${selected.size} employee${selected.size === 1 ? '' : 's'} selected`;
        find('message').textContent = outside ? `${outside} selected outside the current filter. Clear selection to start again.` : 'Selection is retained across searches and pages.';
        save.disabled = reset.disabled = selected.size === 0;
        find('clear').disabled = selected.size === 0;
        const shown = visible(), checked = shown.filter(employee => selected.has(employee.id)).length;
        pageCheck.checked = shown.length > 0 && checked === shown.length;
        pageCheck.indeterminate = checked > 0 && checked < shown.length;
        pageCheck.disabled = shown.length === 0;
        find('all').textContent = `Select all ${filtered.length} matching employees`;
        find('all').disabled = filtered.length === 0;
    };
    const render = () => {
        const filtered = matches();
        page = Math.min(page, Math.max(0, Math.ceil(filtered.length / pageSize) - 1));
        rows.replaceChildren();
        visible().forEach(employee => {
            const row = document.createElement('tr');
            const choice = document.createElement('td'); choice.className = 'pr-review-checkbox';
            const label = document.createElement('label'); label.className = 'pr-review-check';
            const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = selected.has(employee.id);
            checkbox.setAttribute('aria-label', `Select ${employee.name} ${employee.code}`);
            checkbox.addEventListener('change', () => { if (checkbox.checked) selected.add(employee.id); else selected.delete(employee.id); summary(); });
            label.append(checkbox); choice.append(label); row.append(choice);
            const identity = document.createElement('td');
            const link = document.createElement('a'); link.href = `/payroll/${runId}/employees/${employee.id}`; link.textContent = employee.name;
            const code = document.createElement('small'); code.textContent = employee.code || '–'; identity.append(link, code); row.append(identity);
            [employee.gross, employee.net].forEach(value => { const cell = document.createElement('td'); cell.className = 'pr-number'; cell.textContent = money(value); row.append(cell); });
            const status = document.createElement('td'); status.textContent = employee.reviewed ? 'Reviewed' : 'Needs review'; row.append(status);
            const action = document.createElement('td'); const edit = document.createElement('a'); edit.href = link.href; edit.textContent = 'Review & adjust'; action.append(edit); row.append(action);
            rows.append(row);
        });
        if (!filtered.length) { const row = document.createElement('tr'); const cell = document.createElement('td'); cell.colSpan = 6; cell.textContent = 'No employees match the search and review status.'; row.append(cell); rows.append(row); }
        find('range').textContent = filtered.length ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, filtered.length)} of ${filtered.length} employees` : '0 employees';
        previous.disabled = page === 0; next.disabled = (page + 1) * pageSize >= filtered.length;
        summary();
    };
    search.addEventListener('input', () => { page = 0; render(); });
    search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    filter.addEventListener('change', () => { page = 0; render(); });
    previous.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    pageCheck.addEventListener('change', () => { visible().forEach(employee => { if (pageCheck.checked) selected.add(employee.id); else selected.delete(employee.id); }); render(); });
    find('all').addEventListener('click', () => { matches().forEach(employee => selected.add(employee.id)); render(); });
    find('clear').addEventListener('click', () => { selected.clear(); render(); });
    form.addEventListener('submit', event => {
        if (!selected.size) { event.preventDefault(); return; }
        find('selection').value = JSON.stringify(employees.filter(employee => selected.has(employee.id)).map(employee => ({ item_id: employee.id, version: employee.version })));
    });
    render();
})();
