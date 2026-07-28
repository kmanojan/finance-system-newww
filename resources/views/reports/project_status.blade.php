@extends('reports.layout')

@section('report-title', 'Project Status Dashboard')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Project Status Dashboard</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Project delivery status from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Project Name</th>
                <th>Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Budget Limit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['projects'] as $proj)
                <tr>
                    <td data-label="Name" style="color: var(--text-heading); font-weight: 500;">{{ $proj->name }}</td>
                    <td data-label="Status">
                        <span class="badge" style="background: var(--primary-light); color: var(--primary);">
                            {{ ucfirst($proj->status ?? 'active') }}
                        </span>
                    </td>
                    <td data-label="Start">{{ $proj->start_date ?? '-' }}</td>
                    <td data-label="End">{{ $proj->end_date ?? '-' }}</td>
                    <td data-label="Budget" style="font-weight: 600;">{{ $proj->currency ?? 'LKR' }} {{ number_format($proj->budget_limit ?? 0, 2) }}</td>
                </tr>
            @endforeach
            @if(count($data['projects']) === 0)
                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No projects found.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
