<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sign in · Apptimus Finance</title>
    <meta name="description" content="Sign in to your Apptimus Finance workspace.">
    <meta name="theme-color" content="#6941c6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Finance">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('styles.css') }}" rel="stylesheet">
    <link href="{{ asset('design-system.css') }}?v={{ filemtime(public_path('design-system.css')) }}" rel="stylesheet">
    <link href="{{ asset('auth.css') }}?v={{ filemtime(public_path('auth.css')) }}" rel="stylesheet">
    <script>
        try { document.documentElement.dataset.theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'; } catch (_) {}
    </script>
    <script defer src="{{ asset('auth.js') }}?v={{ filemtime(public_path('auth.js')) }}"></script>
</head>
<body class="auth-page">
    <svg class="auth-symbols" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
        <symbol id="auth-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
        <symbol id="auth-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="auth-moon" viewBox="0 0 24 24"><path d="M20.5 13.2A8.5 8.5 0 0 1 10.8 3.5 8.5 8.5 0 1 0 20.5 13.2Z"/></symbol>
        <symbol id="auth-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></symbol>
        <symbol id="auth-close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
    </defs></svg>
    <a class="skip-link" href="#sign-in">Skip to sign in</a>
    <div class="auth-shell">
        <aside class="auth-intro" aria-label="About Apptimus Finance">
            <div class="auth-brand"><span class="auth-monogram" aria-hidden="true">F</span><span>Apptimus <strong>Finance</strong></span></div>
            <div class="auth-intro-content">
                <h2>Your finances.<br>A clearer view.</h2>
                <p>One workspace for the money coming in, the payments going out, and the work behind it.</p>
                <dl class="auth-capabilities">
                    <div><dt>Cash flow</dt><dd>Follow every transaction.</dd></div>
                    <div><dt>Invoices &amp; payments</dt><dd>Stay on top of what’s due.</dd></div>
                    <div><dt>Projects &amp; commitments</dt><dd>Keep costs in perspective.</dd></div>
                </dl>
            </div>
            <p class="auth-intro-footer">Clarity for your everyday finance work.</p>
        </aside>
        <main class="auth-main" id="sign-in" tabindex="-1">
            <header class="auth-topbar">
                <div class="auth-brand auth-mobile-brand"><span class="auth-monogram" aria-hidden="true">F</span><span>Apptimus <strong>Finance</strong></span></div>
                <button type="button" class="auth-theme-toggle" id="themeToggleBtn" aria-label="Switch to dark mode"><svg class="auth-icon" aria-hidden="true"><use href="#auth-moon"></use></svg><span>Dark mode</span></button>
            </header>
            <div class="auth-form-area">
                <header class="auth-heading"><h1>Sign in to Finance</h1><p>Enter your details to open your workspace.</p></header>
                @if($errors->any())
                <div class="auth-error" id="login-errors" role="alert"><strong>We couldn’t sign you in.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
                @endif
                <form method="POST" action="{{ url('/login') }}" id="loginForm">
                    @csrf
                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" placeholder="you@company.com" @error('email') aria-invalid="true" aria-describedby="login-errors" @enderror>
                    </div>
                    <div class="auth-field">
                        <label for="password">Password</label>
                        <div class="auth-password-field">
                            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" @error('password') aria-invalid="true" aria-describedby="login-errors" @enderror>
                            <button type="button" class="auth-password-toggle" id="togglePasswordBtn" aria-label="Show password" aria-controls="password" aria-pressed="false" title="Show password"><svg class="auth-icon" aria-hidden="true"><use href="#auth-eye"></use><path class="auth-eye-slash" d="m3 3 18 18"/></svg></button>
                        </div>
                        <p class="auth-caps-lock" id="capsLockHint" role="status" hidden>Caps Lock is on.</p>
                    </div>
                    <input type="hidden" name="remember" value="0">
                    <label class="auth-remember" for="remember"><input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember', '1') === '1')><span>Keep me signed in</span></label>
                    <button type="submit" class="btn btn-primary auth-submit"><span>Sign in</span><svg class="auth-icon" aria-hidden="true"><use href="#auth-arrow"></use></svg></button>
                </form>
                <p class="auth-help">Need access or help signing in?<br><span>Contact your workspace administrator.</span></p>
                <section class="auth-install" id="authInstallPrompt" aria-label="Install Finance app" hidden>
                    <div><strong>Finance, a tap away.</strong><p>Install the app for quick access.</p></div>
                    <button type="button" class="btn btn-outline" id="installAppBtn">Install</button>
                    <button type="button" class="auth-icon-button" id="dismissInstallBtn" aria-label="Dismiss install suggestion"><svg class="auth-icon" aria-hidden="true"><use href="#auth-close"></use></svg></button>
                </section>
            </div>
            <footer class="auth-footer">Apptimus Finance <span aria-hidden="true">·</span> Your finance workspace</footer>
        </main>
    </div>
</body>
</html>
