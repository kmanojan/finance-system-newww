@extends('layouts.app')
@section('title', 'Add employee')
@section('secondary-sidebar')
@include('navigation.people')
@endsection
@section('content')
<link rel="stylesheet" href="{{ asset('payroll.css') }}?v={{ filemtime(public_path('payroll.css')) }}">
<style>.employee-create-page .pr-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}@media(max-width:600px){.employee-create-page .pr-form-grid{grid-template-columns:1fr}.employee-create-page .pr-footer{flex-wrap:wrap}.employee-create-page .pr-footer button{width:100%}}</style>
<div class="payroll-workspace employee-create-page">
    <header class="pr-header"><div><a href="/employees" class="pr-hint">← Employees</a><h1>Add employee</h1><p>Create an employee profile, then configure salary and components.</p></div></header>
    @if($errors->any())<div role="alert" class="pr-notice"><strong>Check the employee details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="/employees" class="pr-panel">
        @csrf
        <div class="pr-panel-head"><div><h2>Employee details</h2><p class="pr-hint">Fields marked * are required.</p></div></div>
        <div class="pr-body pr-form-grid">
            <div class="pr-field"><label for="first_name">First name *</label><input class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required></div>
            <div class="pr-field"><label for="last_name">Last name</label><input class="form-control" id="last_name" name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name"></div>
            <div class="pr-field"><label for="employee_code">Employee code</label><input class="form-control" id="employee_code" name="employee_code" value="{{ old('employee_code') }}" maxlength="50" placeholder="Auto-generated if blank"><span class="pr-hint">Use a unique code to identify this employee.</span></div>
            <div class="pr-field"><label for="status">Employment status *</label><select class="form-control" id="status" name="status" required><option value="active" @selected(old('status', 'active') === 'active')>Active</option><option value="inactive" @selected(old('status') === 'inactive')>Inactive</option></select></div>
            <div class="pr-field"><label for="job_position">Job title</label><input class="form-control" id="job_position" name="job_position" value="{{ old('job_position') }}" maxlength="255" placeholder="e.g. Software engineer"></div>
            <div class="pr-field"><label for="role">Role</label><input class="form-control" id="role" name="role" value="{{ old('role') }}" maxlength="255" placeholder="e.g. Team lead"><span class="pr-hint">The employee’s work role. Application access is managed separately.</span></div>
            <div class="pr-field"><label for="personal_email">Email</label><input class="form-control" id="personal_email" name="personal_email" type="email" value="{{ old('personal_email') }}" maxlength="255" autocomplete="email"></div>
            <div class="pr-field"><label for="mobile_phone">Phone</label><input class="form-control" id="mobile_phone" name="mobile_phone" type="tel" value="{{ old('mobile_phone') }}" maxlength="50" autocomplete="tel"></div>
        </div>
        <div class="pr-body" style="padding-top:0;"><p class="pr-hint">A linked party is created automatically for payable tracking. Set basic salary, allowances, deductions, and payment details on the next screen.</p></div>
        <div class="pr-footer"><a class="pr-btn" href="/employees">Cancel</a><button type="submit" class="pr-btn pr-btn-primary">Create employee &amp; configure salary</button></div>
    </form>
</div>
@endsection
