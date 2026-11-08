<details class="pr-panel sa-create" id="new-advance" @if($errors->any() && !old('_advance_action_id')) open @endif>
    <summary><div><h2>Record a paid advance</h2><p>Choose the employee, payment details and the salary month for deduction.</p></div><ion-icon name="add-outline" aria-hidden="true"></ion-icon></summary>
    <form method="post" action="/payroll/advances" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="currency" value="LKR">
        <div class="sa-form-section"><h3>Employee & allocation</h3><div class="pr-form-grid">
            <div class="pr-field"><label for="employee_id">Employee</label><x-employee-selector name="employee_id" id="employee_id" :employees="$employees" :selected="old('employee_id')" :required="true" />@error('employee_id')<span class="sa-error">{{ $message }}</span>@enderror</div>
            <div class="pr-field"><label for="company_id">Company</label><select id="company_id" name="company_id" required data-company><option value="">Select company</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id', $companies->count() === 1 ? $company->id : '') == $company->id)>{{ $company->name }}</option>@endforeach</select>@error('company_id')<span class="sa-error">{{ $message }}</span>@enderror</div>
            <div class="pr-field"><label for="department_id">Department</label><select id="department_id" name="department_id" required data-department><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" data-company="{{ $department->company_id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select>@error('department_id')<span class="sa-error">{{ $message }}</span>@enderror</div>
        </div></div>
        <div class="sa-form-section"><h3>Payment details</h3><div class="pr-form-grid">@include('payroll.payment-fields')
            @foreach(['amount','paid_on','bank_account_id','reference','receipt'] as $field)@error($field)<p class="sa-error pr-full">{{ $message }}</p>@enderror @endforeach
        </div></div>
        <div class="sa-form-section sa-recovery-section"><div><h3>Payroll recovery</h3><p>Pay now, deduct in the salary month you choose.</p></div><div class="pr-field"><label for="recovery_month">Deduct in payroll month</label><input id="recovery_month" name="recovery_month" type="month" value="{{ old('recovery_month') }}" aria-describedby="advance-month-help" required><span class="pr-hint" id="advance-month-help">For example, an advance paid in February can be deducted from March salary. Unrecovered balances carry forward.</span>@error('recovery_month')<span class="sa-error">{{ $message }}</span>@enderror</div></div>
        <div class="pr-footer"><button class="pr-btn pr-btn-primary">Save paid advance</button><button class="pr-btn" type="button" data-advance-cancel>Cancel</button><span class="pr-hint">Records a payment already made; no bank transfer is initiated.</span></div>
    </form>
</details>
