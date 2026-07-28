<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Finance System')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@200;300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="{{ asset('styles.css') }}" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <style>
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

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="logo">
            <span>F</span>
        </div>
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <ion-icon name="menu-outline"></ion-icon>
        </button>
    </div>

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
                    <a href="/reminders" class="nav-item {{ request()->is('reminders*', 'approvals*', 'cheques*', 'activity-logs*', 'treasury*', 'assets*') ? 'active' : '' }}">
                        <ion-icon name="cog-outline"></ion-icon>
                        <span>Operations</span>
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

    @yield('modals')

    <script src="{{ asset('script.js') }}"></script>
    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

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
    </script>
    @yield('scripts')
</body>

</html>
