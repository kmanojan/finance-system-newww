document.addEventListener('DOMContentLoaded', () => {
    // Keep iOS PWA links in standalone mode
    if (window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches) {
        document.addEventListener('click', (e) => {
            const target = e.target.closest('a');
            if (
                target &&
                target.href &&
                target.href.startsWith(window.location.origin) &&
                !target.getAttribute('target') &&
                !target.hasAttribute('download') &&
                !target.href.includes('#')
            ) {
                e.preventDefault();
                window.location.href = target.href;
            }
        });
    }
});
