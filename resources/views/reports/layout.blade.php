@extends('layouts.app')
@section('title', 'Reports')

@php
    $month = $month ?? date('m');
    $year = $year ?? date('Y');
@endphp

@section('secondary-sidebar')
<x-secondary-sidebar title="Reports">
    
    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); margin-bottom: 0.5rem; padding-left: 0.75rem; font-weight: 600;">Finance & Accounting</h3>
        <nav class="nav-links">
            @if(auth()->user()?->isAdmin() || in_array(strtolower(auth()->user()?->role ?? ''), ['manager', 'accountant']))
            <a href="{{ route('reports.audit_payables') }}" class="nav-link {{ Route::currentRouteName() === 'reports.audit_payables' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="clipboard-outline"></ion-icon><span class="section-navigation-label">Auditor Payables</span></a>
            @endif
            <a href="{{ route('reports.pnl', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.pnl' ? 'active' : '' }}">
                <ion-icon name="bar-chart-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Profit & Loss</span></a>
            <a href="{{ route('reports.balance_sheet') }}" class="nav-link {{ Route::currentRouteName() === 'reports.balance_sheet' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="document-text-outline"></ion-icon><span class="section-navigation-label">Balance Sheet</span></a>
            <a href="{{ route('reports.cash_flow') }}" class="nav-link {{ Route::currentRouteName() === 'reports.cash_flow' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="swap-horizontal-outline"></ion-icon><span class="section-navigation-label">Cash Flow</span></a>
            <a href="{{ route('reports.expenses', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.expenses' ? 'active' : '' }}">
                <ion-icon name="pie-chart-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Expenses</span></a>
            <a href="{{ route('reports.expense_trend') }}" class="nav-link {{ Route::currentRouteName() === 'reports.expense_trend' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="trending-down-outline"></ion-icon><span class="section-navigation-label">Expense Trend</span></a>
            <a href="{{ route('reports.commissions', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.commissions' ? 'active' : '' }}">
                <ion-icon name="cash-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Commissions</span></a>
            <a href="{{ route('reports.cost_allocations') }}" class="nav-link {{ Route::currentRouteName() === 'reports.cost_allocations' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="calculator-outline"></ion-icon><span class="section-navigation-label">Cost Allocations</span></a>
            <a href="{{ route('reports.bank_reconciliation') }}" class="nav-link {{ Route::currentRouteName() === 'reports.bank_reconciliation' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="card-outline"></ion-icon><span class="section-navigation-label">Bank Reconciliation</span></a>
        </nav>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); margin-bottom: 0.5rem; padding-left: 0.75rem; font-weight: 600;">Projects & Delivery</h3>
        <nav class="nav-links">
            <a href="{{ route('reports.project_status', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.project_status' ? 'active' : '' }}">
                <ion-icon name="albums-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Project Status</span></a>
            <a href="{{ route('reports.project_profitability') }}" class="nav-link {{ Route::currentRouteName() === 'reports.project_profitability' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="trending-up-outline"></ion-icon><span class="section-navigation-label">Project Profitability</span></a>
        </nav>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); margin-bottom: 0.5rem; padding-left: 0.75rem; font-weight: 600;">Client & Account</h3>
        <nav class="nav-links">
            <a href="{{ route('reports.client_health', ['month' => $month ?? date('m'), 'year' => $year ?? date('Y')]) }}" class="nav-link {{ Route::currentRouteName() === 'reports.client_health' ? 'active' : '' }}">
                <ion-icon name="heart-half-outline" aria-hidden="true"></ion-icon><span class="section-navigation-label">Client Health</span></a>
            <a href="{{ route('reports.ar_aging') }}" class="nav-link {{ Route::currentRouteName() === 'reports.ar_aging' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="time-outline"></ion-icon><span class="section-navigation-label">AR Aging Report</span></a>
            <a href="{{ route('reports.client_statement') }}" class="nav-link {{ Route::currentRouteName() === 'reports.client_statement' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="receipt-outline"></ion-icon><span class="section-navigation-label">Client Statement</span></a>
            <a href="{{ route('reports.party_ledger') }}" class="nav-link {{ Route::currentRouteName() === 'reports.party_ledger' ? 'active' : '' }}"><ion-icon aria-hidden="true" name="people-circle-outline"></ion-icon><span class="section-navigation-label">Party Payables & Full Ledger</span></a>
        </nav>
    </div>


</x-secondary-sidebar>
@endsection

@section('content')
@unless(request()->is('reports/export/*', 'reports/audit-payables'))
<div class="report-export-actions"><a class="btn btn-outline" href="{{ url('/reports/export/'.request()->segment(2).'/xlsx').'?'.http_build_query(request()->query()) }}">Export Excel</a><a class="btn btn-outline" href="{{ url('/reports/export/'.request()->segment(2).'/pdf').'?'.http_build_query(request()->query()) }}">Export PDF</a></div>
@endunless
@yield('report-content')
@endsection

