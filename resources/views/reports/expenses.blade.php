@extends('reports.layout')

@section('report-title', 'Expense by Category')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Expense by Category Report</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Category breakdown from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div style="margin-bottom: 2rem; background: var(--bg-card); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between;">
    <div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Total Period Expenses</p>
        <h2 style="color: var(--danger); font-size: 2rem; font-weight: 600;">LKR {{ number_format($data['totalExpense'], 2) }}</h2>
    </div>
    <div style="opacity: 0.1;">
        <ion-icon name="pie-chart" style="font-size: 4rem; color: var(--text-heading);"></ion-icon>
    </div>
</div>

<!-- Expenses Table -->
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th style="text-align: right;">Total Spent</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['expenses'] as $exp)
                <tr>
                    <td data-label="Category" style="color: var(--text-heading); font-weight: 500;">{{ $exp->category }}</td>
                    <td data-label="Total" style="text-align: right; color: var(--danger); font-weight: 600;">{{ number_format($exp->total, 2) }}</td>
                </tr>
            @endforeach
            @if($data['expenses']->isEmpty())
                <tr>
                    <td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;">No expenses found for this period.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
