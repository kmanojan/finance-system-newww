@extends('layouts.share')
@section('title', 'Partner Hub: ' . $party->name)

@section('content')
<!-- Header Banner Card -->
<div class="card" style="padding: 2rem; background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-page) 100%);">
    <div style="font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-bottom:0.4rem;">
        Partner Financial Portal
    </div>
    <h1 style="margin: 0; font-size: 2.2rem; font-weight: 800; color: var(--text-heading);">{{ $party->name }}</h1>
    <p class="text-muted" style="margin-top: 0.5rem; font-size: 1rem;">Track projects, billing schedules, and commission earnings.</p>
</div>

@if($projects->isEmpty())
    <div class="card" style="text-align: center; padding: 3.5rem;">
        <ion-icon name="folder-open-outline" style="font-size:3rem; opacity:0.3; margin-bottom:0.5rem;"></ion-icon>
        <p class="text-muted">No projects are currently linked to your partner account.</p>
    </div>
@else
    @foreach($projects as $project)
    <div class="card" style="margin-bottom: 2rem; border-left:5px solid var(--primary);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.5rem; font-weight:700; color: var(--text-heading);">{{ $project->name }}</h2>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Start Date: {{ $project->start_date }} &mdash; {{ $project->end_date ?? 'Active' }}
                </div>
            </div>
            <span class="badge" style="background:var(--primary-light); color:var(--primary); font-weight:700; padding: 0.4rem 0.8rem;">
                {{ ucfirst($project->status) }}
            </span>
        </div>

        @if($project->commissions->isNotEmpty())
        <div style="background: var(--bg-page); border: 1px solid var(--border-light); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; font-weight:700; color: var(--text-heading); display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="cash-outline" style="color:var(--success);"></ion-icon> Partner Commission Earnings
            </h3>
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; margin:0;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">
                            <th style="padding: 0.65rem 0.5rem;">Commission Terms</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right;">Earned Commission</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right;">Paid To Date</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right;">Outstanding Payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($project->commissions as $comm)
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.75rem 0.5rem; font-weight:600; font-size:0.9rem; color:var(--text-heading);">
                                @if($comm->commission_type === 'percentage')
                                    {{ number_format($comm->percentage_value, 2) }}% of {{ ucfirst($comm->calculation_basis) }}
                                @else
                                    Fixed {{ $project->currency ?? 'LKR' }} {{ number_format($comm->fixed_amount, 2) }} per {{ ucfirst($comm->trigger_type) }}
                                @endif
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; font-size:0.9rem;">
                                {{ $project->currency ?? 'LKR' }} {{ number_format($comm->total_commission, 2) }}
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right; color: var(--success); font-weight: 700; font-size:0.9rem;">
                                {{ $project->currency ?? 'LKR' }} {{ number_format($comm->paid, 2) }}
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right; color: {{ $comm->payable > 0 ? 'var(--danger)' : 'var(--text-muted)' }}; font-weight: 800; font-size:0.9rem;">
                                {{ $project->currency ?? 'LKR' }} {{ number_format($comm->payable, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
            <div>
                <h3 style="font-size: 1.05rem; font-weight:700; color: var(--text-heading); margin-bottom: 1rem; display:flex; align-items:center; gap:0.4rem;">
                    <ion-icon name="document-text-outline" style="color:var(--primary);"></ion-icon> Invoices Issued
                </h3>
                @if($project->invoices->isEmpty())
                    <p class="text-muted" style="font-size: 0.9rem;">No invoices issued yet.</p>
                @else
                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
                        @foreach($project->invoices as $inv)
                        <li style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px solid var(--border-light);">
                            <span>{{ $inv->invoice_no }} <span style="color:var(--text-muted); font-size:0.8rem;">({{ $inv->issue_date }})</span></span>
                            <span style="font-weight: 700; color:var(--text-heading);">{{ $project->currency ?? 'LKR' }} {{ number_format($inv->amount, 2) }}</span>
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            
            <div>
                <h3 style="font-size: 1.05rem; font-weight:700; color: var(--text-heading); margin-bottom: 1rem; display:flex; align-items:center; gap:0.4rem;">
                    <ion-icon name="card-outline" style="color:var(--success);"></ion-icon> Payments Received
                </h3>
                @if($project->payments->isEmpty())
                    <p class="text-muted" style="font-size: 0.9rem;">No payments recorded yet.</p>
                @else
                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem;">
                        @foreach($project->payments as $pay)
                        <li style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px solid var(--border-light);">
                            <span>{{ $pay->payment_date }}</span>
                            <span style="font-weight: 800; color: var(--success);">{{ $pay->currency ?? ($project->currency ?? 'LKR') }} {{ number_format($pay->total_amount, 2) }}</span>
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @endforeach
@endif
@endsection
