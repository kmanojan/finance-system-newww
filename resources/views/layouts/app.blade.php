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
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@200;300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="{{ asset('styles.css') }}" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <style>
        /* CKEditor Custom Theme Adaptation */
        .ck.ck-editor__main>.ck-editor__editable {
            background: var(--bg-page) !important;
            color: var(--text-main) !important;
            border-color: var(--border) !important;
            min-height: 120px;
            border-bottom-left-radius: 8px !important;
            border-bottom-right-radius: 8px !important;
            font-family: inherit !important;
        }
        .ck.ck-toolbar {
            background: var(--bg-card) !important;
            border-color: var(--border) !important;
            border-top-left-radius: 8px !important;
            border-top-right-radius: 8px !important;
        }
        .ck.ck-toolbar .ck-button, .ck.ck-dropdown__panel {
            color: var(--text-main) !important;
        }
        .ck.ck-toolbar .ck-button:hover, .ck.ck-toolbar .ck-button.ck-on {
            background: var(--primary-light) !important;
            color: var(--primary) !important;
        }
        .ck.ck-dropdown__panel {
            background: var(--bg-card) !important;
            border: 1px solid var(--border) !important;
        }
        .ck.ck-list__item .ck-button {
            color: var(--text-main) !important;
        }
        .ck.ck-list__item .ck-button:hover {
            background: var(--primary-light) !important;
        }
        
        /* Base form styles */
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-heading); font-size: 0.9rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: var(--text-main); transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem; padding-right: 2.5rem; }
        .form-row { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .form-col { flex: 1; min-width: 250px; }
        
        /* Modal Styles */
        .modal-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .modal-backdrop.active { opacity: 1; pointer-events: auto; }
        .modal-card { background: var(--bg-card); width: 100%; max-width: 600px; border-radius: 16px; box-shadow: var(--shadow-card); display: flex; flex-direction: column; max-height: 90vh; transform: scale(0.95); transition: transform 0.3s; overflow: hidden; }
        .modal-backdrop.active .modal-card { transform: scale(1); }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); z-index: 10; }
        .modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-heading); }
        .btn-close { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; }
        .modal-card form { display: flex; flex-direction: column; flex: 1; min-height: 0; }
        .modal-body { padding: 2rem; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 1.5rem 2rem; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 1rem; }
    </style>
</head>

<body>

    <!-- Backdrop Overlay for Mobile Drawers -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="mobile-header-left">
            <div class="logo">
                <span>F</span>
            </div>
            <span style="font-weight: 700; color: var(--text-heading); font-size: 1.1rem; letter-spacing: -0.5px;">Finance</span>
        </div>
        <div class="mobile-header-right">
            <button type="button" class="mobile-nav-btn" id="mobileThemeToggleBtn" title="Toggle Dark/Light Mode">
                <ion-icon name="moon-outline"></ion-icon>
            </button>
            <button type="button" class="mobile-nav-btn" id="mobileMenuBtn" title="Main Navigation">
                <ion-icon name="menu-outline"></ion-icon>
            </button>
        </div>
    </header>

    <div class="app-layout">
        <!-- Primary Navbar (Leftmost layer) -->
        <aside class="sidebar-primary" id="sidebarPrimary">
            <div class="sidebar-top">
                <div class="logo">
                    <span>F</span>
                </div>
                <nav class="nav-icons">
                    <a href="/dashboard" class="nav-item {{ request()->is('dashboard*') || request()->is('/') ? 'active' : '' }}">
                        <ion-icon name="grid-outline"></ion-icon>
                        <span>Dashboard</span>
                    </a>
                    <a href="/master" class="nav-item {{ request()->is('master*') ? 'active' : '' }}">
                        <ion-icon name="business-outline"></ion-icon>
                        <span>Master</span>
                    </a>
                    <a href="/employees" class="nav-item {{ request()->is('employees*') ? 'active' : '' }}">
                        <ion-icon name="people-outline"></ion-icon>
                        <span>Employees</span>
                    </a>
                    <a href="/projects" class="nav-item {{ request()->is('projects*') ? 'active' : '' }}" style="position:relative;">
                        <ion-icon name="briefcase-outline"></ion-icon>
                        <span>Projects</span>
                        @php
                            $globalMilestoneCount = \Illuminate\Support\Facades\DB::table('payment_milestones')
                                ->where('status', 'pending')
                                ->where('due_date', date('Y-m-d'))
                                ->count();
                        @endphp
                        @if($globalMilestoneCount > 0)
                            <div style="position:absolute; top:2px; right:2px; background:var(--danger); color:white; font-size:0.65rem; padding:0.1rem 0.35rem; border-radius:50%; font-weight:bold;">
                                {{ $globalMilestoneCount }}
                            </div>
                        @endif
                    </a>
                    <a href="/transactions" class="nav-item {{ request()->is('transactions*') ? 'active' : '' }}">
                        <ion-icon name="cash-outline"></ion-icon>
                        <span>Ledger</span>
                    </a>
                    <a href="/invoices" class="nav-item {{ request()->is('invoices*', 'payables*') ? 'active' : '' }}">
                        <ion-icon name="document-text-outline"></ion-icon>
                        <span>Invoices</span>
                    </a>
                    <a href="/loans" class="nav-item {{ request()->is('loans*') ? 'active' : '' }}">
                        <ion-icon name="card-outline"></ion-icon>
                        <span>Loans</span>
                    </a>
                    <a href="/reminders" class="nav-item {{ request()->is('reminders*', 'approvals*', 'cheques*', 'activity-logs*', 'treasury*', 'assets*') ? 'active' : '' }}" style="position:relative;">
                        <ion-icon name="cog-outline"></ion-icon>
                        <span>Operations</span>
                        @if(!empty($hasActiveReminders))
                            <span class="notification-dot" style="position:absolute; top:6px; right:8px; width:8px; height:8px; background:#ef4444; border-radius:50%; box-shadow:0 0 0 2px var(--bg-card); display:inline-block;" title="Active Reminders Available"></span>
                        @endif
                    </a>

                    <a href="/reports" class="nav-item {{ request()->is('reports*') ? 'active' : '' }}">
                        <ion-icon name="pie-chart-outline"></ion-icon>
                        <span>Reports</span>
                    </a>
                    <a href="/share-links" class="nav-item {{ request()->is('share-links*') ? 'active' : '' }}">
                        <ion-icon name="link-outline"></ion-icon>
                        <span>Sharing</span>
                    </a>

                </nav>
            </div>
            <div class="sidebar-bottom" style="display:flex; flex-direction:column; align-items:center; gap: 1rem;">
                @auth
                    <x-sidebar.quick-add-cost />
                @endauth
                <button id="themeToggleBtn" style="background:none; border:none; color:var(--text-light); font-size:1.4rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:color 0.2s;" title="Toggle Theme">
                    <ion-icon name="moon-outline"></ion-icon>
                </button>
                <a href="/profile" style="text-decoration: none;">
                    <div class="user-avatar" title="{{ Auth::user()->name ?? 'Profile & Settings' }}">
                        <span>{{ Auth::check() ? strtoupper(substr(Auth::user()->name, 0, 1)) : 'A' }}</span>
                    </div>
                </a>
                <form method="POST" action="{{ route('logout') }}" style="margin: 0; padding: 0;">
                    @csrf
                    <button type="submit" style="background:none; border:none; color:var(--text-light); font-size:1.4rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:color 0.2s;" title="Log Out" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-light)'">
                        <ion-icon name="log-out-outline"></ion-icon>
                    </button>
                </form>
            </div>
        </aside>

        @yield('secondary-sidebar')

        <!-- Main Content Area -->
        <main class="main-content @if(!View::hasSection('secondary-sidebar')) full-width @endif">
            <div class="content-card">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Mobile Bottom Navigation Bar (PWA 1-Handed Navigation) -->
    <nav class="mobile-bottom-nav">
        <a href="/dashboard" class="mobile-bottom-nav-item {{ request()->is('dashboard*') || request()->is('/') ? 'active' : '' }}">
            <ion-icon name="grid-outline"></ion-icon>
            <span>Home</span>
        </a>
        <a href="/transactions" class="mobile-bottom-nav-item {{ request()->is('transactions*') ? 'active' : '' }}">
            <ion-icon name="cash-outline"></ion-icon>
            <span>Ledger</span>
        </a>
        <a href="/projects" class="mobile-bottom-nav-item {{ request()->is('projects*') ? 'active' : '' }}">
            <ion-icon name="briefcase-outline"></ion-icon>
            <span>Projects</span>
        </a>
        <a href="/invoices" class="mobile-bottom-nav-item {{ request()->is('invoices*', 'payables*') ? 'active' : '' }}">
            <ion-icon name="document-text-outline"></ion-icon>
            <span>Invoices</span>
        </a>
        <a href="/loans" class="mobile-bottom-nav-item {{ request()->is('loans*') ? 'active' : '' }}">
            <ion-icon name="card-outline"></ion-icon>
            <span>Loans</span>
        </a>
        <button type="button" class="mobile-bottom-nav-item" id="mobileMoreNavBtn" style="background:none; border:none; cursor:pointer;">
            <ion-icon name="ellipsis-horizontal-circle-outline"></ion-icon>
            <span>More</span>
        </button>
    </nav>

    <!-- Global Toast Container -->
    <div class="toast-container" id="globalToastContainer"></div>

    <!-- Global Confirmation Dialog -->
    <x-confirm-modal />

    @yield('modals')

    <script src="{{ asset('script.js') }}"></script>
    <script>
        // Modal Helpers
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        }

        // Close active modal on Escape key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModals = document.querySelectorAll('.modal-backdrop.active');
                activeModals.forEach(m => m.classList.remove('active'));
            }
        });

        // Close modal when clicking outside of modal card
        document.addEventListener('click', function(e) {
            if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
                e.target.classList.remove('active');
            }
        });

        // Toast Notification Function
        window.showToast = function(message, type = 'success') {
            const container = document.getElementById('globalToastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            
            let iconName = 'checkmark-circle-outline';
            if (type === 'error' || type === 'danger') iconName = 'alert-circle-outline';
            if (type === 'warning') iconName = 'warning-outline';
            if (type === 'info') iconName = 'information-circle-outline';

            toast.innerHTML = `
                <div class="toast-icon"><ion-icon name="${iconName}"></ion-icon></div>
                <div class="toast-content">${message}</div>
                <button type="button" class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('toast-hiding');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        };

        // Flash message toasts from Laravel Session
        @if(session('success'))
            window.showToast(@json(session('success')), 'success');
        @endif
        @if(session('error'))
            window.showToast(@json(session('error')), 'error');
        @endif
        @if(session('warning'))
            window.showToast(@json(session('warning')), 'warning');
        @endif
        @if(session('info'))
            window.showToast(@json(session('info')), 'info');
        @endif

        // Global Custom Confirmation Modal
        window.confirmAction = function(options) {
            const title = options.title || 'Are you sure?';
            const message = options.message || 'This action cannot be undone.';
            const confirmText = options.confirmText || 'Confirm';
            const isDanger = options.isDanger !== false;

            document.getElementById('globalConfirmTitle').textContent = title;
            document.getElementById('globalConfirmMessage').textContent = message;
            
            const confirmBtn = document.getElementById('globalConfirmBtn');
            confirmBtn.textContent = confirmText;
            confirmBtn.style.background = isDanger ? 'var(--danger)' : 'var(--primary)';

            const icon = document.getElementById('globalConfirmIcon');
            const iconWrap = document.getElementById('globalConfirmIconWrapper');
            if (isDanger) {
                icon.name = 'alert-circle-outline';
                iconWrap.style.background = 'rgba(239, 68, 68, 0.15)';
                iconWrap.style.color = 'var(--danger)';
            } else {
                icon.name = 'help-circle-outline';
                iconWrap.style.background = 'var(--primary-light)';
                iconWrap.style.color = 'var(--primary)';
            }

            confirmBtn.onclick = function() {
                closeModal('globalConfirmModal');
                if (typeof options.onConfirm === 'function') {
                    options.onConfirm();
                } else if (options.formId) {
                    document.getElementById(options.formId).submit();
                }
            };

            openModal('globalConfirmModal');
            return false;
        };

        // Global Helper to set values for amount-input components
        window.setAmountInputValue = function(id, val) {
            const input = document.getElementById(id);
            if (!input) return;
            const hidden = input.parentElement ? input.parentElement.querySelector('.amount-hidden') : null;
            if (val !== null && val !== undefined && val !== '' && !isNaN(val)) {
                const num = parseFloat(val);
                input.value = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (hidden) {
                    hidden.value = num.toFixed(2);
                    hidden.dispatchEvent(new Event('input', { bubbles: true }));
                }
            } else {
                input.value = '';
                if (hidden) {
                    hidden.value = '';
                    hidden.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        };

        // Double-Submission Guard & Button Loading Spinners on all forms
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.dataset.submitting === 'true') {
                e.preventDefault();
                return false;
            }
            const submitBtn = form.querySelector('button[type="submit"]:not([data-no-spinner])');
            if (submitBtn) {
                form.dataset.submitting = 'true';
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<span class="spinner-icon"></span> Processing...`;
                
                // Safety timeout in case of client-side abort
                setTimeout(() => {
                    form.dataset.submitting = 'false';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 8000);
            }
        });

        // Theme Toggle Logic
        function updateThemeIcons(theme) {
            const icons = document.querySelectorAll('#themeToggleBtn ion-icon, #mobileThemeToggleBtn ion-icon');
            icons.forEach(icon => {
                icon.setAttribute('name', theme === 'dark' ? 'sunny-outline' : 'moon-outline');
            });
        }
        
        const initialTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        updateThemeIcons(initialTheme);
        
        function toggleTheme() {
            const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcons(newTheme);
        }

        const themeBtn = document.getElementById('themeToggleBtn');
        if (themeBtn) themeBtn.addEventListener('click', toggleTheme);

        const mobileThemeBtn = document.getElementById('mobileThemeToggleBtn');
        if (mobileThemeBtn) mobileThemeBtn.addEventListener('click', toggleTheme);

        // Mobile More Menu trigger
        const moreNavBtn = document.getElementById('mobileMoreNavBtn');
        if (moreNavBtn) {
            moreNavBtn.addEventListener('click', function() {
                const primarySidebar = document.getElementById('sidebarPrimary');
                const backdrop = document.getElementById('sidebarBackdrop');
                if (primarySidebar && backdrop) {
                    primarySidebar.classList.toggle('active');
                    backdrop.classList.toggle('active');
                }
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
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const installBanner = document.getElementById('pwaInstallBanner');
            if (installBanner) {
                installBanner.style.display = 'flex';
            }
        });

        window.installPWA = function() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the PWA install prompt');
                    }
                    deferredPrompt = null;
                    const installBanner = document.getElementById('pwaInstallBanner');
                    if (installBanner) installBanner.style.display = 'none';
                });
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
            <button type="button" onclick="document.getElementById('pwaInstallBanner').style.display='none'" style="background:transparent; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer; padding:0.2rem 0.4rem;">&times;</button>
            <button type="button" onclick="installPWA()" class="btn btn-primary" style="padding:0.4rem 0.85rem; font-size:0.82rem; font-weight:700; border-radius:8px;">Install</button>
        </div>
    </div>

    @yield('scripts')
</body>

</html>
