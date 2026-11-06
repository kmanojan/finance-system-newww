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
});
