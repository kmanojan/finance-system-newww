(() => {
    const form = document.querySelector('[data-statutory-batch]');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-stat-row]')];
    const all = form.querySelector('[data-stat-all]'), save = form.querySelector('[data-stat-save]');
    const message = form.querySelector('[data-stat-message]'), total = form.querySelector('[data-stat-total]');
    const date = form.querySelector('[name="paid_on"]');
    const scope = document.querySelector('[data-stat-scope]');
    const loadedScope = [...scope.querySelectorAll('[name]')].map(field => [field, field.value]);
    let submitting = false;
    const cents = value => /^\d{1,9}(\.\d{1,2})?$/.test(value.trim()) ? Math.round(Number(value.trim()) * 100) : NaN;
    const selected = () => rows.filter(row => row.querySelector('[data-stat-select]').checked);
    function summary() {
        const chosen = selected();
        let sum = 0, invalid = false;
        chosen.forEach(row => {
            const input = row.querySelector('[data-stat-amount]'), amount = cents(input.value);
            const bad = !Number.isSafeInteger(amount) || amount <= 0 || amount > Number(row.dataset.balance);
            input.setAttribute('aria-invalid', String(bad));
            invalid ||= bad;
            if (!bad) sum += amount;
        });
        all.checked = chosen.length === rows.length;
        all.indeterminate = chosen.length > 0 && chosen.length < rows.length;
        const earliest = chosen.map(row => row.dataset.earliest).sort().pop() || '';
        date.min = earliest;
        total.textContent = `${chosen.length} month${chosen.length === 1 ? '' : 's'} · LKR ${(sum / 100).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        const changedScope = loadedScope.some(([field, value]) => field.value !== value);
        save.disabled = changedScope || submitting || !chosen.length || invalid || sum > 99999999999;
        message.textContent = changedScope ? 'Load pending months to apply the changed company, payment type or year before recording a batch.' : invalid ? 'Each selected month needs an amount above zero, within its pending balance, with up to two decimals.' : sum > 99999999999 ? 'The batch exceeds LKR 999,999,999.99.' : chosen.length ? `Payment date must be on or after ${earliest}. Unpaid balances remain pending.` : 'Select at least one pending month.';
    }
    function toggle(row, checked) {
        row.querySelector('[data-stat-select]').checked = checked;
        const input = row.querySelector('[data-stat-amount]');
        input.disabled = !checked;
        if (checked && !input.value) input.value = (Number(row.dataset.balance) / 100).toFixed(2);
        if (!checked) input.removeAttribute('aria-invalid');
    }
    rows.forEach(row => {
        row.querySelector('[data-stat-select]').addEventListener('change', event => { toggle(row, event.target.checked); summary(); });
        row.querySelector('[data-stat-amount]').addEventListener('input', summary);
    });
    all.addEventListener('change', () => { rows.forEach(row => toggle(row, all.checked)); summary(); });
    scope.addEventListener('change', summary);
    scope.addEventListener('input', summary);
    scope.addEventListener('submit', event => {
        if (selected().length && !window.confirm('Loading months clears the current selection and entered amounts. Continue?')) event.preventDefault();
    });
    form.addEventListener('invalid', () => { message.textContent = 'Check the highlighted payment field before recording this batch.'; }, true);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        summary();
        if (save.disabled) { message.focus(); return; }
        form.querySelector('[name="entries_json"]').value = JSON.stringify(selected().map(row => ({run_id: Number(row.dataset.run), amount: row.querySelector('[data-stat-amount]').value.trim()})));
        submitting = true; save.disabled = true; save.textContent = 'Recording batch…'; message.textContent = 'Saving the selected months. Please wait.';
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(response.status === 419 ? 'Your session expired. Refresh the page before retrying.' : Object.values(data.errors || {}).flat().join(' ') || 'Unable to save. Check payment batch history before retrying.');
            if (!data.redirect) throw new Error('The response was incomplete. Check payment batch history before retrying.');
            window.location.assign(data.redirect);
        } catch (error) {
            submitting = false; save.textContent = 'Record payment batch'; summary();
            message.textContent = error instanceof TypeError ? 'Connection interrupted. Check payment batch history before retrying.' : error.message; message.focus();
        }
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    summary();
})();
