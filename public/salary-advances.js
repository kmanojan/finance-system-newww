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
        dialog.addEventListener('close', () => {
            sync();
            if (dialog.id === 'new-advance' && location.hash === '#new-advance') history.replaceState(null, '', location.pathname + location.search);
            (trigger || root.querySelector('a[href="#new-advance"]'))?.focus();
        });
        dialog.addEventListener('click', event => { if (event.target === dialog) { const box = dialog.getBoundingClientRect(); if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close(); } });
        new MutationObserver(sync).observe(dialog, {attributes:true,attributeFilter:['open']});
        if (dialog.hasAttribute('data-reopen')) dialog.showModal();
    });
    const createDialog = document.getElementById('new-advance');
    root.querySelectorAll('a[href="#new-advance"]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        trigger = link;
        if (!createDialog.open) createDialog.showModal();
    }));
    const openFromHash = () => { if (location.hash === '#new-advance' && !createDialog.open) createDialog.showModal(); };
    openFromHash();
    window.addEventListener('hashchange', openFromHash);
    const filters = root.querySelector('.sa-more-filters');
    const mobile = matchMedia('(max-width:600px)');
    const syncFilters = () => { if (filters) filters.open = !mobile.matches; };
    syncFilters(); mobile.addEventListener('change', syncFilters);
    document.addEventListener('click', event => { if (mobile.matches && filters?.open && !filters.contains(event.target)) filters.open = false; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && mobile.matches && filters?.open) { filters.open = false; filters.querySelector('summary').focus(); } });
    sync();
})();
