/* Independent, persistent navigation panels; mobile main navigation is a temporary drawer. */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const primary = document.getElementById('sidebarPrimary');
    const secondary = document.getElementById('sidebarSecondary');
    const backdrop = document.getElementById('sidebarBackdrop');
    const mobile = matchMedia('(max-width:1100px)');
    const mobileButtons = [document.getElementById('mobileMenuBtn'), document.getElementById('mobileMoreNavBtn')].filter(Boolean);
    let mobileOpen = false;
    function persist(panel, closed) {
        root.classList.toggle(`${panel}-navigation-closed`, closed);
        try { localStorage.setItem(`finance.nav.${panel}.closed`, String(closed)); } catch (_) {}
    }
    function sync() {
        const primaryOpen = mobile.matches ? mobileOpen : !root.classList.contains('primary-navigation-closed');
        primary?.classList.toggle('open', mobile.matches && mobileOpen);
        primary?.classList.toggle('active', mobile.matches && mobileOpen);
        if (primary) primary.inert = !primaryOpen;
        backdrop?.classList.toggle('active', mobile.matches && mobileOpen);
        document.body.classList.toggle('navigation-drawer-open', mobile.matches && mobileOpen);
        document.querySelectorAll('[data-open-primary],[data-close-primary]').forEach(button => button.setAttribute('aria-expanded', String(primaryOpen)));
        mobileButtons.forEach(button => {button.setAttribute('aria-expanded', String(primaryOpen));button.setAttribute('aria-controls','sidebarPrimary');button.classList.toggle('active',mobile.matches && mobileOpen);});
        const secondaryOpen = !root.classList.contains('secondary-navigation-closed');
        if (secondary) secondary.inert = !secondaryOpen;
        document.querySelectorAll('[data-toggle-secondary]').forEach(button => button.setAttribute('aria-expanded', String(secondaryOpen)));
    }
    function setPrimary(open, focus = true) {
        if (mobile.matches) mobileOpen = open;
        else persist('primary', !open);
        sync();
        if (focus) {
            if (open) primary?.querySelector('[data-close-primary]')?.focus();
            else (mobile.matches ? mobileButtons[0] : document.querySelector('[data-open-primary]'))?.focus();
        }
    }
    document.querySelectorAll('[data-open-primary]').forEach(button => button.addEventListener('click', () => setPrimary(true)));
    document.querySelectorAll('[data-close-primary]').forEach(button => button.addEventListener('click', () => setPrimary(false)));
    mobileButtons.forEach(button => button.addEventListener('click', () => setPrimary(!mobileOpen)));
    document.querySelectorAll('[data-toggle-secondary]').forEach(button => button.addEventListener('click', () => {
        const opening = root.classList.contains('secondary-navigation-closed');
        persist('secondary', !opening);
        sync();
        (opening ? secondary?.querySelector('[data-toggle-secondary]') : document.querySelector('.secondary-navigation-reopen'))?.focus();
    }));
    backdrop?.addEventListener('click', () => setPrimary(false));
    primary?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { if (mobile.matches) setPrimary(false, false); }));
    document.addEventListener('keydown', event => {
        if (!mobile.matches || !mobileOpen) return;
        if (event.key === 'Escape') { event.preventDefault(); setPrimary(false); }
        if (event.key === 'Tab') {
            const items = [...primary.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled])')].filter(el => el.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && (document.activeElement === first || !primary.contains(document.activeElement))) {event.preventDefault();last?.focus();}
            else if (!event.shiftKey && (document.activeElement === last || !primary.contains(document.activeElement))) {event.preventDefault();first?.focus();}
        }
    });
    mobile.addEventListener('change', () => {mobileOpen=false;sync();});
    // In-page tabs change their active class; keep assistive technology in sync.
    const markCurrent = () => document.querySelectorAll('.sidebar-secondary .nav-link').forEach(link => {
        if (link.classList.contains('active')) link.setAttribute('aria-current',link.getAttribute('href')?.startsWith('#') ? 'location' : 'page');
        else link.removeAttribute('aria-current');
    });
    if (secondary) new MutationObserver(markCurrent).observe(secondary,{subtree:true,attributes:true,attributeFilter:['class']});
    markCurrent();sync();

    // Full-page requests can take several seconds on a cold serverless instance.
    const loading = document.getElementById('navigationLoading');
    if (!loading) return;
    const loadingText = loading.querySelector('[data-navigation-loading-text]');
    const dismiss = loading.querySelector('[data-dismiss-navigation-loading]');
    const main = document.getElementById('main-content');
    let showTimer, slowTimer, resetTimer;
    function clearLoading() {
        clearTimeout(showTimer); clearTimeout(slowTimer); clearTimeout(resetTimer);
        loading.hidden = true;
        dismiss.hidden = true;
        main?.removeAttribute('aria-busy');
        document.querySelectorAll('[data-navigation-pending]').forEach(link => link.removeAttribute('data-navigation-pending'));
    }
    function showLoading(link) {
        clearLoading();
        const name = (link.getAttribute('aria-label') || link.textContent).trim().replace(/\s+/g, ' ').slice(0, 70);
        loadingText.textContent = name ? `Opening ${name}…` : 'Opening page…';
        link.setAttribute('data-navigation-pending', 'true');
        main?.setAttribute('aria-busy', 'true');
        showTimer = setTimeout(() => {loading.hidden = false;}, 120);
        slowTimer = setTimeout(() => {
            loadingText.textContent = navigator.onLine ? 'Still loading. This is taking longer than usual…' : 'You appear to be offline. Check your connection.';
            dismiss.hidden = false;
        }, 8000);
        // A cancelled navigation or a file response must not leave the old page busy forever.
        resetTimer = setTimeout(clearLoading, 45000);
    }
    window.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-navigation-loader') || (link.target && link.target !== '_self')) return;
        const url = new URL(link.href, location.href);
        if (!['http:', 'https:'].includes(url.protocol) || url.origin !== location.origin) return;
        if (link.getAttribute('href').startsWith('#') || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
        if (/\/(?:download|export)(?:\/|$)|\.(?:pdf|csv|xlsx?|zip|docx?|png|jpe?g)$/i.test(url.pathname)) return;
        queueMicrotask(() => {if (!event.defaultPrevented) showLoading(link);});
    });
    dismiss.addEventListener('click', clearLoading);
    document.addEventListener('keydown', event => {if (event.key === 'Escape') clearLoading();});
    window.addEventListener('pageshow', clearLoading);
    window.addEventListener('pagehide', clearLoading);
});
