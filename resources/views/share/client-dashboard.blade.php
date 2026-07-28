@extends('layouts.share')
@section('title', 'Client Portal — ' . $party->name)

@section('content')
@php
    $grandInvoiced = $projects->sum('total_invoiced');
    $grandCollected = $projects->sum('total_collected');
    $grandOutstanding = max(0, $grandInvoiced - $grandCollected);
    $realizationPct = ($grandInvoiced > 0) ? min(100, round(($grandCollected / $grandInvoiced) * 100, 1)) : 100;
    $currency = $projects->first()->currency ?? 'LKR';
@endphp

<!-- Glassmorphic Hero Banner -->
<div class="glass-header">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.5rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                <span class="metric-pill" style="background:var(--primary-light); color:var(--primary);">
                    <ion-icon name="business-outline"></ion-icon> Client Account
                </span>
                <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">
                    {{ $projects->count() }} {{ Str::plural('Project', $projects->count()) }} Managed
                </span>
            </div>
            <h1 style="margin: 0; font-size: 2.2rem; font-weight: 800; color: var(--text-heading);">{{ $party->name }}</h1>
            <p style="margin-top: 0.4rem; color: var(--text-muted); font-size: 1rem; max-width:650px;">
                Welcome to your interactive client financial dashboard. Track active deliverables, billed milestones, payments received, and outstanding balances in real time.
            </p>
        </div>

        <div style="min-width:240px; background:var(--bg-card); padding:1.25rem; border-radius:12px; border:1px solid var(--border-light); text-align:center; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted);">
                Billing Settlement Rate
            </div>
            <div style="font-size:2rem; font-weight:800; color:var(--success); margin:0.2rem 0;">
                {{ $realizationPct }}%
            </div>
            <div style="background:var(--bg-page); height:8px; border-radius:10px; overflow:hidden; border:1px solid var(--border-light);">
                <div style="width:{{ $realizationPct }}%; background:linear-gradient(90deg, #10b981, #059669); height:100%; border-radius:10px; transition:width 1s ease;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 3 KPI Metric Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="portal-card" style="margin:0; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size: 0.8rem; text-transform: uppercase; font-weight:700; opacity:0.9; letter-spacing:0.5px;">Total Portfolio Invoiced</span>
            <ion-icon name="document-text-outline" style="font-size:1.4rem; opacity:0.85;"></ion-icon>
        </div>
        <div style="font-size: 1.8rem; font-weight: 800; margin-top: 0.5rem;">
            {{ $currency }} {{ number_format($grandInvoiced, 2) }}
        </div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.3rem;">Total billed across all projects</div>
    </div>

    <div class="portal-card" style="margin:0; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size: 0.8rem; text-transform: uppercase; font-weight:700; opacity:0.9; letter-spacing:0.5px;">Total Paid Settled</span>
            <ion-icon name="checkmark-done-circle-outline" style="font-size:1.4rem; opacity:0.85;"></ion-icon>
        </div>
        <div style="font-size: 1.8rem; font-weight: 800; margin-top: 0.5rem;">
            {{ $currency }} {{ number_format($grandCollected, 2) }}
        </div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.3rem;">Total payments received</div>
    </div>

    <div class="portal-card" style="margin:0; background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size: 0.8rem; text-transform: uppercase; font-weight:700; opacity:0.9; letter-spacing:0.5px;">Total Outstanding Balance</span>
            <ion-icon name="alert-circle-outline" style="font-size:1.4rem; opacity:0.85;"></ion-icon>
        </div>
        <div style="font-size: 1.8rem; font-weight: 800; margin-top: 0.5rem;">
            {{ $currency }} {{ number_format($grandOutstanding, 2) }}
        </div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.3rem;">Remaining balance to settle</div>
    </div>
</div>

<!-- Interactive Toolbar -->
<div class="portal-card" style="padding:1rem 1.25rem; margin-bottom:1.75rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="display:flex; gap:0.5rem; align-items:center;">
        <button type="button" class="interactive-tab-btn active" onclick="filterProjects('all', this)">
            All Projects ({{ $projects->count() }})
        </button>
        <button type="button" class="interactive-tab-btn" onclick="filterProjects('active', this)">
            Active
        </button>
        <button type="button" class="interactive-tab-btn" onclick="filterProjects('completed', this)">
            Completed
        </button>
    </div>

    <div style="min-width:240px; flex:1; max-width:340px;">
        <div style="position:relative;">
            <input type="text" id="projectSearchInput" class="form-control" onkeyup="searchProjects()" placeholder="Search project name..." style="padding-left:2.2rem; font-size:0.88rem; border-radius:8px;">
            <ion-icon name="search-outline" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1rem;"></ion-icon>
        </div>
    </div>
</div>

<!-- Projects Grid -->
@if($projects->isEmpty())
    <div class="portal-card" style="text-align: center; padding: 4rem;">
        <ion-icon name="folder-open-outline" style="font-size:3.5rem; opacity:0.3; margin-bottom:0.5rem;"></ion-icon>
        <h3 style="margin:0; font-weight:700; color:var(--text-heading);">No Projects Available</h3>
        <p class="text-muted" style="margin-top:0.3rem;">No active projects are currently registered under this client portal link.</p>
    </div>
@else
    <div id="projectsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;">
        @foreach($projects as $project)
        @php
            $projInvoiced = $project->total_invoiced;
            $projCollected = $project->total_collected;
            $projBal = max(0, $project->outstanding_balance);
            $projPct = ($projInvoiced > 0) ? min(100, round(($projCollected / $projInvoiced) * 100, 1)) : 100;
        @endphp
        <div class="portal-card project-card-item" data-status="{{ strtolower($project->status) }}" data-name="{{ strtolower($project->name) }}" style="display: flex; flex-direction: column; justify-content: space-between; position:relative; overflow:hidden;">
            <div style="position:absolute; top:0; left:0; width:4px; height:100%; background:{{ $projBal > 0 ? 'var(--primary)' : 'var(--success)' }};"></div>
            
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div>
                        <h2 style="margin: 0; font-size: 1.35rem; font-weight:800; color: var(--text-heading);">{{ $project->name }}</h2>
                        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.2rem;">
                            {{ $project->start_date ?? 'Active' }} &mdash; {{ $project->end_date ?? 'Ongoing' }}
                        </div>
                    </div>
                    @if($project->status === 'active')
                        <span class="metric-pill" style="background:var(--primary-light); color:var(--primary);">Active</span>
                    @elseif($project->status === 'completed')
                        <span class="metric-pill" style="background:#dcfce7; color:#15803d;">Completed</span>
                    @else
                        <span class="metric-pill" style="background:#f1f5f9; color:#475569;">{{ ucfirst($project->status) }}</span>
                    @endif
                </div>
                
                <!-- Financial Progress -->
                <div style="margin-bottom: 1.25rem; background: var(--bg-page); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-light);">
                    <div style="display:flex; justify-content:space-between; font-size:0.78rem; font-weight:700; color:var(--text-muted); margin-bottom:0.3rem;">
                        <span>Payment Realization</span>
                        <span style="color:var(--text-heading);">{{ $projPct }}% Paid</span>
                    </div>
                    <div style="background:var(--bg-card); height:7px; border-radius:10px; overflow:hidden; border:1px solid var(--border-light); margin-bottom:0.75rem;">
                        <div style="width:{{ $projPct }}%; background:linear-gradient(90deg, #10b981, #059669); height:100%; border-radius:10px;"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.9rem;">
                        <div>
                            <div class="text-muted" style="font-size: 0.72rem; font-weight:700; text-transform: uppercase;">Total Billed</div>
                            <div style="font-weight: 800; font-size:1.1rem; color: var(--text-heading); margin-top:0.1rem;">
                                {{ $project->currency ?? $currency }} {{ number_format($projInvoiced, 2) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.72rem; font-weight:700; text-transform: uppercase;">Balance Due</div>
                            <div style="font-weight: 800; font-size:1.1rem; color: {{ $projBal > 0 ? 'var(--danger)' : 'var(--success)' }}; margin-top:0.1rem;">
                                {{ $project->currency ?? $currency }} {{ number_format($projBal, 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <a href="/share/{{ $link->token }}?project_id={{ $project->id }}" class="btn btn-primary-gradient" style="display: block; text-align: center; width: 100%; border-radius:10px; font-weight:700; padding:0.65rem; text-decoration:none; box-shadow:0 4px 15px rgba(79, 70, 229, 0.2);">
                View Full Financial Breakdown <ion-icon name="arrow-forward-outline" style="vertical-align: middle; margin-left: 0.3rem;"></ion-icon>
            </a>
        </div>
        @endforeach
    </div>
@endif

<script>
function filterProjects(status, btn) {
    document.querySelectorAll('.interactive-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const items = document.querySelectorAll('.project-card-item');
    items.forEach(item => {
        if (status === 'all' || item.getAttribute('data-status') === status) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function searchProjects() {
    const val = document.getElementById('projectSearchInput').value.toLowerCase();
    const items = document.querySelectorAll('.project-card-item');
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        if (name.includes(val)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
@endsection
