(() => {
    const root = document.documentElement;
    const themeButton = document.getElementById('themeToggleBtn');
    function syncTheme() {
        const dark = root.dataset.theme === 'dark';
        themeButton.querySelector('use').setAttribute('href', dark ? '#auth-sun' : '#auth-moon');
        themeButton.querySelector('span').textContent = dark ? 'Light mode' : 'Dark mode';
        themeButton.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    }
    syncTheme();
    themeButton.addEventListener('click', () => {
        root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('theme', root.dataset.theme); } catch (_) {}
        syncTheme();
    });
    const password = document.getElementById('password');
    const toggle = document.getElementById('togglePasswordBtn');
    toggle.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.title = show ? 'Hide password' : 'Show password';
    });
    const capsLock = document.getElementById('capsLockHint');
    password.addEventListener('keyup', event => {capsLock.hidden = !event.getModifierState('CapsLock');});
    password.addEventListener('blur', () => {capsLock.hidden = true;});
    const form = document.getElementById('loginForm');
    const submit = form.querySelector('[type="submit"]');
    form.addEventListener('submit', () => {
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.querySelector('span').textContent = 'Signing in…';
    });
    window.addEventListener('pageshow', () => {
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
        submit.querySelector('span').textContent = 'Sign in';
    });
    // Keep installation optional and in the form flow, without covering controls.
    const installPrompt = document.getElementById('authInstallPrompt');
    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        deferredPrompt = event;
        let dismissed = false;
        try { dismissed = localStorage.getItem('pwa_install_dismissed') === 'true'; } catch (_) {}
        installPrompt.hidden = dismissed;
    });
    document.getElementById('dismissInstallBtn').addEventListener('click', () => {
        installPrompt.hidden = true;
        try { localStorage.setItem('pwa_install_dismissed', 'true'); } catch (_) {}
    });
    document.getElementById('installAppBtn').addEventListener('click', async () => {
        if (!deferredPrompt) return;
        try {
            await deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            if (choice.outcome === 'accepted') {
                try { localStorage.setItem('pwa_install_dismissed', 'true'); } catch (_) {}
            }
        } finally {deferredPrompt = null;installPrompt.hidden = true;}
    });
    if ('serviceWorker' in navigator) window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
})();
