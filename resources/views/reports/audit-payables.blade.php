@extends('reports.layout')
@section('report-content')
<link rel="stylesheet" href="{{ asset('audit-payables.css') }}?v={{ filemtime(public_path('audit-payables.css')) }}">
<div class="audit-report">
<header class="audit-header"><div><h1>Auditor payables</h1><p>Loans, salary balances and statutory remittances · All companies · As at {{ $report['asOf'] }}</p></div>
<div class="audit-actions"><a class="btn btn-outline" href="{{ url('/reports/export/audit-payables/pdf').'?'.http_build_query(['as_of'=>$report['asOf']]) }}">Download PDF</a><a class="btn btn-outline" href="{{ url('/reports/export/audit-payables/xlsx').'?'.http_build_query(['as_of'=>$report['asOf']]) }}">Export Excel</a></div></header>
<form method="get" class="audit-filter"><div><label for="audit-as-of">Reporting date</label><input type="date" name="as_of" id="audit-as-of" required max="{{ \App\Services\PayrollStatutoryService::today() }}" value="{{ $report['asOf'] }}"></div><button class="btn btn-primary">Update report</button><span>Generated {{ $report['generatedAt'] }} · Confidential payroll information</span></form>
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<nav class="audit-jump" aria-label="Report sections"><a href="#audit-summary">Summary</a><a href="#audit-loans">Loans & settlements</a><a href="#audit-salaries">Salary balances</a><a href="#audit-statutory">EPF, ETF & tax</a><a href="#audit-review">Drafts & missing months</a><a href="#audit-exceptions">Review points</a></nav>
@include('reports.partials.audit-payables-schedules', ['pdf'=>false])
</div>
@endsection
