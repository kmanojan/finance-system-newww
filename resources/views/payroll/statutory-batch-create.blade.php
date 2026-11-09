@extends('payroll.layout')
@section('payroll-content')
<header class="pr-header"><div><a class="pr-back" href="/payroll/statutory">← EPF, ETF & tax</a><h1>Record statutory batch</h1><p>Select pending payroll months and enter the amount actually paid for each.</p></div></header>
<section class="pr-panel"><div class="pr-body"><form method="get" class="pr-filter" data-stat-scope>
<div class="pr-field"><label for="batch-company">Company</label><select id="batch-company" name="company_id">@foreach($companies as $company)<option value="{{ $company->id }}" @selected($companyId == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
<div class="pr-field"><label for="batch-kind">Payment type</label><select id="batch-kind" name="kind">@foreach(\App\Services\PayrollStatutoryService::KINDS as $key=>$label)<option value="{{ $key }}" @selected($kind === $key)>{{ $key === 'apit' ? 'Tax (APIT)' : $label }}</option>@endforeach</select></div>
<div class="pr-field"><label for="batch-year">Payroll year</label><input id="batch-year" type="number" name="year" min="2000" max="2100" placeholder="All years" value="{{ $filters['year'] ?? '' }}"></div><button class="pr-btn">Load pending months</button>
</form></div></section>
@if($rows->isEmpty())
<section class="pr-panel"><div class="pr-body"><h2>No pending {{ strtoupper($kind) }} payments</h2><p>There are no approved payroll balances for these filters. Try another year or payment type.</p><a class="pr-btn" href="/payroll/statutory">View all obligations</a></div></section>
@else
<form method="post" action="/payroll/statutory/batches" enctype="multipart/form-data" data-statutory-batch>
@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="entries_json" value="[]">
<section class="pr-panel"><div class="pr-panel-head"><div><h2>Pending {{ $kind === 'apit' ? 'tax (APIT)' : strtoupper($kind) }} months</h2><p class="pr-hint">Select a month to fill its balance. Adjust the amount for a partial payment.</p></div><span class="pr-muted">{{ $rows->count() }} {{ $rows->count() === 1 ? 'payroll' : 'payrolls' }}</span></div>
<div class="pr-table-wrap pr-review-table" role="region" aria-label="Select statutory months" tabindex="0"><table class="pr-table"><thead><tr><th><label class="pr-review-check"><input type="checkbox" data-stat-all aria-label="Select all pending months"></label></th><th>Payroll month</th><th>Payment due</th><th class="pr-number">Amount due</th><th class="pr-number">Paid</th><th class="pr-number">Pending</th><th class="pr-number">Pay in this batch · LKR</th></tr></thead><tbody>
@foreach($rows as $row)<tr data-stat-row data-run="{{ $row->run_id }}" data-balance="{{ $row->balance_cents }}" data-earliest="{{ \Carbon\Carbon::parse($row->month)->startOfMonth()->toDateString() }}"><td><label class="pr-review-check"><input type="checkbox" data-stat-select aria-label="Select {{ \Carbon\Carbon::parse($row->month)->format('F Y') }} payroll {{ $row->run_id }}"></label></td><td><a href="/payroll/{{ $row->run_id }}/statutory/{{ $kind }}">{{ \Carbon\Carbon::parse($row->month)->format('F Y') }}</a><small>Payroll #{{ $row->run_id }}</small></td><td>{{ $row->due_on }}</td><td class="pr-number">{{ number_format($row->due_cents/100,2) }}</td><td class="pr-number">{{ number_format($row->paid_cents/100,2) }}</td><td class="pr-number">{{ number_format($row->balance_cents/100,2) }}</td><td><input class="pr-statutory-amount" type="text" inputmode="decimal" data-stat-amount aria-label="Payment for {{ \Carbon\Carbon::parse($row->month)->format('F Y') }} payroll {{ $row->run_id }}" placeholder="0.00" disabled></td></tr>@endforeach
</tbody></table></div></section>
<section class="pr-panel"><div class="pr-panel-head"><h2>Payment details</h2><strong data-stat-total aria-live="polite">0 months · LKR 0.00</strong></div><div class="pr-body"><div class="pr-form-grid">
@include('payroll.payment-fields', ['hideAmount'=>true,'paymentCompany'=>$companyId,'paymentCurrency'=>'LKR','paymentToday'=>$today])
<div class="pr-full"><p class="pr-hint">One payment type, company and bank per batch. Record payments already made to the authority; this does not transfer money or submit documents.</p><p class="pr-hint" data-stat-message role="status" aria-live="polite" tabindex="-1">Select at least one pending month.</p><button type="submit" class="pr-btn pr-btn-primary" data-stat-save data-no-spinner disabled>Record payment batch</button></div>
</div></div></section></form>
@endif
<script src="{{ asset('payroll-statutory-batch.js') }}?v={{ hash_file('sha256',public_path('payroll-statutory-batch.js')) }}" defer></script>
@endsection
