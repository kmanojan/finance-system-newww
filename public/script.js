document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileSubMenuBtn = document.getElementById('mobileSubMenuBtn');
    const sidebarPrimary = document.getElementById('sidebarPrimary');
    const sidebarSecondary = document.getElementById('sidebarSecondary');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    function closePrimarySidebar() {
        if (sidebarPrimary) sidebarPrimary.classList.remove('open');
        if (mobileMenuBtn) mobileMenuBtn.classList.remove('active');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('active');
    }

    // Toggle Primary Sidebar
    if (mobileMenuBtn && sidebarPrimary) {
        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = sidebarPrimary.classList.contains('open');
            if (isOpen) {
                closePrimarySidebar();
            } else {
                sidebarPrimary.classList.add('open');
                mobileMenuBtn.classList.add('active');
                if (sidebarBackdrop) sidebarBackdrop.classList.add('active');
            }
        });
    }

    // Click backdrop to close
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => {
            closePrimarySidebar();
        });
    }

    // Close on primary sidebar link clicks
    document.querySelectorAll('.sidebar-primary a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closePrimarySidebar();
            }
        });
    });

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
