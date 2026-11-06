(() => {
    const tabs = [...document.querySelectorAll('[data-loan-tab]')];
    if (!tabs.length) return;
    const fragments = {schedule: '#interest-schedule', principal: '#principal-records', flow: '#loan-flow', details: '#loan-details'};
    const selectedFromHash = () => location.hash === '#loan-description' ? 'details' : Object.keys(fragments).find(key => fragments[key] === location.hash) || 'schedule';
    const select = (name, {focus = false, history = false, scroll = false} = {}) => {
        if (!fragments[name]) return;
        tabs.forEach(tab => {
            const active = tab.dataset.loanTab === name;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            document.getElementById(tab.getAttribute('aria-controls')).hidden = !active;
            if (active && focus) { tab.focus({preventScroll:true}); tab.scrollIntoView({block:'nearest', inline:'nearest'}); }
        });
        if (history && location.hash !== fragments[name]) window.history.pushState(null, '', fragments[name]);
        document.dispatchEvent(new CustomEvent('loan:tabchange', {detail: name}));
        if (scroll && location.hash) requestAnimationFrame(() => {
            const target = location.hash === '#loan-description' ? document.getElementById('loan-description') : document.querySelector('.loan-tabs');
            target?.scrollIntoView({block:'start'});
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(tab.dataset.loanTab, {history:true}));
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next === undefined) return;
            event.preventDefault();
            select(tabs[next].dataset.loanTab, {focus:true, history:true});
        });
    });
    document.querySelectorAll('[data-open-loan-tab]').forEach(button => button.addEventListener('click', () => select(button.dataset.openLoanTab, {focus:true, history:true})));
    window.addEventListener('hashchange', () => select(selectedFromHash(), {scroll:true}));
    window.addEventListener('popstate', () => select(selectedFromHash()));
    select(selectedFromHash(), {scroll:!!location.hash});
    document.addEventListener('click', event => {
        document.querySelectorAll('.loan-more[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.loan-more[open]').forEach(menu => { menu.open = false; menu.querySelector('summary').focus(); });
    });
})();
