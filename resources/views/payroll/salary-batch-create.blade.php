@extends('payroll.layout')
@section('payroll-content')
<link rel="stylesheet" href="{{ asset('payroll-salary-batch.css') }}?v={{ hash_file('sha256',public_path('payroll-salary-batch.css')) }}">
<div class="pr-salary-batch-page">
<header class="pr-header"><div><a class="pr-back" href="/payroll/{{ $run->id }}">← {{ \Carbon\Carbon::parse($run->month)->format('F Y') }} payroll</a><h1>Record salary batch</h1><p>{{ $company->name }} · {{ \Carbon\Carbon::parse($run->month)->format('F Y') }} · {{ $run->currency }}</p></div><a class="pr-btn" href="/payroll/{{ $run->id }}#salary-batches">View previous batches</a></header>
<section class="pr-panel pr-batch-overview" aria-label="Payroll salary totals">
<dl><dt>Payroll net amount · {{ $run->currency }}</dt><dd>{{ number_format($salaryPayment['due']/100,2) }}</dd><small>{{ $items->count() }} {{ \Illuminate\Support\Str::plural('employee', $items->count()) }} in this payroll</small></dl>
<dl><dt>Already paid · {{ $run->currency }}</dt><dd>{{ number_format($salaryPayment['paid']/100,2) }}</dd><small>Recorded salary payments</small></dl>
<dl><dt>Pending salary · {{ $run->currency }}</dt><dd>{{ number_format($salaryPayment['balance']/100,2) }}</dd><small>{{ $batchEmployees->count() }} {{ \Illuminate\Support\Str::plural('employee', $batchEmployees->count()) }} awaiting payment</small></dl>
</section>
@if($batchEmployees->isEmpty())
<section class="pr-panel"><div class="pr-empty"><h2>All salaries are paid</h2><p>No employees have a remaining salary balance for this payroll.</p><a class="pr-btn" href="/payroll/{{ $run->id }}#salary-batches">View salary batches</a></div></section>
@else
<form method="post" action="/payroll/{{ $run->id }}/salary-batches" enctype="multipart/form-data" data-salary-batch>@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="entries_json" value="{{ old('entries_json', '[]') }}" data-batch-entries>
<section class="pr-panel">
<div class="pr-batch-selection" aria-label="Batch preview">
<div><span class="pr-batch-label">Selected payment · {{ $run->currency }}</span><strong data-batch-selected-total>0.00</strong><span class="pr-hint" data-batch-count>0 employees selected</span></div>
<div><span class="pr-batch-label">Pending after this batch · {{ $run->currency }}</span><strong data-batch-after>{{ number_format($salaryPayment['balance']/100,2) }}</strong><span class="pr-hint">Preview until the batch is recorded</span></div>
</div>
<div class="pr-panel-head"><div><h2>Choose employees</h2><p class="pr-hint">Select an employee to fill their balance, or enter a partial amount. Fully paid employees are hidden.</p></div></div>
<div class="pr-body pr-batch-toolbar"><div class="pr-field"><label for="batch-search">Find employee</label><input id="batch-search" type="search" placeholder="Search name or employee code" value="{{ $initialBatchSearch }}" data-batch-search></div><div class="pr-actions"><button type="button" class="pr-btn" data-batch-fill>Select this page</button><button type="button" class="pr-btn" data-batch-clear>Clear selection</button></div></div>
<div class="pr-table-wrap pr-batch-table" role="region" aria-label="Employee salary amounts" tabindex="0"><table class="pr-table"><thead><tr><th scope="col">Employee</th><th scope="col" class="pr-number">Net salary</th><th scope="col" class="pr-number">Already paid</th><th scope="col" class="pr-number">Pending</th><th scope="col" class="pr-number">Pay in this batch</th></tr></thead><tbody data-batch-rows></tbody></table></div>
<div class="pr-footer pr-batch-pagination"><span class="pr-hint" data-batch-range></span><div class="pr-actions"><button type="button" class="pr-btn" data-batch-prev>Previous</button><button type="button" class="pr-btn" data-batch-next>Next</button></div></div>
<noscript><div class="pr-body">Enable JavaScript to select employee amounts. You can also record an individual salary payment from the payroll page.</div></noscript>
</section>
<section class="pr-panel pr-batch-release"><div class="pr-panel-head"><div><h2>Payment details</h2><p class="pr-hint">Use the date and account of the actual salary release.</p></div></div><div class="pr-body pr-form-grid">
@include('payroll.payment-fields', ['paymentCompany' => $run->company_id, 'paymentCurrency' => $run->currency, 'hideAmount' => true, 'paymentEarliest' => \Carbon\Carbon::parse($run->month)->startOfMonth()->toDateString()])
</div><div class="pr-batch-confirm"><div><span class="pr-batch-label">Batch total</span><strong data-batch-total>{{ $run->currency }} 0.00</strong></div><div class="pr-actions"><a class="pr-btn" href="/payroll/{{ $run->id }}">Cancel</a><button type="submit" class="pr-btn pr-btn-primary" data-batch-save data-no-spinner disabled>Record salary batch</button></div><p class="pr-hint" role="status" aria-live="polite" tabindex="-1" data-batch-message>Enter the payments made in this release. This records a completed payment; it does not send money.</p></div></section>
</form>
<script id="salary-batch-data" type="application/json">@json($batchEmployees)</script>
<script src="{{ asset('payroll-batch.js') }}?v={{ hash_file('sha256',public_path('payroll-batch.js')) }}" defer></script>
@endif
</div>
@endsection
