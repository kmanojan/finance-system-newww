@extends('payroll.layout')
@section('payroll-content')
<header class="pr-header"><div><a class="pr-back" href="/payroll">← Monthly payroll</a><h1>Create payroll</h1><p>Select employees, review income and expenses, and enter this month’s variable amounts.</p></div></header>
<form method="post" action="/payroll" data-payroll-create>@csrf
<section class="pr-panel"><div class="pr-panel-head"><h2>Payroll period</h2><span class="pr-muted">LKR payroll</span></div><div class="pr-body pr-form-grid">
<div class="pr-field"><label for="month">Month</label><input id="month" name="month" type="month" value="{{ old('month', date('Y-m')) }}" required></div>
<div class="pr-field"><label for="company_id">Company</label><select id="company_id" name="company_id" required data-company><option value="">Select company</option>@foreach($companies as $co)<option value="{{ $co->id }}" @selected(old('company_id', $companies->count() === 1 ? $co->id : '') == $co->id)>{{ $co->name }}</option>@endforeach</select></div>
<div class="pr-field"><label for="department_id">Payroll expense department</label><select id="department_id" name="department_id" required data-department><option value="">Select department</option>@foreach($departments as $d)<option value="{{ $d->id }}" data-company="{{ $d->company_id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
<input type="hidden" name="currency" value="LKR">
<div class="pr-field"><label for="salary_due_date">Salary payment due</label><input id="salary_due_date" name="salary_due_date" type="date" value="{{ old('salary_due_date', now()->endOfMonth()->toDateString()) }}" required></div>
<div class="pr-field"><label for="statutory_due_date">EPF / ETF payment due</label><input id="statutory_due_date" name="statutory_due_date" type="date" value="{{ old('statutory_due_date', now()->addMonthNoOverflow()->endOfMonth()->toDateString()) }}" required><span class="pr-hint">Confirm the last working day, including public holidays.</span></div>
<div class="pr-field"><label for="tax_due_date">APIT payment due</label><input id="tax_due_date" name="tax_due_date" type="date" value="{{ old('tax_due_date', now()->addMonthNoOverflow()->day(15)->toDateString()) }}" required><span class="pr-hint">Confirm the applicable remittance period.</span></div>
<div class="pr-field pr-full"><label for="notes">Payroll notes <span class="pr-muted">(optional)</span></label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
</div></section>
<section class="pr-panel"><div class="pr-panel-head"><h2>Employees & salary components</h2><span class="pr-muted" data-selection-count>0 selected</span></div>
<div class="pr-statline pr-create-totals" aria-label="Selected employee totals"><div><dl><dt>Income · LKR</dt><dd data-preview-total="gross">0.00</dd></dl></div><div><dl><dt>Expenses · deductions</dt><dd data-preview-total="deductions">0.00</dd></dl></div><div><dl><dt>Net salaries</dt><dd data-preview-total="net">0.00</dd></dl></div><div><dl><dt>Total employer cost</dt><dd data-preview-total="cost">0.00</dd></dl></div></div>
<div class="pr-body pr-grid-toolbar"><div class="pr-field"><label for="employee-search">Find employee</label><input type="search" id="employee-search" data-employee-search value="{{ request('search') }}" maxlength="150" placeholder="Name or employee code"></div><div class="pr-actions"><button type="button" class="pr-btn" data-select-page>Select this page</button><button type="button" class="pr-btn" data-select-employees>Select all matching active employees</button><button type="button" class="pr-btn" data-clear-employees>Clear selection</button></div></div>
<div class="pr-body pr-preview-status" data-preview-status role="status" aria-live="polite">Select employees to calculate payroll totals.</div>
<div class="pr-grid-scroll" tabindex="0" role="region" aria-label="Employee payroll table. Scroll horizontally for salary components." data-payroll-scroll>
<table class="pr-payroll-grid"><caption class="pr-visually-hidden">Employee salary components in LKR. Income, expenses, employer contributions and payroll totals.</caption>
<thead><tr class="pr-grid-groups"><th rowspan="2" scope="col" class="pr-grid-employee">Employee</th><th scope="colgroup" colspan="{{ ($components['earning'] ?? collect())->count() + 2 }}">Income</th><th scope="colgroup" colspan="{{ ($components['deduction'] ?? collect())->count() + 2 }}">Expenses · deductions</th><th scope="colgroup" colspan="{{ ($components['employer_contribution'] ?? collect())->count() + 1 }}">Employer contributions</th><th scope="colgroup" colspan="2">Payroll totals</th></tr>
<tr class="pr-grid-columns"><th scope="col">Basic salary</th>
@foreach(['earning' => 'Total income', 'deduction' => 'Total expenses', 'employer_contribution' => 'Employer contributions total'] as $type => $label)
@foreach($components[$type] ?? [] as $component)<th scope="col" data-component-column="{{ $component->id }}">{{ $component->name }}<small>{{ $component->payroll_input_mode === 'monthly_amount' ? 'Monthly amount' : 'Salary profile' }}</small></th>@endforeach
@if($type === 'deduction')<th scope="col">Salary advance<small>Scheduled recovery</small></th>@endif
<th scope="col" class="pr-grid-total">{{ $label }}</th>
@endforeach
<th scope="col" class="pr-grid-total">Net salary</th><th scope="col" class="pr-grid-total">Employer cost</th></tr></thead>
<tbody data-payroll-rows>@include('payroll.create-rows')</tbody>
</table></div>
<div class="pr-grid-pagination"><span data-page-status role="status">{{ $employees->firstItem() ?? 0 }}–{{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }} employees</span><div class="pr-actions"><label for="payroll-page-size">Rows per page</label><select id="payroll-page-size" data-page-size><option value="25">25</option><option value="50">50</option><option value="100">100</option></select><button class="pr-btn" type="button" data-previous-page disabled>Previous</button><span data-page-number>Page {{ $employees->currentPage() }} of {{ $employees->lastPage() }}</span><button class="pr-btn" type="button" data-next-page @disabled(!$employees->hasMorePages())>Next</button></div></div>
<p class="pr-body pr-hint">All amounts are in LKR. Selections and entered amounts stay with you across pages and searches until you leave this screen. Scheduled advances are proposed from the chosen month, capped at available net pay; remaining amounts carry forward. A dash means the component is not assigned. Select up to 1,000 employees.</p>
@php($selectionState = ['ids' => old('employee_ids', []), 'amounts' => old('component_amounts', []), 'lastPage' => $employees->lastPage(), 'page' => $employees->currentPage(), 'perPage' => $employees->perPage()])
<script type="application/json" id="payroll-selection-state">@json($selectionState)</script>
<noscript><p class="pr-errors">Enable JavaScript to select employees across pages and create payroll.</p></noscript>
<div class="pr-body"><label class="pr-check"><input type="checkbox" name="salary_confirmation" value="1" required @checked(old('salary_confirmation'))><span>I have selected employees belonging to this company. Their current salary profiles apply to this month. Joining dates, leavers and unpaid leave will be reviewed in the next step.</span></label><p class="pr-hint">One payroll per company, currency and month. An employee can only appear once in a month.</p></div>
<div class="pr-footer"><button class="pr-btn pr-btn-primary" type="submit" data-no-spinner>Create draft & load salaries</button><a class="pr-btn" href="/payroll">Cancel</a></div></section>
</form>
@endsection
