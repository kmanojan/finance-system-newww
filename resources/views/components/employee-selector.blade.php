@props(['name' => 'employee_id', 'id' => null, 'employees' => [], 'selected' => null, 'required' => false, 'placeholder' => 'Select employee...', 'emptyLabel' => 'None (clear selection)'])
@php
    $uid = 'employee-picker-'.uniqid();
    $fieldId = $id ?? $uid.'-field';
@endphp
<div class="employee-selector-component" data-employee-selector data-placeholder="{{ $placeholder }}">
    <select name="{{ $name }}" id="{{ $fieldId }}" class="employee-selector-value" @required($required) aria-label="Employee">
        <option value="">{{ $placeholder }}</option>
        @foreach($employees as $employee)
            <option value="{{ data_get($employee, 'id') }}" data-code="{{ data_get($employee, 'employee_code', '') }}" @selected((string)$selected === (string)data_get($employee, 'id'))>{{ data_get($employee, 'full_name') }}</option>
        @endforeach
    </select>
    <div class="employee-selector-controls" hidden>
        <button type="button" class="form-control employee-selector-trigger" id="{{ $fieldId }}-trigger" aria-haspopup="dialog" aria-controls="{{ $uid }}" aria-expanded="false" @if($required) aria-label="Select employee (required)" @endif>
            <ion-icon name="person-outline" aria-hidden="true"></ion-icon><span data-employee-label>{{ $placeholder }}</span><span class="employee-code" data-employee-code hidden></span><ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon>
        </button>
        <button type="button" class="employee-selector-clear" aria-label="Clear employee selection" hidden><ion-icon name="close-outline" aria-hidden="true"></ion-icon></button>
    </div>
    <span class="employee-selector-error" role="alert" hidden>Please select an employee.</span>
    <dialog class="employee-selector-dialog" id="{{ $uid }}" aria-labelledby="{{ $uid }}-title">
        <header><div><h3 id="{{ $uid }}-title">Select employee</h3><p>Find an employee by name or code.</p></div><button type="button" data-employee-close aria-label="Close employee selector"><ion-icon name="close-outline" aria-hidden="true"></ion-icon></button></header>
        <div class="employee-selector-body">
            <input type="search" class="form-control" data-employee-search placeholder="Search employee name or code..." aria-label="Search employees" autocomplete="off">
            <button type="button" class="employee-selector-option" data-employee-empty>{{ $emptyLabel }}</button>
            <p data-employee-count role="status"></p>
            <div class="employee-selector-list" data-employee-list></div>
            <button type="button" class="btn btn-outline" data-employee-more hidden>Show more employees</button>
        </div>
    </dialog>
</div>
@once
<link rel="stylesheet" href="{{ asset('employee-selector.css') }}?v={{ filemtime(public_path('employee-selector.css')) }}">
<script src="{{ asset('employee-selector.js') }}?v={{ filemtime(public_path('employee-selector.js')) }}" defer></script>
@endonce
