/* Progressive tabs: both sections remain available without JavaScript. */
(() => {
    const tablist = document.querySelector('[data-statutory-tabs]');
    if (!tablist) return;
    const tabs = [...tablist.querySelectorAll('[data-statutory-view]')];
    const panels = [...document.querySelectorAll('[data-statutory-panel]')];
    tablist.setAttribute('role', 'tablist');
    tabs.forEach(tab => {
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', `statutory-${tab.dataset.statutoryView}`);
    });
    panels.forEach(panel => { panel.setAttribute('role', 'tabpanel'); panel.tabIndex = 0; });
    function select(name) {
        tabs.forEach(tab => {
            const active = tab.dataset.statutoryView === name;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.statutoryPanel !== name; });
    }
    function restore() { select(location.hash === '#statutory-employees' ? 'employees' : 'details'); }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', event => {
            event.preventDefault();
            select(tab.dataset.statutoryView);
            history.replaceState(null, '', tab.getAttribute('href'));
        });
        tab.addEventListener('keydown', event => {
            const next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : null;
            if (next === null) return;
            event.preventDefault(); tabs[next].click(); tabs[next].focus();
        });
    });
    // Header shortcuts can target payment forms while the Employees tab is active.
    document.querySelectorAll('.sd-workspace a[href^="#"]').forEach(link => {
        if (link.closest('[data-statutory-tabs]')) return;
        link.addEventListener('click', () => select('details'));
    });
    window.addEventListener('hashchange', restore);
    restore();
})();
