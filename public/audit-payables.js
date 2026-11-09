/* Native disclosures remain usable without JavaScript. Links also reveal their evidence. */
(() => {
    const report = document.querySelector('.audit-report');
    if (!report) return;

    function reveal(hash, focus = false) {
        const target = document.getElementById(hash.slice(1));
        if (!target || !report.contains(target)) return;
        let parent = target;
        while (parent && parent !== report) {
            if (parent.tagName === 'DETAILS') parent.open = true;
            parent = parent.parentElement;
        }
        if (focus) {
            const summary = target.querySelector('summary');
            if (summary) summary.focus({ preventScroll: true });
        }
        target.scrollIntoView({ block: 'start' });
    }

    report.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-audit-open]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const hash = link.getAttribute('href');
        if (location.hash !== hash) history.pushState(null, '', hash);
        reveal(hash, true);
    });
    window.addEventListener('hashchange', () => reveal(location.hash));
    if (location.hash) reveal(location.hash);

    // Printing the web page includes all evidence, just like the dedicated exports.
    let closedBeforePrint = [];
    window.addEventListener('beforeprint', () => {
        closedBeforePrint = [...report.querySelectorAll('details:not([open])')];
        closedBeforePrint.forEach((detail) => { detail.open = true; });
    });
    window.addEventListener('afterprint', () => {
        closedBeforePrint.forEach((detail) => { detail.open = false; });
        closedBeforePrint = [];
    });
})();
