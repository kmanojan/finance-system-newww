@extends('layouts.app')
@section('title', 'User Profile & Settings')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Settings</h2>
    <nav class="nav-links">
        <a href="/profile?tab=general" class="nav-link {{ $tab === 'general' ? 'active' : '' }}">General Info</a>
        <a href="/profile?tab=config" class="nav-link {{ $tab === 'config' ? 'active' : '' }}">Configuration</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 2rem;">
    <div class="header-titles">
        <h1>Profile & Settings</h1>
        <p class="subtitle">Manage your account and system-wide configurations.</p>
    </div>
</header>

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
    {{ session('success') }}
</div>
@endif

@if($tab === 'general')
<div class="card" style="padding: 2rem;">
    <h2 class="section-title">General Information</h2>
    <p class="text-muted">User profile settings will appear here in the future.</p>
    
    <div class="user-avatar" style="width: 100px; height: 100px; font-size: 2.5rem; margin-top: 2rem;">
        <span>Admin</span>
    </div>
    <div style="margin-top: 1rem;">
        <strong>Role:</strong> Administrator
    </div>
</div>
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
