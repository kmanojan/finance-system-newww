@extends('reports.layout')

@section('report-title')
    {{ ucwords(str_replace('_', ' ', request()->route('module'))) }}
@endsection

@section('report-content')
<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 2rem; text-align: center; background: var(--bg-card); border: 1px dashed var(--border); border-radius: 12px;">
    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
        <ion-icon name="construct-outline" style="font-size: 2.5rem; color: var(--primary);"></ion-icon>
    </div>
    <h2 style="color: var(--text-heading); font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">Module Not Installed</h2>
    <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 400px; margin: 0 auto;">
        The underlying system module for this report is not currently installed or configured in this environment.
    </p>
    <button class="btn btn-outline" style="margin-top: 1.5rem;" onclick="window.location.href='{{ route('reports.pnl') }}'">
        Return to P&L
    </button>
</div>
@endsection
