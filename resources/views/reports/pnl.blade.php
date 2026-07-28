@extends('reports.layout')

@section('report-title', 'Profit & Loss Statement')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Profit & Loss Statement</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Financial performance from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<!-- Summary Cards for P&L -->
<div class="grid-cards" style="margin-bottom: 2rem;">
    <div class="summary-card">
        <div class="summary-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
            <ion-icon name="arrow-up-circle-outline"></ion-icon>
        </div>
        <div class="summary-info">
            <h4>Total Income</h4>
            <div class="val text-success">{{ number_format($data['totalIncome'], 2) }}</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
            <ion-icon name="arrow-down-circle-outline"></ion-icon>
        </div>
        <div class="summary-info">
            <h4>Total Expenses</h4>
            <div class="val text-danger">{{ number_format($data['totalExpense'], 2) }}</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
            <ion-icon name="wallet-outline"></ion-icon>
        </div>
        <div class="summary-info">
            <h4>Net Profit / Loss</h4>
            <div class="val" style="color: {{ $data['netProfit'] >= 0 ? 'var(--primary)' : 'var(--danger)' }};">
                {{ number_format($data['netProfit'], 2) }}
            </div>
        </div>
    </div>
</div>

<!-- P&L Table -->
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70%">Category</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background: var(--bg-sidebar-secondary);"><td colspan="2" style="font-weight: 600; color: var(--text-heading);">Income</td></tr>
            @foreach($data['income'] as $item)
                <tr>
                    <td data-label="Category">{{ $item->category }}</td>
                    <td data-label="Amount" style="text-align: right; color: var(--success); font-weight: 500;">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
            @if($data['income']->isEmpty())
                <tr><td colspan="2" style="text-align: center; color: var(--text-muted);">No income records for this period.</td></tr>
            @endif
            <tr style="background: var(--bg-sidebar-secondary);"><td colspan="2" style="font-weight: 600; color: var(--text-heading);">Expenses</td></tr>
            @foreach($data['expense'] as $item)
                <tr>
                    <td data-label="Category">{{ $item->category }}</td>
                    <td data-label="Amount" style="text-align: right; color: var(--danger); font-weight: 500;">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
            @if($data['expense']->isEmpty())
                <tr><td colspan="2" style="text-align: center; color: var(--text-muted);">No expense records for this period.</td></tr>
            @endif
        </tbody>
        <tfoot>
            <tr style="background: var(--bg-table-header);">
                <td style="color: var(--text-heading); font-weight: 700;">Net Profit/Loss</td>
                <td style="text-align: right; font-weight: 700; color: {{ $data['netProfit'] >= 0 ? 'var(--primary)' : 'var(--danger)' }};">{{ number_format($data['netProfit'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
