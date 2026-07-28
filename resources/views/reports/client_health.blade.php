@extends('reports.layout')

@section('report-title', 'Client Health Score')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Client Health Score</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Client account health overview from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Client Name</th>
                <th>Status</th>
                <th>Client Type(s)</th>
                <th>Contact</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['clients'] as $client)
                <tr>
                    <td data-label="Client" style="color: var(--text-heading); font-weight: 500;">{{ $client->name }}</td>
                    <td data-label="Health">
                        <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                            Active
                        </span>
                    </td>
                    <td data-label="Types">{{ $client->types ?? 'client' }}</td>
                    <td data-label="Contact">{{ $client->email ?? '-' }}</td>
                </tr>
            @endforeach
            @if(count($data['clients']) === 0)
                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">No clients recorded.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
