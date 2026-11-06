@extends('reports.layout')
@section('title','Balance sheet')
@section('report-content')
<header class="page-header"><div><h1>Balance sheet</h1><p class="subtitle">Posted general ledger balances as of {{ $asOfDate }} · {{ $baseCurrency }}</p></div></header>
<x-date-range-picker :from="$from" :to="$to" />
@if($unpostedCount > 0)<div class="ds-notice error">{{ $unpostedCount }} historical records have no journal posting. Reconcile and migrate those records before relying on this statement.</div>@endif
<div class="ds-form-grid" style="margin-top:20px">
@foreach(['Assets'=>$assets,'Liabilities'=>$liabilities,'Equity'=>$equity] as $title=>$rows)
<section class="ds-panel"><h2>{{ $title }}</h2><table class="data-table"><thead><tr><th>Account</th><th class="num">Balance</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->code }} · {{ $row->name }}</td><td class="num">{{ number_format($title==='Assets'?$row->balance:-$row->balance,2) }}</td></tr>@empty<tr><td colspan="2">No posted balances.</td></tr>@endforelse
@if($title==='Equity')<tr><td>Retained earnings</td><td class="num">{{ number_format($retainedEarnings,2) }}</td></tr>@endif
</tbody><tfoot><tr><th>Total {{ strtolower($title) }}</th><th class="num">{{ number_format(match($title){'Assets'=>$totalAssets,'Liabilities'=>$totalLiabilities,default=>$totalEquity},2) }}</th></tr></tfoot></table></section>
@endforeach
<section class="ds-panel"><h2>Balance check</h2><div class="work-row"><span>Assets</span><strong>{{ number_format($totalAssets,2) }}</strong></div><div class="work-row"><span>Liabilities + equity</span><strong>{{ number_format($totalLiabilities+$totalEquity,2) }}</strong></div><div class="work-row"><span>Difference</span><strong>{{ number_format($unreconciledDifference,2) }}</strong></div></section>
</div>
@endsection
