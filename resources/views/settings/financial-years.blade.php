@extends('layouts.app')
@section('title','Financial years')
@section('secondary-sidebar')
@include('masters._sidebar')
@endsection
@section('content')
<header class="page-header"><div><h1>Financial years</h1><p class="subtitle">Manage accounting periods and protect closed dates.</p></div><button class="btn btn-primary" onclick="openModal('yearModal')">Add financial year</button></header>
<div class="ds-stack">@forelse($years as $year)<section class="ds-panel"><h2>{{ $year->title }} <span class="text-muted">{{ $year->start_date->format('Y-m-d') }} – {{ $year->end_date->format('Y-m-d') }}</span></h2>
@if($year->is_closed)
<p class="badge badge-success">Year closed</p>
@else
<form method="POST" action="/settings/financial-years/{{ $year->id }}/close" onsubmit="return confirm('Close this financial year and transfer profit or loss to retained earnings? All periods must be hard-closed.');">@csrf<button class="btn btn-outline" @disabled($year->periods->contains(fn($period)=>$period->status !== 'hard_closed'))>Close year &amp; transfer earnings</button></form>
@endif
<div class="data-table-container"><table class="data-table"><thead><tr><th>Period</th><th>Start</th><th>End</th><th>Status</th><th>Update</th></tr></thead><tbody>@foreach($year->periods as $period)<tr><td data-label="Period">{{ $period->period_name }}</td><td data-label="Start">{{ $period->start_date->format('Y-m-d') }}</td><td data-label="End">{{ $period->end_date->format('Y-m-d') }}</td><td data-label="Status">{{ ucwords(str_replace('_',' ',$period->status)) }}</td><td><form method="POST" action="/settings/fiscal-periods/{{ $period->id }}/status" style="display:flex;gap:8px">@csrf @method('PUT')<select @disabled($year->is_closed) name="status" class="form-control" aria-label="Status for {{ $period->period_name }}">@foreach(['open','soft_closed','hard_closed'] as $status)<option value="{{ $status }}" @selected($period->status===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select><button @disabled($year->is_closed) class="btn btn-outline">Save</button></form></td></tr>@endforeach</tbody></table></div></section>@empty<x-empty-state title="No financial years" description="Create a financial year to manage monthly accounting periods." icon="calendar-outline" actionModal="yearModal" actionText="Add financial year"/>@endforelse</div>
@endsection
@section('modals')
<div class="modal-backdrop" id="yearModal"><div class="modal-card"><div class="modal-header"><h2 class="modal-title">Add financial year</h2><button class="btn-close" onclick="closeModal('yearModal')">×</button></div><form method="POST" action="/settings/financial-years">@csrf<div class="modal-body ds-stack"><div><label class="form-label">Company</label><select class="form-control" name="company_id">@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach</select></div><div><label class="form-label">Title</label><input class="form-control" name="title" placeholder="FY 2026/2027" required></div><div class="ds-form-grid"><div><label class="form-label">Start date</label><input class="form-control" name="start_date" type="date" required></div><div><label class="form-label">End date</label><input class="form-control" name="end_date" type="date" required></div></div></div><div class="modal-footer"><button class="btn btn-primary">Create year &amp; periods</button></div></form></div></div>
@endsection
