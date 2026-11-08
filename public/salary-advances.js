(() => {
    const root = document.querySelector('.sa-workspace');
    if (!root) return;
    let trigger;
    root.querySelectorAll('[data-advance-manage]').forEach(button => button.addEventListener('click', () => {
        trigger = button;
        document.getElementById(button.dataset.advanceManage).showModal();
    }));
    const sync = () => document.body.classList.toggle('sa-dialog-open', !!root.querySelector('.sa-dialog[open]'));
    root.querySelectorAll('.sa-dialog').forEach(dialog => {
        dialog.querySelectorAll('[data-advance-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('close', () => { sync(); trigger?.focus(); });
        dialog.addEventListener('click', event => { if (event.target === dialog) { const box = dialog.getBoundingClientRect(); if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close(); } });
        new MutationObserver(sync).observe(dialog, {attributes:true,attributeFilter:['open']});
        if (dialog.hasAttribute('data-reopen')) dialog.showModal();
    });
    root.querySelector('[data-advance-cancel]')?.addEventListener('click', () => {
        document.getElementById('new-advance').open = false;
        if (location.hash === '#new-advance') history.replaceState(null, '', location.pathname + location.search);
        root.querySelector('a[href="#new-advance"]').focus();
    });
    if (location.hash === '#new-advance') document.getElementById('new-advance').open = true;
    const filters = root.querySelector('.sa-more-filters');
    const mobile = matchMedia('(max-width:600px)');
    const syncFilters = () => { if (filters) filters.open = !mobile.matches; };
    syncFilters(); mobile.addEventListener('change', syncFilters);
    document.addEventListener('click', event => { if (mobile.matches && filters?.open && !filters.contains(event.target)) filters.open = false; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && mobile.matches && filters?.open) { filters.open = false; filters.querySelector('summary').focus(); } });
    sync();
})();
