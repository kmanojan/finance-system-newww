(() => {
    document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    }));
    document.querySelectorAll('[data-company]').forEach(company => {
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
})();
