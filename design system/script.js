document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebarPrimary = document.getElementById('sidebarPrimary');

    if (mobileMenuBtn && sidebarPrimary) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebarPrimary.classList.toggle('open');
        });
    }

    // Close sidebar on outside click on mobile
    document.addEventListener('click', (e) => {
        if (sidebarPrimary && sidebarPrimary.classList.contains('open')) {
            if (!sidebarPrimary.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                sidebarPrimary.classList.remove('open');
            }
        }
    });
});
