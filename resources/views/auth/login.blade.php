<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#8b5cf6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Finance">
    <meta name="application-name" content="Finance">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="/icons/icon-512x512.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('styles.css') }}" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-page);
            background-image: radial-gradient(circle at top right, var(--primary-light), transparent 40%),
                              radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.1), transparent 40%);
        }
        .login-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 440px;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-gradient);
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: var(--primary-gradient);
            color: #fff;
            border-radius: 14px;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: var(--shadow-btn);
            margin-right: 1rem;
        }
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: -0.5px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .login-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-heading);
            margin-bottom: 0.5rem;
        }
        .login-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-heading);
            font-size: 0.85rem;
        }
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            color: var(--text-light);
            font-size: 1.2rem;
            pointer-events: none;
        }
        .form-control-icon {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background-color: var(--bg-sidebar-primary);
            color: var(--text-heading);
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-control-icon:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background-color: var(--bg-card);
        }
        .form-control-icon::placeholder {
            color: var(--text-light);
        }
        .password-toggle-btn {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.2rem;
            transition: color 0.2s ease, transform 0.1s ease;
            z-index: 2;
        }
        .password-toggle-btn:hover {
            color: var(--text-heading);
        }
        .password-toggle-btn:active {
            transform: scale(0.92);
        }
        .error-message {
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 0.5rem;
            display: block;
        }
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 10px;
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }
        .theme-toggle-container {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }
        .theme-toggle-btn {
            background: var(--bg-sidebar-primary);
            border: 1px solid var(--border);
            color: var(--text-heading);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
        }
        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            .login-card {
                padding: 2rem 1.25rem;
                border-radius: 16px;
                margin: 0 auto;
                max-width: 100%;
            }
            .theme-toggle-container {
                top: 1rem;
                right: 1rem;
            }
        }
    </style>
</head>
<body>

    <div class="theme-toggle-container">
        <button class="theme-toggle-btn" id="themeToggleBtn" title="Toggle Theme">
            <ion-icon name="moon-outline"></ion-icon>
        </button>
    </div>

    <div class="login-card">
        <div class="logo-container">
            <div class="logo-icon">F</div>
            <div class="logo-text">Finance System</div>
        </div>

        <div class="login-header">
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Please sign in to access your account</p>
        </div>

        <form method="POST" action="/login">
            @csrf
            
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-group">
                    <ion-icon name="mail-outline" class="input-icon"></ion-icon>
                    <input id="email" type="email" name="email" class="form-control-icon" value="{{ old('email') }}" required autofocus placeholder="name@company.com">
                </div>
                @error('email')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <ion-icon name="lock-closed-outline" class="input-icon"></ion-icon>
                    <input id="password" type="password" name="password" class="form-control-icon" value="" required placeholder="••••••••" style="padding-right: 2.75rem;">
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Show password" aria-label="Toggle password visibility">
                        <ion-icon name="eye-outline" id="togglePasswordIcon"></ion-icon>
                    </button>
                </div>
                @error('password')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; font-size: 0.85rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted); cursor: pointer;">
                    <input type="checkbox" name="remember" value="1" checked style="accent-color: var(--primary); width: 16px; height: 16px; border-radius: 4px;">
                    Keep me signed in (Remember me)
                </label>
            </div>

            <button type="submit" class="btn btn-primary-gradient btn-login">
                Sign In
                <ion-icon name="arrow-forward-outline"></ion-icon>
            </button>
        </form>
    </div>

    <script>
        // Theme Toggle Logic
        const themeBtn = document.getElementById('themeToggleBtn');
        const themeIcon = themeBtn.querySelector('ion-icon');
        
        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.setAttribute('name', 'sunny-outline');
            } else {
                themeIcon.setAttribute('name', 'moon-outline');
            }
        }
        
        const initialTheme = document.documentElement.getAttribute('data-theme');
        updateThemeIcon(initialTheme);
        
        themeBtn.addEventListener('click', () => {
            const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        // Show / Hide Password Toggle
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (togglePasswordBtn && passwordInput && togglePasswordIcon) {
            togglePasswordBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                togglePasswordIcon.setAttribute('name', isPassword ? 'eye-off-outline' : 'eye-outline');
                togglePasswordBtn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
            });
        }

        // Service Worker Registration for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('PWA Service Worker registered:', reg.scope))
                    .catch(err => console.log('PWA Service Worker registration failed:', err));
            });
        }

        // PWA Install Prompt Handling
        window.deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            window.deferredPrompt = e;
            
            const isDismissed = localStorage.getItem('pwa_install_dismissed') === 'true';
            const installBanner = document.getElementById('pwaInstallBanner');
            if (installBanner && !isDismissed) {
                installBanner.style.display = 'flex';
            }
        });

        window.dismissPWAInstall = function() {
            localStorage.setItem('pwa_install_dismissed', 'true');
            const installBanner = document.getElementById('pwaInstallBanner');
            if (installBanner) installBanner.style.display = 'none';
        };

        window.installPWA = function() {
            if (window.deferredPrompt) {
                window.deferredPrompt.prompt();
                window.deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        localStorage.setItem('pwa_install_dismissed', 'true');
                    }
                    window.deferredPrompt = null;
                    const installBanner = document.getElementById('pwaInstallBanner');
                    if (installBanner) installBanner.style.display = 'none';
                });
            } else {
                alert('To install on iOS: Tap Share > "Add to Home Screen". On Chrome/Desktop: Click install in browser address bar.');
            }
        };
    </script>

    <!-- PWA Install Banner -->
    <div id="pwaInstallBanner" style="display:none; position:fixed; bottom:20px; left:50%; transform:translateX(-50%); width:calc(100% - 32px); max-width:440px; background:var(--bg-card); border:1px solid var(--primary); border-radius:14px; padding:0.85rem 1rem; box-shadow:0 12px 36px rgba(0,0,0,0.35); z-index:99999; justify-content:space-between; align-items:center; gap:0.75rem;">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <img src="/icons/icon-192x192.png" alt="App Logo" style="width:40px; height:40px; border-radius:10px; flex-shrink:0;">
            <div>
                <strong style="font-size:0.9rem; color:var(--text-heading); display:block; line-height:1.2;">Install Finance App</strong>
                <span style="font-size:0.75rem; color:var(--text-muted);">Standalone Mobile Experience</span>
            </div>
        </div>
        <div style="display:flex; gap:0.4rem; align-items:center;">
            <button type="button" onclick="dismissPWAInstall()" style="background:transparent; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer; padding:0.2rem 0.4rem;" title="Don't show again">&times;</button>
            <button type="button" onclick="installPWA()" class="btn btn-primary" style="padding:0.4rem 0.85rem; font-size:0.82rem; font-weight:700; border-radius:8px;">Install</button>
        </div>
    </div>
</body>
</html>
