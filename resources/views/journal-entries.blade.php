@extends('layouts.app')
@section('title', 'Double-Entry Journal Entries')
@section('meta_description', 'Double-entry bookkeeping journal entries, debit and credit ledger balancing, and account adjustments.')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Ledger</h2>
    <nav class="nav-links">
        <a href="/transactions" class="nav-link {{ request()->is('transactions') ? 'active' : '' }}">All Transactions</a>
        <a href="/journal-entries" class="nav-link {{ request()->is('journal-entries') ? 'active' : '' }}">Journal Entries</a>
        <a href="/budgets" class="nav-link {{ request()->is('budgets') ? 'active' : '' }}">Budgets</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Journal Entries</h1>
        <p class="subtitle">View and manage double-entry journal entries.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="alert('Feature coming soon')">
        <ion-icon name="add-outline"></ion-icon> New Entry
    </button>
</header>

<div class="toolbar">
    <div class="toolbar-left">
    </div>
    <div class="toolbar-right">
        <div class="search-input">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" placeholder="Search entries">
        </div>
    </div>
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Description</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
            <tr>
                <td data-label="ID"><span class="font-medium">#{{ $entry->id }}</span></td>
                <td data-label="Date"><span class="text-muted">{{ $entry->entry_date }}</span></td>
                <td data-label="Description"><span>{{ $entry->description }}</span></td>
                <td data-label="Reference">
                    @if($entry->reference_type)
                        <span class="badge" style="background:#f1f5f9;color:#475569;">{{ $entry->reference_type }} #{{ $entry->reference_id }}</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
            @if($entries->isEmpty())
            <tr><td colspan="4" class="text-center text-muted py-4">No journal entries found.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
