@extends('layouts.app')
@section('title', 'Reports')

@php
    $month = $month ?? date('m');
    $year = $year ?? date('Y');
@endphp

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary" style="overflow-y: auto;">
    <h2 class="sidebar-title">Reports</h2>
    
    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); margin-bottom: 0.5rem; padding-left: 0.75rem; font-weight: 600;">Finance & Accounting</h3>
        <nav class="nav-links">
            <a href="{{ route('reports.pnl', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.pnl' ? 'active' : '' }}">
                <ion-icon name="bar-chart-outline"></ion-icon> Profit & Loss
            </a>
            <a href="{{ route('reports.balance_sheet') }}" class="nav-link {{ Route::currentRouteName() === 'reports.balance_sheet' ? 'active' : '' }}">
                <ion-icon name="document-text-outline"></ion-icon> Balance Sheet
            </a>
            <a href="{{ route('reports.cash_flow') }}" class="nav-link {{ Route::currentRouteName() === 'reports.cash_flow' ? 'active' : '' }}">
                <ion-icon name="swap-horizontal-outline"></ion-icon> Cash Flow
            </a>
            <a href="{{ route('reports.expenses', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.expenses' ? 'active' : '' }}">
                <ion-icon name="pie-chart-outline"></ion-icon> Expenses
            </a>
            <a href="{{ route('reports.expense_trend') }}" class="nav-link {{ Route::currentRouteName() === 'reports.expense_trend' ? 'active' : '' }}">
                <ion-icon name="trending-down-outline"></ion-icon> Expense Trend
            </a>
            <a href="{{ route('reports.commissions', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.commissions' ? 'active' : '' }}">
                <ion-icon name="cash-outline"></ion-icon> Commissions
            </a>
            <a href="{{ route('reports.cost_allocations') }}" class="nav-link {{ Route::currentRouteName() === 'reports.cost_allocations' ? 'active' : '' }}">
                <ion-icon name="calculator-outline"></ion-icon> Cost Allocations
            </a>
            <a href="{{ route('reports.bank_reconciliation') }}" class="nav-link {{ Route::currentRouteName() === 'reports.bank_reconciliation' ? 'active' : '' }}">
                <ion-icon name="card-outline"></ion-icon> Bank Reconciliation
            </a>
        </nav>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); margin-bottom: 0.5rem; padding-left: 0.75rem; font-weight: 600;">Projects & Delivery</h3>
        <nav class="nav-links">
            <a href="{{ route('reports.project_status', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.project_status' ? 'active' : '' }}">
                <ion-icon name="albums-outline"></ion-icon> Project Status
            </a>
            <a href="{{ route('reports.project_profitability') }}" class="nav-link {{ Route::currentRouteName() === 'reports.project_profitability' ? 'active' : '' }}">
                <ion-icon name="trending-up-outline"></ion-icon> Project Profitability
            </a>
        </nav>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); margin-bottom: 0.5rem; padding-left: 0.75rem; font-weight: 600;">Client & Account</h3>
        <nav class="nav-links">
            <a href="{{ route('reports.client_health', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.client_health' ? 'active' : '' }}">
                <ion-icon name="heart-half-outline"></ion-icon> Client Health
            </a>
            <a href="{{ route('reports.ar_aging') }}" class="nav-link {{ Route::currentRouteName() === 'reports.ar_aging' ? 'active' : '' }}">
                <ion-icon name="time-outline"></ion-icon> AR Aging Report
            </a>
            <a href="{{ route('reports.client_statement') }}" class="nav-link {{ Route::currentRouteName() === 'reports.client_statement' ? 'active' : '' }}">
                <ion-icon name="receipt-outline"></ion-icon> Client Statement
            </a>
            <a href="{{ route('reports.party_ledger') }}" class="nav-link {{ Route::currentRouteName() === 'reports.party_ledger' ? 'active' : '' }}">
                <ion-icon name="people-circle-outline"></ion-icon> Party Payables & Full Ledger
            </a>
        </nav>
    </div>


</aside>
@endsection

@section('content')
@yield('report-content')
@endsection

