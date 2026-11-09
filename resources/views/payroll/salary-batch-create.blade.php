@extends('payroll.layout')
@section('payroll-content')
<header class="pr-header"><div><a class="pr-back" href="/payroll/{{ $run->id }}">← {{ \Carbon\Carbon::parse($run->month)->format('F Y') }} payroll</a><h1>Record salary batch</h1><p>{{ $company->name }} · {{ $run->currency }} · choose employees and the amount actually released to each.</p></div></header>
@if($batchEmployees->isEmpty())
<section class="pr-panel"><div class="pr-body"><h2>All salaries are paid</h2><p class="pr-hint">No employees have a remaining salary balance for this payroll.</p><a class="pr-btn" href="/payroll/{{ $run->id }}#salary-batches">View salary batches</a></div></section>
@else
<form method="post" action="/payroll/{{ $run->id }}/salary-batches" enctype="multipart/form-data" data-salary-batch>@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="entries_json" value="{{ old('entries_json', '[]') }}" data-batch-entries>
<section class="pr-panel"><div class="pr-panel-head"><div><h2>Pending employee payments</h2><p class="pr-hint">{{ $batchEmployees->count() }} {{ \Illuminate\Support\Str::plural('employee', $batchEmployees->count()) }} with unpaid salary · {{ $run->currency }} {{ number_format($batchEmployees->sum('balance')/100,2) }} remaining. Fully paid employees are hidden. Leave an amount blank to skip an employee.</p></div><span class="pr-state" data-batch-count>0 employees selected</span></div>
<div class="pr-body pr-filter"><div class="pr-field"><label for="batch-search">Find employee</label><input id="batch-search" type="search" placeholder="Search name or employee code" value="{{ $initialBatchSearch }}" data-batch-search></div><button type="button" class="pr-btn" data-batch-fill>Fill balances on this page</button><button type="button" class="pr-btn" data-batch-clear>Clear selected amounts</button></div>
<div class="pr-table-wrap pr-batch-table" role="region" aria-label="Employee salary amounts" tabindex="0"><table class="pr-table"><thead><tr><th>Employee</th><th class="pr-number">Net salary</th><th class="pr-number">Previously paid</th><th class="pr-number">Remaining salary</th><th class="pr-number">Pay in this batch</th></tr></thead><tbody data-batch-rows></tbody></table></div>
<div class="pr-footer"><span class="pr-hint" data-batch-range></span><button type="button" class="pr-btn" data-batch-prev>Previous</button><button type="button" class="pr-btn" data-batch-next>Next</button></div>
<noscript><div class="pr-body">Enable JavaScript to select employee amounts. You can also record an individual salary payment from the payroll page.</div></noscript>
</section>
<section class="pr-panel"><div class="pr-panel-head"><h2>Release details</h2><strong class="pr-number" data-batch-total>{{ $run->currency }} 0.00</strong></div><div class="pr-body pr-form-grid">
@include('payroll.payment-fields', ['paymentCompany' => $run->company_id, 'paymentCurrency' => $run->currency, 'hideAmount' => true, 'paymentEarliest' => \Carbon\Carbon::parse($run->month)->startOfMonth()->toDateString()])
</div><div class="pr-footer"><button type="submit" class="pr-btn pr-btn-primary" data-batch-save data-no-spinner disabled>Record salary batch</button><a class="pr-btn" href="/payroll/{{ $run->id }}">Cancel</a><p class="pr-hint" role="status" aria-live="polite" tabindex="-1" data-batch-message>Enter the payments made in this release. This records a completed payment; it does not send money.</p></div></section>
</form>
<script id="salary-batch-data" type="application/json">@json($batchEmployees)</script>
<script src="{{ asset('payroll-batch.js') }}?v={{ hash_file('sha256',public_path('payroll-batch.js')) }}" defer></script>
@endif
@endsection
