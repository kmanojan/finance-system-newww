(() => {
    document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    }));
    document.querySelectorAll('select[data-company]').forEach(company => {
        const form = company.closest('form');
        const update = () => {
            form.querySelectorAll('[data-department], [data-bank]').forEach(select => {
                [...select.options].forEach(option => {
                    if (!option.value) return;
                    option.hidden = !!company.value && option.dataset.company !== company.value;
                    option.disabled = option.hidden;
                    if (option.selected && option.hidden) select.value = '';
                });
            });
        };
        company.addEventListener('change', update);
        update();
    });
    const recoveryMonth = document.getElementById('recovery_month'), advanceDate = document.getElementById('paid_on');
    if (recoveryMonth && advanceDate) {
        const updateMinimum = () => { recoveryMonth.min = advanceDate.value.slice(0,7); };
        advanceDate.addEventListener('change', updateMinimum); updateMinimum();
    }
    document.querySelectorAll('a[href="#new-advance"]').forEach(link => link.addEventListener('click', () => { document.querySelector('#new-advance').open = true; }));
    document.querySelectorAll('[data-employee-directory]').forEach(directory => {
        const search = directory.querySelector('[data-employee-search]');
        if (!search) return;
        const rows = [...directory.querySelectorAll('[data-employee-row]')];
        const filter = directory.querySelector('[data-employee-filter]');
        const empty = directory.querySelector('[data-employee-empty]');
        const range = directory.querySelector('[data-employee-range]');
        const previous = directory.querySelector('[data-employee-prev]');
        const next = directory.querySelector('[data-employee-next]');
        const pageSize = 50;
        let page = 0;

        const render = () => {
            const terms = search.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
            const status = filter?.value ?? 'all';
            const matches = rows.filter(row => (status === 'all' || row.dataset.paymentState === status)
                && terms.every(term => row.dataset.search.includes(term)));
            const start = page * pageSize;
            const end = Math.min(start + pageSize, matches.length);
            const visible = new Set(matches.slice(start, end));
            rows.forEach(row => { row.hidden = !visible.has(row); });
            empty.hidden = matches.length > 0;
            range.textContent = matches.length ? `${start + 1}–${end} of ${matches.length} employees` : '0 employees';
            previous.disabled = page === 0;
            next.disabled = end >= matches.length;
        };
        const applyFilters = () => {
            page = 0;
            render();
        };

        search.addEventListener('input', applyFilters);
        filter?.addEventListener('change', applyFilters);
        previous.addEventListener('click', () => { page--; render(); });
        next.addEventListener('click', () => { page++; render(); });
        render();
        const tools = directory.querySelector('[data-employee-tools]');
        if (tools) tools.hidden = false;
        directory.querySelector('[data-employee-paging]').hidden = false;
    });
})();
