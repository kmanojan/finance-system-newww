@extends('layouts.app')
@section('title', $employee->full_name.' - Employee profile')
@section('secondary-sidebar')
@include('navigation.people')
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('people-register.css') }}?v={{ hash_file('sha256', public_path('people-register.css')) }}">
<link rel="stylesheet" href="{{ asset('employee-profile.css') }}?v={{ hash_file('sha256', public_path('employee-profile.css')) }}">
@include('employees.salary-calculator')
<div class="pi-page ep-page" id="salaryCalculatorRoot" x-data="salaryCalculator(window.initialSalaryData)">
    <a class="ep-back" href="/employees"><ion-icon name="arrow-back-outline" aria-hidden="true"></ion-icon> All employees</a>
    <header class="pi-header">
        <div><h1>{{ $employee->full_name }}</h1><p>{{ $employee->employee_code ?: 'EMP-'.$employee->id }} @if($employee->job_position)· {{ $employee->job_position }}@endif · {{ ucfirst($employee->status) }}</p></div>
        <div class="pi-actions"><x-employee-party-action :employee="$employee" /><button data-salary-profile-action type="button" @click="submitForm()" :disabled="isSubmitting" class="pi-button pi-primary"><span x-text="isSubmitting ? 'Saving…' : 'Save salary profile'">Save salary profile</span></button></div>
    </header>
    <p class="pi-notice" role="status" x-show="saveMessage" x-cloak x-text="saveMessage"></p>
    <p class="pi-notice ep-error" role="alert" x-show="saveError" x-cloak x-text="saveError"></p>
    @if(session('success'))<p class="pi-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="pi-notice ep-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="pi-overview" aria-label="Salary profile preview">
        <div class="pi-highlight"><span>Estimated take-home pay</span><strong><small x-text="currency">{{ $employee->currency }}</small> <span x-text="formatNumber(netSalary)">{{ number_format($salaryBreakdown['net_salary'], 2) }}</span></strong><p>Monthly profile preview · changes apply when saved</p></div>
        <dl class="pi-metrics">
            @foreach(['grossSalary' => 'Gross pay', 'totalDeductions' => 'Employee deductions', 'ctc' => 'Employer cost'] as $value => $label)
                <div><dt>{{ $label }}</dt><dd><small x-text="currency">{{ $employee->currency }}</small><span x-text="formatNumber({{ $value }})"></span></dd></div>
            @endforeach
        </dl>
    </section>
    <div class="employee-view-tabs ep-tabs" role="tablist" aria-label="Employee details">
        <button type="button" id="employee-profile-tab" role="tab" aria-selected="true" aria-controls="employee-profile-panel" data-employee-view="profile">Salary components</button>
        <button type="button" id="employee-sheets-tab" role="tab" aria-selected="false" aria-controls="employee-sheets-panel" data-employee-view="sheets" tabindex="-1">Salary sheets <span>{{ $salarySheets->total() }}</span></button>
        <button type="button" id="employee-history-tab" role="tab" aria-selected="false" aria-controls="salary-history" data-employee-view="history" tabindex="-1">Change history <span>{{ $salaryHistory->total() }}</span></button>
    </div>
    <div id="employee-profile-panel" role="tabpanel" aria-labelledby="employee-profile-tab">
        <form id="salaryConfigForm" method="POST" action="/employees/{{ $employee->id }}/salary" @submit.prevent="submitForm()" class="ep-layout">
            @csrf
            <div class="ep-main">
                <section class="ep-panel">
                    <header><h2>Basic salary</h2><p>The monthly amount used to calculate this profile.</p></header>
                    <div class="ep-fields">
                        <div class="pi-field"><label for="basic_salary">Basic salary</label><div @input="updateBasicFromInput($event)" @change="updateBasicFromInput($event)" @blur.capture="updateBasicFromInput($event)"><x-amount-input name="basic_salary" id="basic_salary" :value="$employee->basic_salary" required="true" /></div></div>
                        <div class="pi-field"><label for="profile-currency">Currency</label><select id="profile-currency" name="currency" x-model="currency">@foreach(collect(['LKR','USD','EUR','GBP','AED',$employee->currency])->filter()->unique() as $code)<option value="{{ $code }}" @selected($employee->currency === $code)>{{ $code }}</option>@endforeach</select></div>
                    </div>
                </section>
                @foreach(['earning' => 'Earnings & allowances', 'deduction' => 'Employee deductions', 'employer_contribution' => 'Employer contributions'] as $type => $title)
                    <section class="ep-panel ep-components" aria-labelledby="components-{{ $type }}">
                        <header><h2 id="components-{{ $type }}">{{ $title }}</h2><p>{{ $type === 'earning' ? 'Enable the allowances included in this employee’s salary.' : ($type === 'deduction' ? 'Amounts deducted from the employee’s gross pay.' : 'Contributions paid by the company in addition to gross pay.') }}</p></header>
                        <template x-for="comp in components.filter(c => c.type === '{{ $type }}' && c.code !== 'BASIC')" :key="comp.id">
                            <div class="ep-component">
                                <label class="ep-component-name"><input type="checkbox" x-model="comp.enabled" @change="recalculate()"><span><strong x-text="comp.name"></strong><small x-text="comp.code"></small><small x-show="comp.is_epf_eligible">EPF eligible</small></span></label>
                                <div class="pi-field"><label :for="'component-value-'+comp.id" x-text="comp.calc_type === 'percentage' ? 'Rate (%)' : 'Amount ('+currency+')'"></label><input :id="'component-value-'+comp.id" type="number" min="0" step="0.01" x-model.number="comp.value" @input="recalculate()" :disabled="!comp.enabled"></div>
                                <div class="ep-effect"><span>Monthly amount</span><strong x-text="currency+' '+formatNumber(calculateSingleComponent(comp))"></strong></div>
                            </div>
                        </template>
                        <p class="ep-component-empty" x-show="!components.some(c => c.type === '{{ $type }}' && c.code !== 'BASIC')">No components configured for this category.</p>
                    </section>
                @endforeach
                <a class="ep-back" href="/master/salary-components">Manage salary components <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a>
                <section class="ep-panel"><header><h2>Reason for change</h2><p>Saved with the previous and new salary profile.</p></header><div class="pi-field"><label for="salary_change_reason">Reason (optional)</label><input id="salary_change_reason" name="salary_change_reason" maxlength="1000" placeholder="Annual review, promotion or allowance adjustment"></div></section>
                <template x-for="comp in components" :key="'sync-'+comp.id"><div hidden><input type="hidden" :name="'components['+comp.id+'][id]'" :value="comp.id"><input type="hidden" :name="'components['+comp.id+'][value]'" :value="comp.value || 0"><input type="hidden" :name="'components['+comp.id+'][enabled]'" :value="comp.enabled ? '1' : '0'"></div></template>
            </div>
            <aside class="ep-side">
                <section class="ep-panel"><header><h2>Payment details</h2><p>Bank and membership information for payroll.</p></header><div class="ep-fields">
                    <div class="pi-field"><label for="payment_mode">Payment mode</label><select id="payment_mode" name="payment_mode">@foreach(['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque'] as $value => $label)<option value="{{ $value }}" @selected(($employee->payment_mode ?? 'bank_transfer') === $value)>{{ $label }}</option>@endforeach</select></div>
                    @foreach(['epf_number' => 'EPF / ETF member number', 'bank_name' => 'Bank name', 'bank_branch' => 'Branch', 'bank_account_no' => 'Account number'] as $field => $label)<div class="pi-field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ $employee->$field }}" maxlength="{{ $field === 'bank_name' || $field === 'bank_branch' ? 100 : 50 }}"></div>@endforeach
                </div></section>
                <section class="ep-panel"><header><h2>Calculation summary</h2></header><dl class="ep-calculation">
                    @foreach(['basic' => 'Basic salary', 'totalAllowances' => 'Allowances', 'epfBaseSalary' => 'EPF eligible earnings', 'employeeEpf' => 'Employee EPF', 'apitTax' => 'APIT', 'totalEmployerCost' => 'Employer contributions'] as $value => $label)<div><dt>{{ $label }}</dt><dd x-text="currency+' '+formatNumber({{ $value }})"></dd></div>@endforeach
                </dl></section>
                @if($employee->costAllocations->count())<section class="ep-panel"><header><h2>Recent project allocations</h2></header><dl class="ep-calculation">@foreach($employee->costAllocations->take(5) as $cost)<div><dt>{{ $cost->project->name ?? 'General project' }}<small>{{ \Carbon\Carbon::parse($cost->period_start)->format('M Y') }}</small></dt><dd>{{ $cost->currency }} {{ number_format($cost->amount,2) }}</dd></div>@endforeach</dl></section>@endif
            </aside>
        </form>
    </div>
    @include('employees.salary-sheets')
    @include('employees.salary-history')
</div>
<script defer src="{{ asset('salary-history.js') }}?v={{ hash_file('sha256', public_path('salary-history.js')) }}"></script>
@endsection
