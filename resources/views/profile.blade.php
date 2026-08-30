@extends('layouts.app')
@section('title', 'User Profile & Settings')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Settings</h2>
    <nav class="nav-links">
        <a href="/profile?tab=general" class="nav-link {{ $tab === 'general' ? 'active' : '' }}">General Info</a>
        <a href="/profile?tab=app" class="nav-link {{ $tab === 'app' ? 'active' : '' }}">Install App</a>
        <a href="/profile?tab=config" class="nav-link {{ $tab === 'config' ? 'active' : '' }}">Configuration</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 2rem;">
    <div class="header-titles">
        <h1>Profile & Settings</h1>
        <p class="subtitle">Manage your account information, security settings, and system configurations.</p>
    </div>
</header>

@if(session('error'))
<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 0.5rem;">
    <ion-icon name="alert-circle-outline" style="font-size: 1.25rem;"></ion-icon>
    <span>{{ session('error') }}</span>
</div>
@endif

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0; display: flex; align-items: center; gap: 0.5rem;">
    <ion-icon name="checkmark-circle-outline" style="font-size: 1.25rem;"></ion-icon>
    <span>{{ session('success') }}</span>
</div>
@endif

@if($errors->any())
<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fecaca;">
    <ul style="margin: 0; padding-left: 1.25rem;">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if($tab === 'general')
<div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start;">
    <!-- User Summary Card -->
    <div class="card" style="padding: 2rem; text-align: center;">
        <div style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 2.2rem; margin: 0 auto 1.25rem; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
            {{ strtoupper(substr($user->name ?? 'User', 0, 2)) }}
        </div>
        
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 0.25rem;">
            {{ $user->name ?? 'User' }}
        </h3>
        <p class="text-muted" style="font-size: 0.875rem; margin-bottom: 1rem;">{{ $user->email }}</p>

        @php
            $roleColors = [
                'admin' => ['bg' => '#f3e8ff', 'text' => '#7e22ce'],
                'manager' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
                'accountant' => ['bg' => '#fef3c7', 'text' => '#b45309'],
                'staff' => ['bg' => '#ecfdf5', 'text' => '#047857'],
                'viewer' => ['bg' => '#f1f5f9', 'text' => '#475569'],
            ];
            $role = strtolower($user->role ?? 'staff');
            $roleStyle = $roleColors[$role] ?? ['bg' => '#f1f5f9', 'text' => '#475569'];
        @endphp
        
        <div style="display: inline-block; margin-bottom: 1.5rem;">
            <span class="badge" style="background: {{ $roleStyle['bg'] }}; color: {{ $roleStyle['text'] }}; font-size: 0.8rem; padding: 0.35rem 0.75rem; border-radius: 6px; font-weight: 600; text-transform: capitalize;">
                {{ ucfirst($user->role ?? 'Staff') }}
            </span>
        </div>

        <div style="border-top: 1px solid var(--border-light); padding-top: 1.25rem; text-align: left; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between;">
                <span class="text-muted">Department:</span>
                <span class="font-medium" style="color: var(--text-heading);">{{ $user->department?->name ?? 'Global / None' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span class="text-muted">Phone:</span>
                <span class="font-medium" style="color: var(--text-heading);">{{ $user->phone ?: '-' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span class="text-muted">Status:</span>
                <span class="badge" style="background: {{ $user->is_active ? 'var(--success-light, #dcfce7)' : '#fee2e2' }}; color: {{ $user->is_active ? 'var(--success, #166534)' : '#991b1b' }}; font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 600;">
                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span class="text-muted">Member Since:</span>
                <span class="font-medium" style="color: var(--text-heading);">{{ $user->created_at ? $user->created_at->format('M d, Y') : '-' }}</span>
            </div>
        </div>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-light); padding-top: 1.25rem;">
            <a href="/profile?tab=app" class="btn btn-outline" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.85rem;">
                <ion-icon name="phone-portrait-outline"></ion-icon> App Installation
            </a>
        </div>
    </div>

    <!-- Edit Forms Container -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Edit Profile Details Card -->
        <div class="card" style="padding: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <ion-icon name="person-outline"></ion-icon>
                </div>
                <div>
                    <h2 class="section-title" style="margin: 0; font-size: 1.1rem;">Personal Information</h2>
                    <p class="text-muted" style="font-size: 0.8rem; margin: 0;">Update your basic account details and contact information.</p>
                </div>
            </div>

            <form action="{{ route('profile.general.update') }}" method="POST">
                @csrf
                @method('PUT')
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Full Name <span style="color:red;">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address <span style="color:red;">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" placeholder="e.g. +94 77 123 4567">
                </div>

                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary-gradient">
                        <ion-icon name="save-outline"></ion-icon> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password Card -->
        <div class="card" style="padding: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <ion-icon name="lock-closed-outline"></ion-icon>
                </div>
                <div>
                    <h2 class="section-title" style="margin: 0; font-size: 1.1rem;">Change Password</h2>
                    <p class="text-muted" style="font-size: 0.8rem; margin: 0;">Ensure your account remains secure with a strong password.</p>
                </div>
            </div>

            <form action="{{ route('profile.password.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label">Current Password <span style="color:red;">*</span></label>
                    <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">New Password <span style="color:red;">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password <span style="color:red;">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm new password" required>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary-gradient">
                        <ion-icon name="key-outline"></ion-icon> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($tab === 'app')
<div style="display: flex; flex-direction: column; gap: 1.5rem; max-width: 800px;">
    <div class="card" style="padding: 2rem;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 1.25rem;">
            <div style="width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <ion-icon name="phone-portrait-outline"></ion-icon>
            </div>
            <div>
                <h2 class="section-title" style="margin: 0; font-size: 1.25rem;">Install Finance App</h2>
                <p class="text-muted" style="font-size: 0.85rem; margin: 0.2rem 0 0;">Add the Finance Management System directly to your device home screen for quick offline-ready standalone access.</p>
            </div>
        </div>

        <div style="background: var(--bg-page); border: 1px solid var(--border-light); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.75rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <img src="/icons/icon-192x192.png" alt="App Icon" style="width: 48px; height: 48px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <div>
                    <strong style="color: var(--text-heading); font-size: 1rem; display: block;">Finance Management App</strong>
                    <span id="appInstallStatusText" class="text-muted" style="font-size: 0.8rem;">Ready for standalone installation</span>
                </div>
            </div>
            <div>
                <button type="button" onclick="installPWA()" id="btnInstallPWAProfile" class="btn btn-primary-gradient" style="padding: 0.65rem 1.25rem; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                    <ion-icon name="download-outline" style="font-size: 1.1rem;"></ion-icon> Install App Now
                </button>
            </div>
        </div>

        <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-heading); margin-bottom: 1rem;">
            Installation Instructions by Device
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
            <!-- iOS Safari -->
            <div style="background: var(--bg-page); border: 1px solid var(--border-light); border-radius: 12px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <ion-icon name="logo-apple" style="font-size: 1.4rem; color: var(--text-heading);"></ion-icon>
                    <strong style="color: var(--text-heading); font-size: 0.95rem;">iOS (iPhone / iPad)</strong>
                </div>
                <ol style="margin: 0; padding-left: 1.2rem; font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                    <li>Open this site in <strong>Safari</strong>.</li>
                    <li>Tap the <strong>Share</strong> button (bottom bar).</li>
                    <li>Scroll down and tap <strong>"Add to Home Screen"</strong>.</li>
                    <li>Tap <strong>Add</strong> in the top right.</li>
                </ol>
            </div>

            <!-- Android & Desktop -->
            <div style="background: var(--bg-page); border: 1px solid var(--border-light); border-radius: 12px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <ion-icon name="logo-android" style="font-size: 1.4rem; color: #10b981;"></ion-icon>
                    <strong style="color: var(--text-heading); font-size: 0.95rem;">Android & Desktop Chrome</strong>
                </div>
                <ol style="margin: 0; padding-left: 1.2rem; font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                    <li>Click the <strong>"Install App Now"</strong> button above.</li>
                    <li>Or click the <strong>Install</strong> icon in the browser address bar.</li>
                    <li>Confirm by clicking <strong>Install</strong> on the prompt.</li>
                </ol>
            </div>
        </div>

        <div style="margin-top: 1.5rem; text-align: right;">
            <button type="button" onclick="localStorage.removeItem('pwa_install_dismissed'); showToast('Popup banner reset for this browser.', 'info');" class="btn btn-outline" style="font-size: 0.8rem; color: var(--text-muted);">
                Reset dismissed popup banner
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
        const statusEl = document.getElementById('appInstallStatusText');
        const btnEl = document.getElementById('btnInstallPWAProfile');
        if (isStandalone) {
            if (statusEl) statusEl.innerHTML = '<span style="color:#10b981; font-weight:600;">✓ App is installed & running in Standalone Mode</span>';
            if (btnEl) {
                btnEl.textContent = 'Installed';
                btnEl.disabled = true;
                btnEl.classList.remove('btn-primary-gradient');
                btnEl.classList.add('btn-outline');
            }
        }
    });
</script>
@endif

@if($tab === 'config')
<div class="card" style="padding: 2rem;">
    <h2 class="section-title">System Configuration</h2>
    <form action="/profile/config" method="POST" style="max-width: 500px; margin-top: 1.5rem;">
        @csrf
        <div class="form-group">
            <label class="form-label">System Default Currency</label>
            <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 0.8rem;">
                This currency will be pre-selected globally when creating budgets, transactions, loans, and master data.
            </p>
            <x-currency-selector name="base_currency" :selected="$company->base_currency ?? 'LKR'" required />
        </div>
        
        <div style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary-gradient">Save Configuration</button>
        </div>
    </form>
</div>
@endif

@endsection
