(() => {
    const form = document.querySelector('[data-salary-batch]');
    if (!form) return;
    const employees = JSON.parse(document.getElementById('salary-batch-data').textContent);
    const byId = new Map(employees.map(employee => [employee.id, employee]));
    const entries = new Map();
    const hidden = form.querySelector('[data-batch-entries]');
    try { JSON.parse(hidden.value || '[]').forEach(entry => { if (byId.has(Number(entry.item_id))) entries.set(Number(entry.item_id), String(entry.amount)); }); } catch (_) { /* Start empty if an old submission cannot be read. */ }
    const rows = form.querySelector('[data-batch-rows]');
    const search = form.querySelector('[data-batch-search]');
    const previous = form.querySelector('[data-batch-prev]'), next = form.querySelector('[data-batch-next]');
    const save = form.querySelector('[data-batch-save]'), message = form.querySelector('[data-batch-message]');
    let page = 0;
    const pageSize = 50;
    const money = cents => (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const cents = value => {
        if (!value.trim()) return 0;
        if (!/^\d+(\.\d{1,2})?$/.test(value.trim())) return null;
        const [whole, fraction = ''] = value.trim().split('.');
        const amount = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
        return Number.isSafeInteger(amount) ? amount : null;
    };
    const filtered = () => {
        const query = search.value.toLowerCase().trim();
        return employees.filter(employee => `${employee.name} ${employee.code || ''}`.toLowerCase().includes(query));
    };
    const pageEmployees = () => filtered().slice(page * pageSize, (page + 1) * pageSize);
    const summary = () => {
        let total = 0, count = 0, invalid = 0;
        entries.forEach((value, id) => {
            const amount = cents(value), employee = byId.get(id);
            if (amount === null || amount > employee.balance || amount > 99999999999) invalid++;
            else if (amount > 0) { total += amount; count++; }
        });
        form.querySelector('[data-batch-count]').textContent = `${count} employee${count === 1 ? '' : 's'} selected`;
        form.querySelector('[data-batch-total]').textContent = `LKR ${money(total)}`;
        save.disabled = invalid > 0 || total <= 0 || total > 99999999999;
        message.textContent = invalid ? `${invalid} amount${invalid === 1 ? '' : 's'} must be corrected. Use up to two decimals and stay within each employee’s remaining salary.` : total > 99999999999 ? 'The batch exceeds the maximum total of LKR 999,999,999.99.' : 'Only the entered amounts will be recorded. Other employees and unpaid balances stay payable.';
    };
    const render = () => {
        const matches = filtered();
        page = Math.min(page, Math.max(0, Math.ceil(matches.length / pageSize) - 1));
        rows.replaceChildren();
        pageEmployees().forEach(employee => {
            const row = document.createElement('tr');
            const identity = document.createElement('td');
            const name = document.createElement('strong'); name.textContent = employee.name;
            const code = document.createElement('small'); code.textContent = employee.code || '—';
            identity.append(name, code); row.append(identity);
            [employee.net, employee.paid, employee.balance].forEach(value => { const cell = document.createElement('td'); cell.className = 'pr-number'; cell.textContent = money(value); row.append(cell); });
            const cell = document.createElement('td'); cell.className = 'pr-number';
            const input = document.createElement('input');
            input.type = 'text'; input.inputMode = 'decimal'; input.pattern = '\\d+(\\.\\d{1,2})?'; input.placeholder = employee.balance > 0 ? '0.00' : 'Paid'; input.disabled = employee.balance <= 0;
            input.className = 'pr-batch-amount'; input.setAttribute('aria-label', `Pay in this batch for ${employee.name}`);
            input.value = entries.get(employee.id) || '';
            const after = document.createElement('small');
            const update = () => { const value = cents(input.value); const invalid = value === null || value > employee.balance || value > 99999999999; input.setAttribute('aria-invalid', String(invalid)); after.textContent = invalid ? `Maximum ${money(employee.balance)}` : value > 0 ? `After batch: ${money(employee.balance - value)}` : ''; };
            input.addEventListener('input', () => { if (input.value.trim()) entries.set(employee.id, input.value); else entries.delete(employee.id); update(); summary(); });
            update(); cell.append(input, after); row.append(cell); rows.append(row);
        });
        if (!matches.length) { const row = document.createElement('tr'); const cell = document.createElement('td'); cell.colSpan = 5; cell.textContent = 'No pending employees match this search.'; row.append(cell); rows.append(row); }
        form.querySelector('[data-batch-range]').textContent = matches.length ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, matches.length)} of ${matches.length} employees · entered amounts are retained across pages` : '0 employees';
        previous.disabled = page === 0; next.disabled = (page + 1) * pageSize >= matches.length; summary();
    };
    search.addEventListener('input', () => { page = 0; render(); });
    previous.addEventListener('click', () => { page--; render(); }); next.addEventListener('click', () => { page++; render(); });
    form.querySelector('[data-batch-fill]').addEventListener('click', () => { pageEmployees().filter(employee => employee.balance > 0).forEach(employee => entries.set(employee.id, (employee.balance / 100).toFixed(2))); render(); });
    form.querySelector('[data-batch-clear]').addEventListener('click', () => { entries.clear(); render(); });
    form.addEventListener('submit', event => {
        summary();
        if (save.disabled) { event.preventDefault(); message.focus(); return; }
        hidden.value = JSON.stringify([...entries].filter(([, value]) => cents(value) > 0).map(([id, value]) => ({ item_id: id, amount: value.trim() })));
        save.disabled = true;
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
        else summary();
    });
    render();
})();
