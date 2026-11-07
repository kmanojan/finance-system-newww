@extends('payroll.layout')
@section('payroll-content')
<header class="pr-header"><div><a href="/payroll" class="pr-back">← Monthly payroll</a><h1>Import historical payroll</h1><p>Create comparison drafts from reviewed salary-sheet data.</p></div></header>
<section class="pr-panel"><div class="pr-body"><p>Upload the reconciled JSON import file with confirmed employee IDs and amounts in cents. Every month keeps its own salary snapshot, source amounts, and sheet row reference.</p><p class="pr-hint">Imports create drafts only. Salary payments, advance disbursements, loan balances, and journals are not created. Historical advance and loan deductions require reconciliation before approval.</p></div>
<form method="POST" action="/payroll/import-history" enctype="multipart/form-data">@csrf
<div class="pr-body pr-form-grid">
<div class="pr-field"><label for="history-company">Company</label><select id="history-company" name="company_id" required>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
<div class="pr-field"><label for="history-department">Department</label><select id="history-department" name="department_id" required>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
<div class="pr-field pr-full"><label for="history-file">Reviewed import file</label><input id="history-file" name="source_file" type="file" accept=".json,application/json" required><span class="pr-hint">Maximum 4 MB. Reimporting the same source returns the existing drafts; a conflicting payroll prevents the import.</span></div>
<div class="pr-full"><label class="pr-check"><input name="fill_profiles" type="checkbox" value="1"><span>Fill employee profiles with no basic salary using their latest nonzero source basic. Existing salaries and component settings are preserved; monthly components stay in the historical drafts.</span></label></div>
<div class="pr-full"><label class="pr-check"><input name="confirm_mapping" type="checkbox" value="1" required><span>I confirmed each source name matches the correct employee and selected the correct company and department.</span></label></div>
</div><div class="pr-footer"><button class="pr-btn pr-btn-primary">Create comparison drafts</button><a class="pr-btn" href="/payroll">Cancel</a></div></form></section>
@endsection
