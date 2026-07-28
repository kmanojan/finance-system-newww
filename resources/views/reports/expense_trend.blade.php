@extends('reports.layout')
@section('title', 'Expense Trend Analysis')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Expense Category Trend Analysis</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Category-wise spend trends ending {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Expense Category</th>
                @foreach($months as $m)
                    <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">{{ $m['label'] }}</th>
                @endforeach
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">6-Month Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($trendData as $row)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; font-weight:700; color:var(--text-heading);">{{ $row->category_name }}</td>
                @foreach($months as $m)
                    <td style="padding:0.85rem 1rem; text-align:right; font-size:0.85rem;">
                        LKR {{ number_format($row->totals[$m['key']] ?? 0, 2) }}
                    </td>
                @endforeach
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:800; color:var(--primary);">
                    LKR {{ number_format($row->grand_total, 2) }}
                </td>
            </tr>
            @endforeach
            @if(empty($trendData))
            <tr><td colspan="{{ count($months) + 2 }}" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">No expense categories recorded.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
