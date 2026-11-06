@extends('layouts.app')
@section('title', 'Payroll')
@section('secondary-sidebar')
@include('navigation.people')
@endsection
@section('content')
<link rel="stylesheet" href="{{ asset('payroll.css') }}?v={{ filemtime(public_path('payroll.css')) }}">
<div class="payroll-workspace">
    @if(session('success'))<div class="pr-notice" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="pr-errors" role="alert"><strong>Check the following</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('payroll-content')
</div>
@endsection
@section('scripts')
<script src="{{ asset('payroll.js') }}?v={{ filemtime(public_path('payroll.js')) }}" defer></script>
<script src="{{ asset('payroll-create.js') }}?v={{ filemtime(public_path('payroll-create.js')) }}" defer></script>
@endsection
