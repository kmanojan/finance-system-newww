@extends('layouts.app')
@section('title', 'Project Hub: ' . $project->name)

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <a href="/projects" class="btn btn-outline btn-pill" style="margin-bottom: 2rem; width: 100%;">
        <ion-icon name="arrow-back-outline"></ion-icon> Back to Projects
    </a>
    <h2 class="sidebar-title">Project Hub</h2>
    <nav class="nav-links">
        <a href="#overview" class="nav-link active" onclick="switchTab('overview', this)">Overview</a>
        <a href="#invoices" class="nav-link" onclick="switchTab('invoices', this)">
            Invoices
            @if($draftInvoices->count() > 0)
                <span class="badge" style="background:var(--danger); color:#fff; font-size:0.75rem; padding:0.2rem 0.5rem; border-radius:50%; margin-left:5px;">{{ $draftInvoices->count() }}</span>
            @endif
        </a>
        <a href="#payments" class="nav-link" onclick="switchTab('payments', this)">Payments</a>
        <a href="#change-requests" class="nav-link" onclick="switchTab('change-requests', this)">Change Requests</a>
        <a href="#notes" class="nav-link" onclick="switchTab('notes', this)">Notes & Interactions</a>
        <a href="#commission-setup" class="nav-link" onclick="switchTab('commission-setup', this)">Commission Setup</a>
        <a href="#invoice-schedule" class="nav-link" onclick="switchTab('invoice-schedule', this)">Invoice Schedule</a>
        <a href="#payment-milestones" class="nav-link" onclick="switchTab('payment-milestones', this)">
            Payment Milestones
            @if($milestonesDueTodayCount > 0)
                <span class="badge" style="background:#FEE2E2;color:#991B1B;padding:0.2em 0.6em;margin-left:0.5rem;font-size:0.8rem;">{{ $milestonesDueTodayCount }} Due Today</span>
            @endif
        </a>
        <a href="#documents" class="nav-link" onclick="switchTab('documents', this)">
            Documents
            @if(count($documents) > 0)
                <span class="badge" style="background:#E0E7FF;color:#3730A3;padding:0.2em 0.6em;margin-left:0.5rem;font-size:0.8rem;">{{ count($documents) }}</span>
            @endif
        </a>
        <a href="#cost-allocations" class="nav-link" onclick="switchTab('cost-allocations', this)">
            Cost Allocations
        </a>
    </nav>
</aside>
@endsection

@section('content')
<style>
    /* Creative Dashboard CSS */
    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255,255,255,0.4);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 1.5rem;
    }
    .glass-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    }
    .metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .metric-card {
        padding: 1.5rem;
        border-radius: 16px;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .metric-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        transform: scale(1);
        transition: transform 0.5s ease;
        pointer-events: none;
    }
    .metric-card:hover::after {
        transform: scale(1.2);
    }
    .metric-card h3 { font-size: 0.9rem; font-weight: 500; opacity: 0.9; margin-bottom: 0.5rem; }
    .metric-card .value { font-size: 2rem; font-weight: 700; letter-spacing: -0.5px; }
    
    .grad-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .grad-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .grad-orange { background: linear-gradient(135deg, #f12711 0%, #f5af19 100%); }
    .grad-blue { background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); }

    .budget-track { background: #e2e8f0; border-radius: 10px; height: 12px; width: 100%; margin-top: 1rem; overflow: hidden; position: relative; }
    .budget-fill { background: linear-gradient(90deg, #f5af19, #f12711); height: 100%; border-radius: 10px; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); }
    
    .section-title { font-size: 1.2rem; font-weight: 600; color: var(--text-heading); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; justify-content: space-between; }
    
    .mini-table { width: 100%; border-collapse: collapse; }
    .mini-table th, .mini-table td { padding: 0.75rem 0.5rem; border-bottom: 1px solid var(--border-light); text-align: left; font-size: 0.9rem; }
    .mini-table th { color: var(--text-muted); font-weight: 500; }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 768px) {
        .metric-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .metric-card {
            padding: 1.25rem 1rem;
            border-radius: 12px;
        }
        .metric-card .value {
            font-size: 1.5rem;
        }
        .glass-card {
            padding: 1.25rem 1rem;
            border-radius: 12px;
        }
        .section-title {
            font-size: 1.05rem;
            flex-wrap: wrap;
        }
        .mini-table {
            min-width: 500px;
        }
    }
</style>

<header class="page-header">
    <div class="header-titles">
        <h1 style="font-size: 2.2rem; letter-spacing: -1px; background: linear-gradient(90deg, #1e293b, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            {{ $project->name }}
        </h1>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center;">
        @if($project->status === 'active')
            <span class="badge" style="background:#DCFCE7;color:#166534;font-size:1rem;padding:0.6em 1.2em;border-radius:8px;">Active Project</span>
        @else
            <span class="badge badge-draft" style="font-size:1rem;padding:0.6em 1.2em;">{{ ucfirst($project->status) }}</span>
        @endif
        @if($clients->count() > 0)
            <form action="/share-links" method="POST" style="margin:0;">
                @csrf
                <input type="hidden" name="shareable_type" value="project">
                <input type="hidden" name="shareable_id" value="{{ $project->id }}">
                <input type="hidden" name="audience" value="client">
                <input type="hidden" name="expires_at" value="{{ \Carbon\Carbon::now()->addDays(30)->format('Y-m-d') }}">
                <button type="submit" class="btn btn-outline btn-pill" title="Share with Client" style="border-color: var(--primary); color: var(--primary);">
                    <ion-icon name="share-social-outline" style="vertical-align: middle;"></ion-icon> Share
                </button>
            </form>
        @endif
        <button type="button" class="btn btn-outline btn-pill" onclick="openModal('editProjectModal_{{ $project->id }}')"><ion-icon name="create-outline" style="vertical-align: middle;"></ion-icon> Edit</button>
    </div>
</header>

@if(session('success'))
<div class="alert alert-success" style="background:#dcfce7; color:#166534; padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
    {{ session('success') }}
    @if(session('generated_link'))
        <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" readonly value="{{ session('generated_link') }}" class="form-control" style="max-width: 400px; background: #fff; padding: 0.4rem 0.8rem; font-size: 0.9rem;">
            <button type="button" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.9rem;" onclick="navigator.clipboard.writeText('{{ session('generated_link') }}'); alert('Copied to clipboard!')">Copy Link</button>
        </div>
    @endif
</div>
@endif

<!-- ================== TAB 1: OVERVIEW ================== -->
<div id="overview" class="tab-content active">

    <!-- Revenue Realization Progress Banner -->
    <div class="glass-card" style="background: var(--bg-card); border: 1px solid var(--border); border-left: 6px solid var(--primary); margin-bottom: 2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <span style="font-size:0.85rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); display:flex; align-items:center; gap:0.4rem;">
                    <ion-icon name="analytics-outline" style="color:var(--primary);"></ion-icon> Total Revenue Realization Base
                </span>
                <h2 style="font-size:2rem; font-weight:800; color:var(--text-heading); margin:0.3rem 0 0 0;">
                    {{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($project->budget_limit + $totalApprovedCR, 2) }}
                    <small style="font-size:0.9rem; font-weight:500; color:var(--text-muted);">(Budget: {{ number_format($project->budget_limit, 2) }} + CRs: {{ number_format($totalApprovedCR, 2) }})</small>
                </h2>
            </div>
            <div style="display:flex; gap:1rem; align-items:center;">
                <div style="background:var(--primary-light); padding:0.6rem 1.2rem; border-radius:10px; text-align:center;">
                    <span style="font-size:0.75rem; color:var(--primary); font-weight:600; text-transform:uppercase;">Realization Rate</span>
                    <div style="font-size:1.4rem; font-weight:700; color:var(--primary);">{{ $collectionRate }}%</div>
                </div>
            </div>
        </div>

        @php
            $revBase = max(1, $project->budget_limit + $totalApprovedCR);
            $invoicedPct = min(100, round(($totalInvoiced / $revBase) * 100, 1));
            $collectedPct = min(100, round(($invoiceCollected / $revBase) * 100, 1));
        @endphp

        <!-- Progress Bars -->
        <div style="margin-top: 1.5rem;">
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600; color:var(--text-main); margin-bottom:0.4rem;">
                <span>Billing Realization Progress</span>
                <span style="color:var(--text-heading);">{{ $invoicedPct }}% Invoiced ({{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalInvoiced, 2) }})</span>
            </div>
            <div style="background:var(--bg-page); border-radius:8px; height:10px; overflow:hidden; border:1px solid var(--border-light);">
                <div style="width:{{ $invoicedPct }}%; background: linear-gradient(90deg, #3b82f6, #0284c7); height:100%; border-radius:8px; transition:width 0.8s ease;"></div>
            </div>

            <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:600; color:var(--text-main); margin-top:0.8rem; margin-bottom:0.4rem;">
                <span>Collection Realization Progress</span>
                <span style="color:var(--text-heading);">{{ $collectedPct }}% Collected ({{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($invoiceCollected, 2) }})</span>
            </div>
            <div style="background:var(--bg-page); border-radius:8px; height:10px; overflow:hidden; border:1px solid var(--border-light);">
                <div style="width:{{ $collectedPct }}%; background: linear-gradient(90deg, #10b981, #059669); height:100%; border-radius:8px; transition:width 0.8s ease;"></div>
            </div>
        </div>
    </div>

    <!-- Categorized Metric Sections -->
    <div style="display:flex; flex-direction:column; gap:1.25rem;">

        <!-- Section 1: Contract & Scope (Revenue Base) -->
        <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:1.2rem;">
            <div style="font-weight:700; font-size:0.95rem; color:var(--text-heading); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="contract-outline" style="color:var(--primary); font-size:1.15rem;"></ion-icon>
                <span>Contract & Scope (Revenue Base)</span>
            </div>
            <div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap:1rem;">
                <!-- Tile 1: Project Budget -->
                <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Project Budget</h3>
                        <ion-icon name="wallet-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($project->budget_limit, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Initial Base Budget</div>
                </div>

                <!-- Tile 2: Approved Change Requests -->
                <div class="metric-card" style="background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Approved CRs</h3>
                        <ion-icon name="document-text-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">+{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalApprovedCR, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Scope Adjustments</div>
                </div>

                <!-- Tile 3: Total Revenue Base -->
                <div class="metric-card" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Total Project Value</h3>
                        <ion-icon name="trending-up-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($project->budget_limit + $totalApprovedCR, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Budget + Approved CR</div>
                </div>
            </div>
        </div>

        <!-- Section 2: Billing & Cash Collections (Money Flow) -->
        <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:1.2rem;">
            <div style="font-weight:700; font-size:0.95rem; color:var(--text-heading); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="cash-outline" style="color:#16a34a; font-size:1.15rem;"></ion-icon>
                <span>Billing & Cash Collections</span>
            </div>
            <div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                <!-- Tile 1: Cash Collected -->
                <div class="metric-card" style="background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Cash Collected</h3>
                        <ion-icon name="cash-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalCollected, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Total Cash Received</div>
                </div>

                <!-- Tile 2: Invoices Issued -->
                <div class="metric-card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Invoices Issued</h3>
                        <ion-icon name="paper-plane-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalInvoiced, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Total Billed to Client</div>
                </div>

                <!-- Tile 3: Outstanding Balance -->
                <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Outstanding Balance</h3>
                        <ion-icon name="alert-circle-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($outstandingBalance, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Pending Billed Collection</div>
                </div>

                <!-- Tile 4: Realization Rate -->
                <div class="metric-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Collection Rate</h3>
                        <ion-icon name="pie-chart-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $collectionRate }}%</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Settled / Billed Ratio</div>
                </div>
            </div>
        </div>

        <!-- Section 3: Expenses & Profitability (Bottom Line) -->
        <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:1.2rem;">
            <div style="font-weight:700; font-size:0.95rem; color:var(--text-heading); margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="stats-chart-outline" style="color:#0284c7; font-size:1.15rem;"></ion-icon>
                <span>Expenses & Profitability (Bottom Line)</span>
            </div>
            <div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                <!-- Tile 1: External Commissions -->
                <div class="metric-card" style="background: linear-gradient(135deg, #db2777 0%, #ec4899 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Commissions</h3>
                        <ion-icon name="people-circle-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">-{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalCommission, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">External Partner Payouts</div>
                </div>

                <!-- Tile 2: Internal Cost Allocations -->
                <div class="metric-card" style="background: linear-gradient(135deg, #475569 0%, #64748b 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Cost Allocations</h3>
                        <ion-icon name="calculator-outline" style="font-size:1.3rem; opacity:0.8;"></ion-icon>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">-{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalCostAllocation, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Employee + Server + Direct</div>
                </div>

                <!-- Tile 3: Project Profit 👁️ -->
                <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center; position:relative; z-index:10;">
                        <h3 style="margin:0;">Project Profit</h3>
                        <button type="button" onclick="openModal('projectProfitModal')" title="View Breakdown" style="position:relative; z-index:20; background:rgba(255,255,255,0.25); border:none; color:white; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.2rem; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.45)'" onmouseout="this.style.background='rgba(255,255,255,0.25)'">
                            <ion-icon name="eye-outline" style="pointer-events:none;"></ion-icon>
                        </button>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($projectProfit, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">(Budget + CR) - Commission</div>
                </div>

                <!-- Tile 4: Company Net Profit 👁️ -->
                <div class="metric-card" style="background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%);">
                    <div style="display:flex; justify-content:space-between; align-items:center; position:relative; z-index:10;">
                        <h3 style="margin:0;">Company Net Profit</h3>
                        <button type="button" onclick="openModal('companyProfitModal')" title="View Breakdown" style="position:relative; z-index:20; background:rgba(255,255,255,0.25); border:none; color:white; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.2rem; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.45)'" onmouseout="this.style.background='rgba(255,255,255,0.25)'">
                            <ion-icon name="eye-outline" style="pointer-events:none;"></ion-icon>
                        </button>
                    </div>
                    <div class="value" style="font-size:1.5rem; margin-top:0.3rem;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($companyProfit, 2) }}</div>
                    <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Net Bottom Line</div>
                </div>
            </div>
        </div>

    </div>


    <!-- Charts & Health Breakdown Section -->
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.5rem; margin-top:1.5rem;">
        <div class="card">
            <h2 class="section-title"><ion-icon name="bar-chart-outline" style="color:var(--primary);"></ion-icon> Financial Performance & Profitability</h2>
            <div style="height: 320px; width: 100%;">
                <canvas id="financialOverviewChart"></canvas>
            </div>
        </div>
        <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <h2 class="section-title"><ion-icon name="pie-chart-outline" style="color:var(--primary);"></ion-icon> Billing & Invoice Realization</h2>
                <div style="height: 180px; width: 100%; display: flex; justify-content: center; align-items: center; margin-bottom: 1rem;">
                    <canvas id="invoiceHealthChart"></canvas>
                </div>
            </div>
            
            <div style="border-top:1px solid var(--border-light); padding-top:1rem; margin-top:1rem;">
                <h3 class="section-title" style="font-size:0.95rem; margin-bottom:0.75rem;"><ion-icon name="calendar-outline" style="color:var(--primary);"></ion-icon> Project Schedule & Duration</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem; background:var(--bg-page); padding:0.85rem; border-radius:10px; border:1px solid var(--border-light);">
                    <div><span class="text-muted" style="font-size:0.75rem; display:block;">Start Date</span> <strong style="color:var(--text-heading); font-size:0.85rem;">{{ $project->start_date ?? 'Not set' }}</strong></div>
                    <div><span class="text-muted" style="font-size:0.75rem; display:block;">End Date</span> <strong style="color:var(--text-heading); font-size:0.85rem;">{{ $project->end_date ?? 'Not set' }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stakeholders Card -->
    <div class="card" style="margin-top:1.5rem;">
        <h2 class="section-title"><ion-icon name="people-outline" style="color:var(--primary);"></ion-icon> Project Stakeholders</h2>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
            <div>
                <span class="text-muted" style="font-size:0.8rem; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Clients</span>
                @forelse($clients as $c)
                    <div style="padding:0.75rem 1rem; background:var(--bg-page); border-radius:10px; margin-top:0.5rem; border:1px solid var(--border-light); display:flex; align-items:center; gap:0.5rem;">
                        <ion-icon name="business-outline" style="color:var(--primary); font-size:1.1rem;"></ion-icon>
                        <div style="font-weight:500; color:var(--text-heading);">{{ $c->name }}</div>
                    </div>
                @empty
                    <div class="text-muted" style="padding:0.5rem 0;">No clients assigned.</div>
                @endforelse
            </div>
            <div>
                <span class="text-muted" style="font-size:0.8rem; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Partners</span>
                @forelse($partners as $p)
                    <div style="padding:0.75rem 1rem; background:var(--bg-page); border-radius:10px; margin-top:0.5rem; border:1px solid var(--border-light); display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <ion-icon name="person-outline" style="color:var(--primary); font-size:1.1rem;"></ion-icon>
                            <span style="font-weight:500; color:var(--text-heading);">{{ $p->name }}</span>
                        </div>
                        <span class="badge" style="background:var(--primary-light); color:var(--primary); font-weight:600;">{{ $p->share_percentage }}% Share</span>
                    </div>
                @empty
                    <div class="text-muted" style="padding:0.5rem 0;">No partners assigned.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- ================== TAB 2: INVOICES ================== -->
<div id="invoices" class="tab-content">
    <div class="card">
        <div class="section-title">
            <span><ion-icon name="document-text-outline"></ion-icon> Project Invoices</span>
            <button type="button" class="btn btn-primary-gradient btn-pill" onclick="openModal('createInvoiceModal')"><ion-icon name="add"></ion-icon> Create Invoice</button>
        </div>
        @if($invoices->isEmpty())
            <p class="text-muted" style="text-align:center; padding:2rem;">No invoices generated yet.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Invoice No</th><th>Status</th><th>Date</th><th>Due Date</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Balance</th><th style="text-align:center;">Action</th></tr></thead>
                <tbody>
                    @foreach($invoices as $inv)
                    <tr>
                        <td style="font-weight:500;">{{ $inv->invoice_no }}</td>
                        <td>
                            @php
                                $statusColors = [
                                    'draft' => 'background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;',
                                    'sent' => 'background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;',
                                    'paid' => 'background:#dcfce7; color:#166534; border:1px solid #bbf7d0;',
                                    'overdue' => 'background:#fee2e2; color:#991b1b; border:1px solid #fecaca;',
                                    'cancelled' => 'background:#f3f4f6; color:#374151; border:1px solid #d1d5db;',
                                ];
                                $currentStyle = $statusColors[$inv->status] ?? $statusColors['draft'];
                            @endphp
                            <span style="display:inline-block; border-radius:999px; padding:0.3rem 1rem; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; {{ $currentStyle }}">{{ $inv->status }}</span>
                        </td>
                        <td>{{ $inv->issue_date ?? 'N/A' }}</td>
                        <td>{{ $inv->due_date ?? 'N/A' }}</td>
                        <td style="text-align:right; font-weight:600;">${{ number_format($inv->amount, 2) }}</td>
                        @php
                            $collected = DB::table('payment_allocations')->where('invoice_id', $inv->id)->sum('amount');
                            $balance = max(0, $inv->amount - $collected);
                        @endphp
                        <td style="text-align:right; font-weight:600; color: {{ $balance > 0 ? 'var(--danger)' : 'var(--success)' }};">${{ number_format($balance, 2) }}</td>
                        <td style="text-align:center;">
                            <div class="dropdown" style="position:relative; display:inline-block; text-align:left;">
                                <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; border:none; font-size:1.2rem; cursor:pointer;" onclick="toggleDropdown(this)">
                                    <ion-icon name="ellipsis-vertical"></ion-icon>
                                </button>
                                <div class="dropdown-menu" style="display:none; position:absolute; right:0; top:100%; background:#fff; min-width:160px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); border-radius:8px; border:1px solid #e2e8f0; z-index:50;">
                                    @if($inv->status === 'draft' || $inv->status === 'pending_approval')
                                    <form action="/projects/{{ $project->id }}/invoices/{{ $inv->id }}/approve" method="POST" style="display:block;">
                                        @csrf
                                        <button type="submit" style="display:block; width:100%; text-align:left; border:none; background:transparent; padding:0.6rem 1rem; color:var(--primary); font-size:0.85rem; cursor:pointer; border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                            <ion-icon name="checkbox-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Approve
                                        </button>
                                    </form>
                                    @endif
                                    <a href="javascript:void(0)" style="display:block; padding:0.6rem 1rem; color:#334155; text-decoration:none; font-size:0.85rem; border-bottom:1px solid #f1f5f9;" onclick="openModal('viewInvoiceModal_{{ $inv->id }}'); closeDropdown(this)" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                        <ion-icon name="eye-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> View Details
                                    </a>
                                    <a href="javascript:void(0)" style="display:block; padding:0.6rem 1rem; color:#334155; text-decoration:none; font-size:0.85rem; border-bottom:1px solid #f1f5f9;" onclick="openModal('changeStatusModal_{{ $inv->id }}'); closeDropdown(this)" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                        <ion-icon name="swap-horizontal-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Change Status
                                    </a>
                                    <a href="/invoices/{{ $inv->id }}/pdf" style="display:block; padding:0.6rem 1rem; color:#10b981; text-decoration:none; font-size:0.85rem;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                        <ion-icon name="download-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Download PDF
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<!-- ================== TAB 3: PAYMENTS ================== -->
<div id="payments" class="tab-content">
    <div class="card">
        <div class="section-title">
            <span><ion-icon name="card-outline"></ion-icon> Payments Received</span>
            <button type="button" class="btn btn-primary-gradient btn-pill" onclick="openModal('createPaymentModal')"><ion-icon name="add"></ion-icon> Record Payment</button>
        </div>
        @if($payments->isEmpty())
            <p class="text-muted" style="text-align:center; padding:2rem;">No payments recorded yet.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Payment Date</th><th>Currency</th><th>Applied To</th><th style="text-align:right;">Collected Amount</th><th style="text-align:center;">Action</th></tr></thead>
                <tbody>
                    @foreach($payments as $pay)
                    <tr>
                        <td>{{ $pay->payment_date }}</td>
                        <td>{{ $pay->currency }}</td>
                        <td>
                            @php
                                $allocs = DB::table('payment_allocations')
                                            ->join('invoices', 'payment_allocations.invoice_id', '=', 'invoices.id')
                                            ->where('payment_allocations.payment_id', $pay->id)
                                            ->pluck('invoices.invoice_no')
                                            ->toArray();
                                $appliedTo = empty($allocs) ? 'General Payment' : implode(', ', $allocs);
                            @endphp
                            <span class="badge" style="background:var(--bg-alt); color:var(--text); border:1px solid var(--border);">{{ $appliedTo }}</span>
                        </td>
                        <td style="text-align:right; font-weight:600; color:var(--success);">${{ number_format($pay->total_amount, 2) }}</td>
                        <td style="text-align:center;">
                            <button type="button" class="btn btn-outline btn-pill" style="padding:0.2rem 0.8rem; font-size:0.8rem;" onclick="openModal('viewPaymentModal_{{ $pay->id }}')">View</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-alt); font-weight:700;">
                        <td colspan="3" style="text-align:right;">Total Collected:</td>
                        <td style="text-align:right; color:var(--success);">${{ number_format($totalCollected, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>

<!-- ================== TAB 4: CHANGE REQUESTS ================== -->
<div id="change-requests" class="tab-content">
    <div class="card">
        <div class="section-title">
            <span><ion-icon name="git-pull-request-outline"></ion-icon> Change Requests</span>
            <button type="button" class="btn btn-primary-gradient btn-pill" onclick="openModal('createCRModal')"><ion-icon name="add"></ion-icon> New Change Request</button>
        </div>
        @if($change_requests->isEmpty())
            <p class="text-muted" style="text-align:center; padding:2rem;">No change requests yet.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Requested Date</th><th>Description</th><th>Status</th><th style="text-align:right;">Amount</th><th style="text-align:center;">Action</th></tr></thead>
                <tbody>
                    @foreach($change_requests as $cr)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($cr->created_at)->format('Y-m-d') }}</td>
                        <td>
                            <span style="font-weight:600;">{{ $cr->description }}</span>
                            @if(!empty($cr->attachments) && $cr->attachments->count() > 0)
                                <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.7rem; margin-left:4px;" title="Has Attachments">
                                    <ion-icon name="attach-outline"></ion-icon> {{ $cr->attachments->count() }}
                                </span>
                            @endif
                            @if(!empty($cr->links) && count($cr->links) > 0)
                                <span class="badge" style="background:#f3e8ff; color:#6b21a8; font-size:0.7rem; margin-left:4px;" title="Has External Links">
                                    <ion-icon name="link-outline"></ion-icon> {{ count($cr->links) }}
                                </span>
                            @endif
                        </td>
                        <td><span class="badge">{{ ucfirst($cr->status) }}</span></td>
                        <td style="text-align:right;">{{ $cr->currency ?? ($project->currency ?? 'LKR') }} {{ number_format($cr->amount, 2) }}</td>
                        <td style="text-align:center;">
                            <div style="display:flex; gap:0.4rem; justify-content:center; align-items:center;">
                                <button type="button" class="btn btn-sm btn-outline" onclick='openViewCRModal(@json($cr))' title="View Attachments & Links">
                                    <ion-icon name="eye-outline"></ion-icon> View
                                </button>
                                @if($cr->status === 'pending')
                                    <form action="/projects/{{ $project->id }}/change-requests/{{ $cr->id }}/approve" method="POST" style="display:inline; margin:0;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline" style="color:var(--success); border-color:var(--success);">Approve</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<!-- ================== TAB 5: NOTES & INTERACTIONS ================== -->
<div id="notes" class="tab-content">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
        <div class="card">
            <div class="section-title">
                <span><ion-icon name="document-text-outline"></ion-icon> Notes</span>
                <button type="button" class="btn btn-outline btn-pill" onclick="openModal('createNoteModal')">Add Note</button>
            </div>
            @foreach($notes as $note)
                <div style="padding:1rem; background:var(--bg-page); border:1px solid var(--border-light); border-radius:8px; margin-bottom:1rem;">
                    <p style="margin-bottom:0.5rem; color:var(--text-main);">{{ $note->content }}</p>
                    <small class="text-muted">{{ \Carbon\Carbon::parse($note->created_at)->format('Y-m-d H:i') }}</small>
                </div>
            @endforeach
        </div>
        
        <div class="card">
            <div class="section-title">
                <span><ion-icon name="chatbubbles-outline"></ion-icon> Interactions</span>
                <button type="button" class="btn btn-outline btn-pill" onclick="openModal('createInteractionModal')">Log Interaction</button>
            </div>
            @foreach($interactions as $interaction)
                <div style="padding:1rem; background:var(--bg-page); border:1px solid var(--border-light); border-radius:8px; margin-bottom:1rem;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                        <span class="badge" style="background:var(--primary-light); color:var(--primary);">{{ ucfirst($interaction->type) }}</span>
                        <small class="text-muted">{{ \Carbon\Carbon::parse($interaction->interaction_date)->format('Y-m-d H:i') }}</small>
                    </div>
                    <p style="color:var(--text-main);">{{ $interaction->summary }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>


<!-- ================== TAB 6: COMMISSION SETUP ================== -->
<div id="commission-setup" class="tab-content">
    <div class="card">
        <div class="section-title">
            <span><ion-icon name="cash-outline"></ion-icon> Commission Setup</span>
            <button type="button" class="btn btn-primary-gradient btn-pill" onclick="openModal('createCommissionModal')"><ion-icon name="add"></ion-icon> Add Commission</button>
        </div>
        @if($commissions->isEmpty())
            <p class="text-muted" style="text-align:center; padding:2rem;">No commissions configured for this project yet.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Party Name</th>
                        <th>Type & Details</th>
                        <th style="text-align:right;">Total Commission</th>
                        <th style="text-align:right;">Paid</th>
                        <th style="text-align:right;">Payable</th>
                        <th>Status</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($commissions as $comm)
                    <tr>
                        <td style="font-weight:500;">{{ $comm->party_name }}</td>
                        <td>
                            @if($comm->commission_type === 'percentage')
                                <span class="badge" style="background:var(--primary-light); color:var(--primary);">{{ number_format($comm->percentage_value, 2) }}%</span>
                                <small class="text-muted">of {{ ucfirst($comm->calculation_basis) }}</small>
                            @else
                                <span class="badge" style="background:#f1f5f9; color:#475569;">{{ $comm->currency }} {{ number_format($comm->fixed_amount, 2) }}</span>
                                <small class="text-muted">Trigger: {{ ucfirst($comm->trigger_type) }}</small>
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:600;">{{ $project->currency }} {{ number_format($comm->total_commission, 2) }}</td>
                        <td style="text-align:right; font-weight:600; color:var(--success);">{{ $project->currency }} {{ number_format($comm->paid, 2) }}</td>
                        <td style="text-align:right; font-weight:600; color:{{ $comm->payable > 0 ? 'var(--danger)' : 'var(--text-muted)' }};">{{ $project->currency }} {{ number_format($comm->payable, 2) }}</td>
                        <td>
                            <span class="badge" style="background:{{ $comm->status === 'active' ? 'var(--success-light)' : '#f1f5f9' }}; color:{{ $comm->status === 'active' ? 'var(--success)' : '#475569' }};">
                                {{ ucfirst($comm->status) }}
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <div class="dropdown" style="position:relative; display:inline-block; text-align:left;">
                                <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; border:none; font-size:1.2rem; cursor:pointer;" onclick="toggleDropdown(this)">
                                    <ion-icon name="ellipsis-vertical"></ion-icon>
                                </button>
                                <div class="dropdown-menu" style="display:none; position:absolute; right:0; top:100%; background:var(--bg-card); min-width:160px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1); border-radius:8px; border:1px solid var(--border); z-index:50;">
                                    @if($comm->payable > 0)
                                    <a href="javascript:void(0)" style="display:block; padding:0.6rem 1rem; color:var(--text-main); text-decoration:none; font-size:0.85rem; border-bottom:1px solid var(--border-light);" onclick="openPaymentModal({{ json_encode($comm) }}); closeDropdown(this)" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                        <ion-icon name="wallet-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Record Payment
                                    </a>
                                    @endif
                                    <a href="javascript:void(0)" style="display:block; padding:0.6rem 1rem; color:var(--text-main); text-decoration:none; font-size:0.85rem; border-bottom:1px solid var(--border-light);" onclick="openEditCommissionModal({{ json_encode($comm) }}); closeDropdown(this)" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                        <ion-icon name="create-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Edit Setup
                                    </a>
                                    <form action="/projects/{{ $project->id }}/commissions/{{ $comm->id }}" method="POST" style="display:block;" onsubmit="return confirm('Remove this commission setup?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="display:block; width:100%; text-align:left; border:none; background:transparent; padding:0.6rem 1rem; color:var(--danger); font-size:0.85rem; cursor:pointer;" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                            <ion-icon name="trash-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>


<!-- ================== TAB 7: INVOICE SCHEDULE ================== -->
<div id="invoice-schedule" class="tab-content">
    <div class="card">
        <div class="section-title">
            <span><ion-icon name="calendar-outline"></ion-icon> Invoice Generation Schedule</span>
            <div style="display:flex; gap:1rem;">
                @if($draftInvoices->count() > 0)
                <button type="button" class="btn btn-outline btn-pill" onclick="openApprovalSidebar()">
                    <ion-icon name="checkbox-outline"></ion-icon> Approval Inbox ({{ $draftInvoices->count() }})
                </button>
                @endif
                <button type="button" class="btn btn-primary-gradient btn-pill" onclick="openModal('createScheduleModal')">
                    <ion-icon name="add-outline"></ion-icon> Create Schedule
                </button>
            </div>
        </div>
        @if($schedules->isEmpty())
            <p class="text-muted" style="text-align:center; padding:2rem;">No recurring invoice schedules defined for this project.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Schedule Name</th>
                        <th>Frequency</th>
                        <th>Validity Period</th>
                        <th>Next Generation</th>
                        <th>Status</th>
                        <th>Generated</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedules as $s)
                    <tr>
                        <td style="font-weight:600; color:var(--text-heading);">{{ $s->name }}</td>
                        <td>
                            <span class="badge" style="background:#f1f5f9; color:#475569;">
                                @if($s->frequency === 'custom')
                                    Every {{ $s->custom_interval_days }} Days
                                @else
                                    {{ ucfirst($s->frequency) }}
                                @endif
                            </span>
                            @if($s->generate_day)
                                <small class="text-muted" style="display:block;">Day: {{ $s->generate_day }}</small>
                            @endif
                        </td>
                        <td style="font-size:0.9rem;">
                            {{ $s->from_date }} to {{ $s->to_date ?? 'No End Date' }}
                        </td>
                        <td style="font-weight:500;">
                            {{ $s->next_generation_date }}
                        </td>
                        <td>
                            <span class="badge" style="
                                @if($s->status === 'active') background:var(--success-light); color:var(--success);
                                @elseif($s->status === 'paused') background:#fef3c7; color:#d97706;
                                @elseif($s->status === 'completed') background:#e0f2fe; color:#0369a1;
                                @else background:#fee2e2; color:#b91c1c;
                                @endif
                            ">
                                {{ ucfirst($s->status) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background:#f1f5f9; color:#475569;">
                                {{ $s->invoices_count }} invoices
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <div class="dropdown" style="position:relative; display:inline-block; text-align:left;">
                                <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; border:none; font-size:1.2rem; cursor:pointer;" onclick="toggleDropdown(this)">
                                    <ion-icon name="ellipsis-vertical"></ion-icon>
                                </button>
                                <div class="dropdown-menu" style="display:none; position:absolute; right:0; top:100%; background:var(--bg-card); min-width:180px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1); border-radius:8px; border:1px solid var(--border); z-index:50;">
                                    <a href="javascript:void(0)" style="display:block; padding:0.6rem 1rem; color:var(--text-main); text-decoration:none; font-size:0.85rem; border-bottom:1px solid var(--border-light);" onclick="openEditScheduleModal({{ json_encode($s) }}); closeDropdown(this)" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                        <ion-icon name="create-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Edit Schedule
                                    </a>
                                    @if($s->status === 'active')
                                    <form action="/projects/{{ $project->id }}/schedules/{{ $s->id }}/pause" method="POST" style="display:block;">
                                        @csrf
                                        <button type="submit" style="display:block; width:100%; text-align:left; border:none; background:transparent; padding:0.6rem 1rem; color:var(--text-main); font-size:0.85rem; cursor:pointer; border-bottom:1px solid var(--border-light);" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                            <ion-icon name="pause-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Pause
                                        </button>
                                    </form>
                                    <form action="/projects/{{ $project->id }}/schedules/{{ $s->id }}/run" method="POST" style="display:block;" onsubmit="return confirm('Generate invoice immediately for today and advance next generation date?');">
                                        @csrf
                                        <button type="submit" style="display:block; width:100%; text-align:left; border:none; background:transparent; padding:0.6rem 1rem; color:var(--primary); font-size:0.85rem; cursor:pointer; border-bottom:1px solid var(--border-light);" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                            <ion-icon name="play-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Run Immediate
                                        </button>
                                    </form>
                                    <form action="/projects/{{ $project->id }}/schedules/{{ $s->id }}/skip" method="POST" style="display:block;" onsubmit="return confirm('Skip next generation run?');">
                                        @csrf
                                        <button type="submit" style="display:block; width:100%; text-align:left; border:none; background:transparent; padding:0.6rem 1rem; color:#d97706; font-size:0.85rem; cursor:pointer; border-bottom:1px solid var(--border-light);" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                            <ion-icon name="play-forward-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Skip Next
                                        </button>
                                    </form>
                                    @elseif($s->status === 'paused')
                                    <form action="/projects/{{ $project->id }}/schedules/{{ $s->id }}/resume" method="POST" style="display:block;">
                                        @csrf
                                        <button type="submit" style="display:block; width:100%; text-align:left; border:none; background:transparent; padding:0.6rem 1rem; color:var(--text-main); font-size:0.85rem; cursor:pointer; border-bottom:1px solid var(--border-light);" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                            <ion-icon name="play-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Resume
                                        </button>
                                    </form>
                                    @endif
                                    
                                    @if($s->invoices_count == 0)
                                    <form action="/projects/{{ $project->id }}/schedules/{{ $s->id }}" method="POST" style="display:block;" onsubmit="return confirm('Delete this schedule definition?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="display:block; width:100%; text-align:left; border:none; background:transparent; padding:0.6rem 1rem; color:var(--danger); font-size:0.85rem; cursor:pointer;" onmouseover="this.style.background='var(--bg-sidebar-secondary)'" onmouseout="this.style.background='transparent'">
                                            <ion-icon name="trash-outline" style="vertical-align:middle; margin-right:0.5rem; font-size:1.1rem;"></ion-icon> Delete
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>


<!-- ================== TAB: PAYMENT MILESTONES ================== -->
<div id="payment-milestones" class="tab-content">
    <div class="card">
        <div class="section-title">
            <span><ion-icon name="flag-outline"></ion-icon> Payment Milestones</span>
            <button type="button" class="btn btn-primary-gradient btn-pill" onclick="openModal('createMilestoneModal')">
                <ion-icon name="add"></ion-icon> Create Milestone
            </button>
        </div>
        
        @if($paymentMilestones->isEmpty())
            <p class="text-muted" style="text-align:center; padding:2rem;">No payment milestones found.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Milestone Name</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paymentMilestones as $milestone)
                    @php 
                        $isToday = \Carbon\Carbon::parse($milestone->due_date)->isToday(); 
                    @endphp
                    <tr style="{{ $milestone->status === 'pending' && $isToday ? 'background: rgba(254, 226, 226, 0.2);' : '' }}">
                        <td style="font-weight:500;">
                            {{ $milestone->name }}
                            @if($milestone->status === 'pending' && $isToday)
                                <span class="badge" style="background:var(--danger); color:#fff; font-size:0.7rem; margin-left:8px;">Due Today</span>
                            @endif
                        </td>
                        <td style="font-weight:600;"><x-amount-display :amount="$milestone->amount" currency="$" /></td>
                        <td>{{ $milestone->due_date }}</td>
                        <td>
                            @php
                                $statusColors = [
                                    'pending' => 'background:#fffbeb; color:#b45309; border:1px solid #fde68a;',
                                    'invoiced' => 'background:#dcfce7; color:#166534; border:1px solid #bbf7d0;',
                                    'skipped' => 'background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;',
                                ];
                                $cStyle = $statusColors[$milestone->status] ?? $statusColors['pending'];
                            @endphp
                            <span style="display:inline-block; border-radius:999px; padding:0.3rem 1rem; font-size:0.75rem; font-weight:600; text-transform:uppercase; {{ $cStyle }}">{{ $milestone->status }}</span>
                        </td>
                        <td style="text-align:right;">
                            @if($milestone->status === 'pending' && $isToday)
                                <button type="button" class="btn btn-sm btn-outline" style="color:var(--primary); border-color:var(--primary); margin-right:5px;" onclick="openCreateInvoiceForMilestone({{ $milestone->id }}, {{ $milestone->amount }}, '{{ addslashes($milestone->name) }}')">
                                    Create as Invoice
                                </button>
                                <form action="/projects/{{ $project->id }}/milestones/{{ $milestone->id }}/skip" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline" style="color:var(--text-muted); border-color:var(--border);" onsubmit="return confirm('Skip this milestone?');">
                                        Skip
                                    </button>
                                </form>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<div id="documents" class="tab-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600;">Project Documents</h2>
        <button type="button" class="btn btn-primary" onclick="openModal('createDocumentModal')">
            <ion-icon name="document-attach-outline" style="vertical-align: middle;"></ion-icon> Add Document
        </button>
    </div>

    @if(count($documents) == 0)
        <div class="empty-state">
            <p>No documents uploaded yet.</p>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name / Title</th>
                    <th>Type</th>
                    <th>Source</th>
                    <th>Date</th>
                    <th>Tags</th>
                    <th>Uploaded By</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documents as $doc)
                <tr>
                    <td style="font-weight: 500;">
                        {{ $doc->name }}
                        @if($doc->visible_to_client)
                            <span class="badge" style="background:#E0F2FE; color:#0369A1; font-size:0.7rem; margin-left:0.5rem;" title="Visible to Client">Client</span>
                        @endif
                        @if($doc->change_request_id)
                            <span class="badge" style="background:#FEF3C7; color:#92400E; font-size:0.7rem; margin-left:0.5rem;" title="Linked to Change Request #{{ $doc->change_request_id }}">CR #{{ $doc->change_request_id }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="background:#F1F5F9; color:#475569;">{{ $doc->type }}</span>
                    </td>
                    <td>
                        @if($doc->source_type === 'file')
                            <span style="color:#059669;"><ion-icon name="document-outline" style="vertical-align: middle;"></ion-icon> File</span>
                        @else
                            <span style="color:#3B82F6;"><ion-icon name="link-outline" style="vertical-align: middle;"></ion-icon> Link</span>
                        @endif
                    </td>
                    <td class="text-muted">{{ $doc->document_date ? \Carbon\Carbon::parse($doc->document_date)->format('M d, Y') : '-' }}</td>
                    <td>
                        @if($doc->tags)
                            @foreach(explode(',', $doc->tags) as $tag)
                                <span class="badge" style="background:#F3F4F6; color:#374151; font-size:0.75rem; margin-right:4px;">{{ trim($tag) }}</span>
                            @endforeach
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-muted">{{ $doc->uploaded_by_name ?? 'System' }}</td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            @if($doc->source_type === 'file')
                                <a href="/documents/{{ $doc->id }}/download" class="btn btn-sm btn-outline" title="Download">
                                    <ion-icon name="download-outline"></ion-icon>
                                </a>
                            @else
                                <a href="{{ $doc->url }}" target="_blank" class="btn btn-sm btn-outline" title="{{ $doc->link_label ?: 'Open Link' }}">
                                    <ion-icon name="open-outline"></ion-icon>
                                </a>
                            @endif
                            <button class="btn btn-sm btn-outline" onclick="openEditDocumentModal({{ json_encode($doc) }})" title="Edit">
                                <ion-icon name="create-outline"></ion-icon>
                            </button>
                            <form action="/documents/{{ $doc->id }}" method="POST" style="margin: 0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline" style="color:var(--danger); border-color:var(--border);" onclick="return confirm('Delete this document?');" title="Delete">
                                    <ion-icon name="trash-outline"></ion-icon>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div></div>

<!-- ================== TAB: COST ALLOCATIONS ================== -->
<div id="cost-allocations" class="tab-content" x-data="{
        allocations: [],
        loading: false,
        filters: {
            from: '',
            to: '',
            type: ''
        },
        form: {
            open: false,
            type: 'employee',
            currency: '{{ auth()->user()->default_currency ?? 'LKR' }}',
            period_start: new Date().toISOString().slice(0,10),
            rows: [
                { employee_id: '', amount: null, notes: '' }
            ],
            server_id: null,
            cost_center_name: '',
            single_amount: null,
            single_notes: ''
        },
        addRow() {
            this.form.rows.push({ employee_id: '', amount: null, notes: '' });
        },
        removeRow(index) {
            if (this.form.rows.length > 1) {
                this.form.rows.splice(index, 1);
            }
        },
        fetchAllocations() {
            this.loading = true;
            let url = `/api/cost-allocations?project_id={{ $project->id }}`;
            if (this.filters.from && this.filters.to) {
                url += `&from=${this.filters.from}&to=${this.filters.to}`;
            }
            if (this.filters.type) {
                url += `&type=${this.filters.type}`;
            }
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    this.allocations = data.data || [];
                    this.loading = false;
                });
        },
        resetFilters() {
            this.filters.from = '';
            this.filters.to = '';
            this.filters.type = '';
            this.fetchAllocations();
        },
        get totalCost() {
            return this.allocations.reduce((sum, a) => sum + parseFloat(a.amount || 0), 0);
        },
        get employeeCost() {
            return this.allocations.filter(a => a.type === 'employee').reduce((sum, a) => sum + parseFloat(a.amount || 0), 0);
        },
        get serverCost() {
            return this.allocations.filter(a => a.type === 'server').reduce((sum, a) => sum + parseFloat(a.amount || 0), 0);
        },
        get otherCost() {
            return this.allocations.filter(a => a.type === 'other').reduce((sum, a) => sum + parseFloat(a.amount || 0), 0);
        },
        submitForm() {
            let payload = {
                project_id: {{ $project->id }},
                type: this.form.type,
                currency: this.form.currency,
                period_start: this.form.period_start,
            };

            if (this.form.type === 'employee') {
                const validRows = this.form.rows.filter(r => r.employee_id && r.amount);
                if (validRows.length === 0) {
                    alert('Please select at least one employee and specify their amount.');
                    return;
                }
                payload.allocations = validRows.map(r => ({
                    employee_id: r.employee_id,
                    amount: r.amount,
                    notes: r.notes
                }));
            } else if (this.form.type === 'server') {
                if (!this.form.server_id || !this.form.single_amount) {
                    alert('Please select a server and specify the amount.');
                    return;
                }
                payload.server_id = this.form.server_id;
                payload.amount = this.form.single_amount;
                payload.notes = this.form.single_notes;
            } else {
                if (!this.form.cost_center_name || !this.form.single_amount) {
                    alert('Please specify a cost center name and amount.');
                    return;
                }
                payload.cost_center_name = this.form.cost_center_name;
                payload.amount = this.form.single_amount;
                payload.notes = this.form.single_notes;
            }

            fetch('/api/cost-allocations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload),
            }).then(response => {
                if(response.ok) {
                    this.form.open = false;
                    this.form.rows = [{ employee_id: '', amount: null, notes: '' }];
                    this.form.single_amount = null;
                    this.form.cost_center_name = '';
                    this.form.single_notes = '';
                    this.fetchAllocations();
                } else {
                    alert('Failed to save cost allocation.');
                }
            });
        },
        deleteAllocation(id) {
            if(!confirm('Are you sure you want to delete this allocation?')) return;
            fetch(`/api/cost-allocations/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }).then(() => this.fetchAllocations());
        }
    }" x-init="fetchAllocations()" @cost-allocation-added.window="fetchAllocations()">
    
    {{-- Header --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin: 0 0 0.25rem 0;">Cost Allocation Report</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Track employee, server, and other operational cost facts for this project.</p>
        </div>
        <button @click="form.open = !form.open" class="btn btn-primary btn-pill" style="display: flex; align-items: center; gap: 0.4rem;">
            <ion-icon name="add-outline"></ion-icon> Log Cost Allocation
        </button>
    </div>

    {{-- Filter Bar --}}
    <div style="background: var(--bg-card); padding: 1rem 1.25rem; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        <div style="flex: 1; min-width: 150px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">From Date</label>
            <input type="date" x-model="filters.from" class="form-control" style="padding: 0.45rem 0.6rem; font-size: 0.85rem;">
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">To Date</label>
            <input type="date" x-model="filters.to" class="form-control" style="padding: 0.45rem 0.6rem; font-size: 0.85rem;">
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Type</label>
            <select x-model="filters.type" class="form-control" style="padding: 0.45rem 0.6rem; font-size: 0.85rem;">
                <option value="">All Types</option>
                <option value="employee">Employee</option>
                <option value="server">Server</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
            <button type="button" @click="fetchAllocations()" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">Filter Report</button>
            <button type="button" @click="resetFilters()" class="btn btn-outline" style="padding: 0.45rem 0.8rem; font-size: 0.85rem;">Reset</button>
        </div>
    </div>

    {{-- Report KPI Summary Cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 0.4rem;">Total Cost Allocated</div>
            <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-heading);">
                LKR <span x-text="totalCost.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                <span x-text="allocations.length"></span> cost record(s)
            </div>
        </div>

        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 0.4rem;">Employee Costs</div>
            <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);">
                LKR <span x-text="employeeCost.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                <span x-text="totalCost > 0 ? ((employeeCost / totalCost) * 100).toFixed(1) : '0.0'"></span>% of total cost
            </div>
        </div>

        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 0.4rem;">Server Costs</div>
            <div style="font-size: 1.4rem; font-weight: 700; color: #d97706;">
                LKR <span x-text="serverCost.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                <span x-text="totalCost > 0 ? ((serverCost / totalCost) * 100).toFixed(1) : '0.0'"></span>% of total cost
            </div>
        </div>

        <div style="background: var(--bg-card); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border);">
            <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 0.4rem;">Other Costs</div>
            <div style="font-size: 1.4rem; font-weight: 700; color: #475569;">
                LKR <span x-text="otherCost.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                <span x-text="totalCost > 0 ? ((otherCost / totalCost) * 100).toFixed(1) : '0.0'"></span>% of total cost
            </div>
        </div>
    </div>

    <!-- Inline Form -->
    <div x-show="form.open" x-transition class="glass-card" style="display: none; border: 1px solid var(--primary); background: var(--bg-card); margin-bottom: 2rem; padding: 1.5rem; border-radius: 12px;">
        <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--text-heading);">Log Cost Allocations</h3>
        
        <div class="form-row" style="margin-bottom: 1.25rem;">
            <div class="form-col">
                <label class="form-label">Type</label>
                <select x-model="form.type" class="form-control">
                    <option value="employee">Employee(s)</option>
                    <option value="server">Server</option>
                    <option value="other">Other Cost Center</option>
                </select>
            </div>
            <div class="form-col">
                <label class="form-label">Date / Period</label>
                <input type="date" x-model="form.period_start" class="form-control">
            </div>
            <div class="form-col" style="max-width: 150px;">
                <label class="form-label">Currency</label>
                <select x-model="form.currency" class="form-control">
                    <option value="LKR">LKR</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
        </div>

        <!-- Dynamic Rows for Employee Allocation -->
        <div x-show="form.type === 'employee'" style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight:600; margin-bottom: 0.75rem;">Employee Allocations (Specify Employee, Amount & Note per row)</label>
            
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <template x-for="(row, index) in form.rows" :key="index">
                    <div style="display: flex; gap: 0.75rem; align-items: flex-end; background: var(--bg-page); padding: 0.85rem; border-radius: 8px; border: 1px solid var(--border);">
                        <div style="flex: 2; position: relative;" x-data="{ open: false, search: '' }" @click.away="open = false">
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Employee</label>
                            
                            {{-- Selected Display Button --}}
                            <button type="button" @click="open = !open" class="form-control" style="display: flex; align-items: center; justify-content: space-between; padding: 0.45rem 0.75rem; background: var(--bg-card); cursor: pointer; text-align: left; height: 38px;">
                                <template x-if="row.employee_id">
                                    <span style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
                                        @foreach(\App\Models\Employee::where('status', 'active')->orderBy('full_name')->get() as $emp)
                                            <span x-show="row.employee_id == {{ $emp->id }}" style="display: flex; align-items: center; gap: 0.5rem;">
                                                <img src="{{ $emp->profile_picture_url ?? 'https://ui-avatars.com/api/?name='.urlencode($emp->full_name) }}" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;">
                                                <span style="font-weight: 500; font-size: 0.85rem; color: var(--text-heading);">{{ $emp->full_name }}</span>
                                                <small style="color: var(--text-muted); font-size: 0.75rem;">({{ $emp->job_position ?? 'Employee' }})</small>
                                            </span>
                                        @endforeach
                                    </span>
                                </template>
                                <template x-if="!row.employee_id">
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Select employee...</span>
                                </template>
                                <ion-icon name="chevron-down-outline" style="color: var(--text-muted); flex-shrink: 0;"></ion-icon>
                            </button>

                            {{-- Dropdown Panel with Search --}}
                            <div x-show="open" x-transition style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 100; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow-card); margin-top: 0.25rem; max-height: 220px; overflow-y: auto;">
                                <div style="padding: 0.5rem; border-bottom: 1px solid var(--border-light); position: sticky; top: 0; background: var(--bg-card);">
                                    <input type="text" x-model="search" class="form-control" placeholder="Search by name or position..." style="padding: 0.35rem 0.6rem; font-size: 0.8rem;" @click.stop>
                                </div>
                                <ul style="list-style: none; margin: 0; padding: 0;">
                                    @foreach(\App\Models\Employee::where('status', 'active')->orderBy('full_name')->get() as $emp)
                                    <li x-show="'{{ strtolower($emp->full_name.' '.$emp->job_position) }}'.includes(search.toLowerCase())"
                                        @click="row.employee_id = {{ $emp->id }}; open = false;"
                                        style="padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; gap: 0.6rem; cursor: pointer; transition: background 0.2s;"
                                        onmouseover="this.style.background='var(--primary-light)'"
                                        onmouseout="this.style.background='transparent'">
                                        <img src="{{ $emp->profile_picture_url ?? 'https://ui-avatars.com/api/?name='.urlencode($emp->full_name) }}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                                        <div style="overflow: hidden;">
                                            <div style="font-weight: 500; color: var(--text-heading); font-size: 0.85rem;">{{ $emp->full_name }}</div>
                                            <small style="color: var(--text-muted); font-size: 0.75rem;">{{ $emp->job_position ?? 'Employee' }}</small>
                                        </div>
                                    </li>
                                    @endforeach
                                    @if(\App\Models\Employee::where('status', 'active')->count() === 0)
                                    <li style="padding: 0.75rem; text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                                        No active employees found.
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Amount</label>
                            <div class="amount-input-wrapper" style="position: relative;">
                                <input type="text" class="form-control amount-display-input" placeholder="0.00" style="padding: 0.55rem; height: 38px;" @input="formatAmountInput($event.target); row.amount = $event.target.parentElement.querySelector('.amount-hidden').value" @blur="formatAmountBlur($event.target); row.amount = $event.target.parentElement.querySelector('.amount-hidden').value">
                                <input type="hidden" class="amount-hidden" :value="row.amount">
                            </div>
                        </div>
                        <div style="flex: 2;">
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Note / Description</label>
                            <input type="text" x-model="row.notes" class="form-control" placeholder="E.g. July Payroll / Frontend Work" style="padding: 0.55rem;">
                        </div>
                        <div>
                            <button type="button" @click="removeRow(index)" class="btn btn-outline" style="color: var(--danger); border-color: var(--border); padding: 0.55rem 0.75rem;" :disabled="form.rows.length === 1">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="addRow()" class="btn btn-outline" style="margin-top: 0.75rem; font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                <ion-icon name="add-outline"></ion-icon> Add Another Employee Row
            </button>
        </div>

        <!-- Single Row Form for Server / Other -->
        <div x-show="form.type !== 'employee'" class="form-row" style="margin-bottom: 1.5rem;">
            <div class="form-col" x-show="form.type === 'server'" @server-selected.window="form.server_id = $event.detail">
                <label class="form-label">Server</label>
                <x-server-selector />
            </div>
            <div class="form-col" x-show="form.type === 'other'">
                <label class="form-label">Cost Center Name</label>
                <input type="text" x-model="form.cost_center_name" class="form-control" placeholder="E.g. Marketing Tool">
            </div>
            <div class="form-col">
                <label class="form-label">Amount</label>
                <div class="amount-input-wrapper" style="position: relative;">
                    <input type="text" class="form-control amount-display-input" placeholder="0.00" @input="formatAmountInput($event.target); form.single_amount = $event.target.parentElement.querySelector('.amount-hidden').value" @blur="formatAmountBlur($event.target); form.single_amount = $event.target.parentElement.querySelector('.amount-hidden').value">
                    <input type="hidden" class="amount-hidden" :value="form.single_amount">
                </div>
            </div>
            <div class="form-col">
                <label class="form-label">Notes</label>
                <input type="text" x-model="form.single_notes" class="form-control" placeholder="Optional notes">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-outline" @click="form.open = false">Cancel</button>
            <button type="button" @click="submitForm()" class="btn btn-primary">Save Allocation(s)</button>
        </div>
    </div>

    <!-- Data Table -->
    <div style="background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: var(--bg-page); border-bottom: 1px solid var(--border);">
                <tr>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Date</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Type</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Description / Entity</th>
                    <th style="padding: 0.85rem 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted);">Notes</th>
                    <th style="padding: 0.85rem 1rem; text-align: right; font-size: 0.8rem; color: var(--text-muted);">Amount</th>
                    <th style="padding: 0.85rem 1rem; text-align: right; font-size: 0.8rem; color: var(--text-muted);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="item in allocations" :key="item.id">
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <td style="padding: 0.85rem 1rem; font-size: 0.85rem; color: var(--text-main);" x-text="item.period_start"></td>
                        <td style="padding: 0.85rem 1rem;">
                            <template x-if="item.type === 'employee'">
                                <span style="background: #e0e7ff; color: #3730a3; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Employee</span>
                            </template>
                            <template x-if="item.type === 'server'">
                                <span style="background: #fef3c7; color: #92400e; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Server</span>
                            </template>
                            <template x-if="item.type === 'other'">
                                <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Other</span>
                            </template>
                        </td>
                        <td style="padding: 0.85rem 1rem; font-size: 0.85rem;">
                            <template x-if="item.type === 'employee'">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <img :src="item.employee && item.employee.profile_picture_url ? item.employee.profile_picture_url : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.employee ? item.employee.full_name : 'Emp')" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <div style="font-weight: 500; color: var(--text-heading);" x-text="item.employee ? item.employee.full_name : 'Unknown Employee'"></div>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;" x-text="item.employee ? item.employee.job_position : ''"></small>
                                    </div>
                                </div>
                            </template>
                            <template x-if="item.type === 'server'">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <ion-icon name="server-outline" style="color: var(--primary); font-size: 1.1rem;"></ion-icon>
                                    <div>
                                        <div style="font-weight: 500; color: var(--text-heading);" x-text="item.server ? item.server.name : 'Unknown Server'"></div>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;" x-text="item.server ? item.server.provider : ''"></small>
                                    </div>
                                </div>
                            </template>
                            <template x-if="item.type === 'other'">
                                <span style="font-weight: 500; color: var(--text-heading);" x-text="item.cost_center_name"></span>
                            </template>
                        </td>
                        <td style="padding: 0.85rem 1rem; color: var(--text-muted); font-size: 0.85rem;" x-text="item.notes || '-'"></td>
                        <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 600; color: var(--text-heading); font-size: 0.85rem;">
                            <span x-text="item.currency"></span>
                            <span x-text="parseFloat(item.amount).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                        </td>
                        <td style="padding: 0.85rem 1rem; text-align: right;">
                            <button @click="deleteAllocation(item.id)" class="action-btn text-danger" title="Delete">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="allocations.length === 0">
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                        <ion-icon name="calculator-outline" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;"></ion-icon><br>
                        No cost allocations found for this project.
                    </td>
                </tr>
            </tbody>
            <tfoot x-show="allocations.length > 0" style="background: var(--bg-page); border-top: 2px solid var(--border); font-weight: 700;">
                <tr>
                    <td colspan="4" style="padding: 0.85rem 1rem; text-align: right; color: var(--text-heading);">Total Project Allocated Cost:</td>
                    <td style="padding: 0.85rem 1rem; text-align: right; color: var(--primary); font-size: 0.95rem;">
                        LKR <span x-text="totalCost.toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection

@section('modals')
<!-- Create Document Modal -->
<div class="modal-backdrop" id="createDocumentModal">
    <div class="modal-card">
        <div class="modal-header">
            <h2 class="modal-title">Add Document</h2>
            <button type="button" class="btn-close" onclick="closeModal('createDocumentModal')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <form action="/projects/{{ $project->id }}/documents" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name / Title <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Master Services Agreement">
                </div>
                <div class="form-group">
                    <label class="form-label">Type <span style="color:var(--danger)">*</span></label>
                    <select name="type" class="form-control" required>
                        <option value="Agreement">Agreement</option>
                        <option value="Change Request">Change Request</option>
                        <option value="Proposal">Proposal</option>
                        <option value="NDA">NDA</option>
                        <option value="Invoice-related">Invoice-related</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Source Type <span style="color:var(--danger)">*</span></label>
                    <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="source_type" value="file" checked onchange="document.getElementById('docFileWrapper').style.display='block'; document.getElementById('docLinkWrapper').style.display='none';"> File Upload
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="source_type" value="link" onchange="document.getElementById('docFileWrapper').style.display='none'; document.getElementById('docLinkWrapper').style.display='block';"> External Link
                        </label>
                    </div>
                </div>
                
                <div id="docFileWrapper" class="form-group">
                    <label class="form-label">Upload File <span style="color:var(--danger)">*</span></label>
                    <input type="file" name="file" class="form-control">
                </div>
                
                <div id="docLinkWrapper" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">URL <span style="color:var(--danger)">*</span></label>
                        <input type="url" name="url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link Label (Optional)</label>
                        <input type="text" name="link_label" class="form-control" placeholder="e.g. View on Google Drive">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Linked Change Request (Optional)</label>
                    <select name="change_request_id" class="form-control">
                        <option value="">-- None --</option>
                        @foreach($change_requests as $cr)
                            <option value="{{ $cr->id }}">{{ \Illuminate\Support\Str::limit($cr->description, 50) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Document Date</label>
                    <input type="date" name="document_date" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Tags (comma separated)</label>
                    <input type="text" name="tags" class="form-control" placeholder="e.g. legal, signed">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="visible_to_client" value="1">
                        Visible to Client via Share Link
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createDocumentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Document</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Document Modal -->
<div class="modal-backdrop" id="editDocumentModal">
    <div class="modal-card">
        <div class="modal-header">
            <h2 class="modal-title">Edit Document</h2>
            <button type="button" class="btn-close" onclick="closeModal('editDocumentModal')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <form id="editDocumentForm" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name / Title <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="name" id="edit_doc_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Type <span style="color:var(--danger)">*</span></label>
                    <select name="type" id="edit_doc_type" class="form-control" required>
                        <option value="Agreement">Agreement</option>
                        <option value="Change Request">Change Request</option>
                        <option value="Proposal">Proposal</option>
                        <option value="NDA">NDA</option>
                        <option value="Invoice-related">Invoice-related</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Source Type <span style="color:var(--danger)">*</span></label>
                    <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="source_type" id="edit_doc_source_file" value="file" onchange="document.getElementById('editDocFileWrapper').style.display='block'; document.getElementById('editDocLinkWrapper').style.display='none';"> File Upload
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="source_type" id="edit_doc_source_link" value="link" onchange="document.getElementById('editDocFileWrapper').style.display='none'; document.getElementById('editDocLinkWrapper').style.display='block';"> External Link
                        </label>
                    </div>
                </div>
                
                <div id="editDocFileWrapper" class="form-group">
                    <label class="form-label">Upload New File (Optional)</label>
                    <input type="file" name="file" class="form-control">
                    <small class="text-muted" style="display:block; margin-top:0.25rem;">Leave blank to keep existing file.</small>
                </div>
                
                <div id="editDocLinkWrapper" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">URL <span style="color:var(--danger)">*</span></label>
                        <input type="url" name="url" id="edit_doc_url" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link Label (Optional)</label>
                        <input type="text" name="link_label" id="edit_doc_link_label" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Linked Change Request (Optional)</label>
                    <select name="change_request_id" id="edit_doc_cr" class="form-control">
                        <option value="">-- None --</option>
                        @foreach($change_requests as $cr)
                            <option value="{{ $cr->id }}">{{ \Illuminate\Support\Str::limit($cr->description, 50) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Document Date</label>
                    <input type="date" name="document_date" id="edit_doc_date" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Tags (comma separated)</label>
                    <input type="text" name="tags" id="edit_doc_tags" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="edit_doc_notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="visible_to_client" id="edit_doc_visible" value="1">
                        Visible to Client via Share Link
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editDocumentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Document</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Invoice -->
<div class="modal-backdrop" id="createInvoiceModal">
    <div class="modal-card" style="max-width: 900px;">
        <div class="modal-header"><h2 class="modal-title">Create Invoice</h2><button type="button" class="btn-close" onclick="closeModal('createInvoiceModal')"><ion-icon name="close-outline"></ion-icon></button></div>
        <form action="/invoices" method="POST">
            @csrf
            <input type="hidden" name="project_id" value="{{ $project->id }}">
            <input type="hidden" name="payment_milestone_id" id="invoice_payment_milestone_id" value="">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col" style="flex:2;">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-control" required>
                            @foreach($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                        </select>
                    </div>
                    <div class="form-col" style="flex:1;">
                        <label class="form-label">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-col" style="flex:1;">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-col">
                        <label class="form-label">Applicable Invoice Tax Rate (VAT)</label>
                        <x-tax-selector name="tax_type_id" id="invoice_overall_tax" category="vat" appliesTo="invoice_item" selected="" onchange="calculateInvoiceTotals" />
                    </div>
                </div>

                
                <h3 class="section-title" style="margin-top:2rem; font-size:1.1rem; border-bottom:1px solid var(--border); padding-bottom:0.5rem;">Line Items</h3>
                <table class="mini-table" style="margin-bottom: 1rem;">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Description</th>
                            <th style="width: 25%;">Item Type</th>
                            <th style="width: 10%;">Qty</th>
                            <th style="width: 15%;">Unit Price</th>
                            <th style="width: 10%; text-align:right;">Line Total</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="invoice-items-container">
                        <tr class="invoice-item-row">
                            <td><input type="text" name="item_description[]" class="form-control" style="padding:0.4rem;" placeholder="Item description" required></td>
                            <td>
                                <select name="item_type_id[]" class="form-control" style="padding:0.4rem;" required>
                                    @foreach($invoiceTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="item_qty[]" class="form-control qty-input" style="padding:0.4rem;" value="1" required></td>
                            <td><x-amount-input name="item_price[]" class="form-control" style="padding:0.4rem;" required="true" /></td>
                            <td style="vertical-align:middle; width: 120px;"><x-amount-input name="ignore_total[]" class="form-control line-total-input" style="padding:0.4rem; text-align:right; font-weight:600; background:var(--bg-alt);" readonly="true" /></td>
                            <td style="vertical-align:middle; text-align:center;">
                                <button type="button" class="btn btn-outline" style="border:none; color:var(--danger); padding:0.2rem;" onclick="removeInvoiceRow(this)">
                                    <ion-icon name="trash-outline"></ion-icon>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-top:1.5rem; background:var(--bg-alt); padding:1.2rem; border-radius:12px; border:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary-gradient btn-pill" onclick="addInvoiceRow()"><ion-icon name="add-outline"></ion-icon> Add Line Item</button>
                    
                    <div style="display:flex; flex-direction:column; gap:0.5rem; min-width:320px; text-align:right;">
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.9rem; color:var(--text-muted);">
                            <span>Line Items Subtotal:</span>
                            <strong id="invoice-subtotal-display" style="color:var(--text-heading);">0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.9rem; color:var(--text-muted);">
                            <span>VAT / Tax Amount:</span>
                            <strong id="invoice-tax-display" style="color:var(--primary);">0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.9rem; color:#dc2626;">
                            <span>Discount (-):</span>
                            <div style="display:flex; gap:0.35rem; align-items:center;">
                                <select name="discount_type" id="invoice_discount_type" class="form-control" style="padding:0.25rem 0.4rem; font-size:0.8rem; width:75px;" onchange="calculateInvoiceTotals()">
                                    <option value="fixed">Fixed</option>
                                    <option value="percentage">%</option>
                                </select>
                                <div style="width: 110px;">
                                    <x-amount-input name="discount_value" id="invoice_discount_value" class="form-control" style="padding:0.3rem 0.5rem; font-size:0.88rem; text-align:right; color:#dc2626;" placeholder="0.00" onkeyup="calculateInvoiceTotals()" onblur="calculateInvoiceTotals()" />
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:1.15rem; font-weight:700; color:var(--text-heading); border-top:1px solid var(--border); padding-top:0.6rem; margin-top:0.2rem;">
                            <span>Grand Total:</span>
                            <div style="width: 160px;">
                                <x-amount-input name="amount" id="invoice-grand-total" class="form-control" style="font-size:1.1rem; font-weight:700; text-align:right; color:var(--primary); background:var(--bg-card);" readonly="true" />
                            </div>
                        </div>
                    </div>
                </div>

                
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary-gradient">Save Invoice</button></div>
        </form>
    </div>

</div>

<!-- Record Payment -->
<div class="modal-backdrop" id="createPaymentModal">
    <div class="modal-card">
        <div class="modal-header"><h2 class="modal-title">Record Payment</h2><button type="button" class="btn-close" onclick="closeModal('createPaymentModal')"><ion-icon name="close-outline"></ion-icon></button></div>
        <form action="/projects/{{ $project->id }}/payments" method="POST" id="recordPaymentForm" onsubmit="return validatePayment()">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem; background: var(--bg-alt); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                    <label class="form-label" style="font-weight: 600; margin-bottom: 0.75rem;">Allocate Payment to Invoices (Optional)</label>
                    <p class="text-muted" style="font-size: 0.85rem; margin-top: -0.5rem; margin-bottom: 1rem;">Specify how much of the total payment should be applied to specific invoices. Leave blank for a general payment.</p>
                    
                    @php $activeInvoices = $invoices->whereNotIn('status', ['paid', 'cancelled']); @endphp
                    @if($activeInvoices->isEmpty())
                        <div style="font-size: 0.9rem; color: var(--text-muted);">No active invoices found to allocate.</div>
                    @else
                        @foreach($activeInvoices as $inv)
                            @php
                                $invCollected = DB::table('payment_allocations')->where('invoice_id', $inv->id)->sum('amount');
                                $invBalance = max(0, $inv->amount - $invCollected);
                            @endphp
                            <div class="allocation-row" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px dashed var(--border);">
                                <div style="flex: 2;">
                                    <div style="font-size: 0.95rem; font-weight: 500;">{{ $inv->invoice_no }} <span style="font-size:0.75rem; color:var(--text-muted); background:var(--bg-card); padding:0.1rem 0.4rem; border-radius:4px; margin-left:0.5rem;">{{ ucfirst($inv->status) }}</span></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">Balance: {{ $inv->currency ?? ($project->currency ?? 'LKR') }} {{ number_format($invBalance, 2) }} <span style="margin: 0 0.25rem;">|</span> Total: {{ number_format($inv->amount, 2) }}</div>
                                </div>
                                <div style="flex: 1; min-width: 120px;">
                                    <input type="hidden" name="alloc_invoice_id[]" value="{{ $inv->id }}">
                                    <x-amount-input name="alloc_amount[]" placeholder="0.00" class="form-control alloc-amt-input" />
                                    <button type="button" class="btn btn-sm btn-outline" style="padding: 0.15rem 0.4rem; font-size: 0.7rem; margin-top: 0.25rem; width: 100%; border: 1px dashed var(--border);" onclick="fillMaxAllocation(this, {{ $invBalance }})">Fill Balance</button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="form-row">
                    <div class="form-col"><label class="form-label">Total Amount</label><x-amount-input name="total_amount" required="true" /></div>
                    <div class="form-col"><label class="form-label">Currency</label><input type="text" name="currency" class="form-control" value="{{ $project->currency ?? ($baseCurrency ?? 'LKR') }}" required></div>
                </div>
                
                <x-payment-modes />
                
                <div id="payment-validation-msg" style="margin-top: 1rem; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; display: none; transition: transform 0.2s ease;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary-gradient" id="savePaymentBtn">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- New Change Request -->
<div class="modal-backdrop" id="createCRModal">
    <div class="modal-card">
        <div class="modal-header"><h2 class="modal-title">New Change Request</h2><button type="button" class="btn-close" onclick="closeModal('createCRModal')"><ion-icon name="close-outline"></ion-icon></button></div>
        <form action="/projects/{{ $project->id }}/change-requests" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" required></textarea></div>
                <div class="form-row">
                    <div class="form-col form-group"><label class="form-label">Amount</label><x-amount-input name="amount" required="true" /></div>
                    <div class="form-col"><label class="form-label">Currency</label><input type="text" name="currency" class="form-control" value="{{ $project->currency ?? ($baseCurrency ?? 'LKR') }}"></div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control"><option value="pending">Pending</option><option value="approved">Approved</option></select>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:500;">
                        <input type="checkbox" name="auto_create_invoice" value="1" checked>
                        <span>Auto-create invoice if approved</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary-gradient">Submit Request</button></div>
        </form>
    </div>
</div>

<!-- Add Note -->
<div class="modal-backdrop" id="createNoteModal">
    <div class="modal-card">
        <div class="modal-header"><h2 class="modal-title">Add Note</h2><button type="button" class="btn-close" onclick="closeModal('createNoteModal')"><ion-icon name="close-outline"></ion-icon></button></div>
        <form action="/projects/{{ $project->id }}/notes" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Content</label><textarea name="content" class="form-control" rows="4" required></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary-gradient">Save Note</button></div>
        </form>
    </div>
</div>

<!-- Log Interaction -->
<div class="modal-backdrop" id="createInteractionModal">
    <div class="modal-card">
        <div class="modal-header"><h2 class="modal-title">Log Interaction</h2><button type="button" class="btn-close" onclick="closeModal('createInteractionModal')"><ion-icon name="close-outline"></ion-icon></button></div>
        <form action="/projects/{{ $project->id }}/interactions" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control"><option value="call">Call</option><option value="meeting">Meeting</option><option value="email">Email</option></select>
                    </div>
                    <div class="form-col"><label class="form-label">Date & Time</label><input type="datetime-local" name="interaction_date" class="form-control" required></div>
                </div>
                <div class="form-group" style="margin-top:1rem;"><label class="form-label">Summary</label><textarea name="summary" class="form-control" required></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary-gradient">Log Interaction</button></div>
        </form>
    </div>
</div>

<!-- View Invoice Modals -->
@foreach($invoices as $inv)
<div class="modal-backdrop" id="viewInvoiceModal_{{ $inv->id }}">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h2 class="modal-title">Invoice Details: {{ $inv->invoice_no }}</h2>
            <button type="button" class="btn-close" onclick="closeModal('viewInvoiceModal_{{ $inv->id }}')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div style="display:flex; justify-content:space-between; margin-bottom:1rem; padding: 0.75rem 1rem; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                <div><strong>Status:</strong> <span class="badge badge-{{ $inv->status }}">{{ ucfirst($inv->status) }}</span></div>
                <div><strong>Issue Date:</strong> {{ $inv->issue_date ?? 'N/A' }}</div>
                <div><strong>Due Date:</strong> {{ $inv->due_date ?? 'N/A' }}</div>
            </div>
            <h3 class="section-title" style="font-size:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem; margin-top: 1rem;">Line Items</h3>
            <table class="data-table">
                <thead><tr><th>Description</th><th>Qty</th><th style="text-align:right;">Unit Price</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody>
                    @foreach($invoiceItems->where('invoice_id', $inv->id) as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->qty }}</td>
                        <td style="text-align:right;">{{ $inv->currency ?? 'LKR' }} {{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align:right; font-weight:500;">{{ $inv->currency ?? 'LKR' }} {{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Invoice Total Breakdown -->
            @php
                $invSubtotal = (float)str_replace(',', '', (string)($inv->subtotal > 0 ? $inv->subtotal : $invoiceItems->where('invoice_id', $inv->id)->sum('total')));
                $invDiscountValue = (float)str_replace(',', '', (string)($inv->discount_value ?? 0));
                $invDiscountAmount = (float)str_replace(',', '', (string)($inv->discount_amount ?? 0));
                $invTaxRate = (float)str_replace(',', '', (string)($inv->tax_rate ?? 0));
                $invTaxAmount = (float)str_replace(',', '', (string)($inv->tax_amount ?? 0));
                $invGrandTotal = (float)str_replace(',', '', (string)($inv->grand_total > 0 ? $inv->grand_total : $inv->amount));
            @endphp
            <div style="display:flex; flex-direction:column; gap:0.4rem; width:320px; margin-left:auto; margin-top:1.5rem; background:var(--bg-card); padding:1rem; border-radius:10px; border:1px solid var(--border); text-align:right;">
                <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:var(--text-muted);">
                    <span>Line Items Subtotal:</span>
                    <strong style="color:var(--text-heading);">{{ $inv->currency ?? 'LKR' }} {{ number_format($invSubtotal, 2) }}</strong>
                </div>

                @if($invDiscountAmount > 0)
                <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:#dc2626;">
                    <span>Discount @if(($inv->discount_type ?? 'fixed') === 'percentage') ({{ number_format($invDiscountValue, 2) }}%) @endif:</span>
                    <strong>- {{ $inv->currency ?? 'LKR' }} {{ number_format($invDiscountAmount, 2) }}</strong>
                </div>
                @endif

                @if($invTaxAmount > 0 || $invTaxRate > 0)
                <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:var(--text-muted);">
                    <span>VAT / Tax ({{ number_format($invTaxRate, 2) }}%):</span>
                    <strong style="color:var(--primary);">+ {{ $inv->currency ?? 'LKR' }} {{ number_format($invTaxAmount, 2) }}</strong>
                </div>
                @endif

                <div style="display:flex; justify-content:space-between; font-size:1.15rem; font-weight:700; color:var(--text-heading); border-top:1px solid var(--border); padding-top:0.6rem; margin-top:0.3rem;">
                    <span>Grand Total:</span>
                    <span style="color:var(--primary);">{{ $inv->currency ?? 'LKR' }} {{ number_format($invGrandTotal, 2) }}</span>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- Change Status Modal -->
<div class="modal-backdrop" id="changeStatusModal_{{ $inv->id }}">
    <div class="modal-card" style="max-width:450px;">
        <div class="modal-header">
            <h2 class="modal-title">Change Invoice Status</h2>
            <button type="button" class="btn-close" onclick="closeModal('changeStatusModal_{{ $inv->id }}')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <form action="/invoices/{{ $inv->id }}/status" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 1rem;">Update the status for invoice <strong>{{ $inv->invoice_no }}</strong>.</p>
                <div class="form-group">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-control" required style="padding:0.75rem; font-size:1rem;">
                        <option value="draft" {{ $inv->status == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="sent" {{ $inv->status == 'sent' ? 'selected' : '' }}>Sent</option>
                        <option value="paid" {{ $inv->status == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="overdue" {{ $inv->status == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="cancelled" {{ $inv->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary-gradient">Confirm Change</button></div>
        </form>
    </div>
</div>
@endforeach

<!-- View Payment Modals -->
@foreach($payments as $pay)
<div class="modal-backdrop" id="viewPaymentModal_{{ $pay->id }}">
    <div class="modal-card" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title">Payment Details</h2>
            <button type="button" class="btn-close" onclick="closeModal('viewPaymentModal_{{ $pay->id }}')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div style="display:flex; justify-content:space-between; margin-bottom:1rem;">
                <div><strong>Payment Date:</strong> {{ $pay->payment_date }}</div>
                <div><strong>Currency:</strong> {{ $pay->currency }}</div>
                <div><strong>Total Amount:</strong> <span style="color:var(--success); font-weight:600;">${{ number_format($pay->total_amount, 2) }}</span></div>
            </div>
            
            <div style="margin-bottom:1.5rem; padding: 0.75rem; background: var(--bg-alt); border-radius: 6px; border: 1px solid var(--border);">
                <strong style="display:block; margin-bottom:0.5rem; font-size:0.85rem; color:var(--text-muted);">Allocated To:</strong> 
                @php
                    $detailedAllocs = DB::table('payment_allocations')
                        ->join('invoices', 'payment_allocations.invoice_id', '=', 'invoices.id')
                        ->where('payment_allocations.payment_id', $pay->id)
                        ->select('invoices.invoice_no', 'payment_allocations.amount')
                        ->get();
                @endphp
                @if($detailedAllocs->isEmpty())
                    <span class="text-muted" style="font-size:0.9rem;">General Project Payment (Unallocated)</span>
                @else
                    @foreach($detailedAllocs as $alloc)
                        <span class="badge" style="background:#fff; border:1px solid #e2e8f0; color:#334155; margin-right:0.5rem; padding:0.3rem 0.6rem; font-size:0.85rem;">
                            {{ $alloc->invoice_no }} <span style="color:var(--success); margin-left:0.25rem;">({{ $pay->currency }} {{ number_format($alloc->amount, 2) }})</span>
                        </span>
                    @endforeach
                @endif
            </div>
            <h3 class="section-title" style="font-size:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">Payment Modes</h3>
            <table class="data-table">
                <thead><tr><th>Mode</th><th>Amount</th><th>Details</th><th>Notes</th></tr></thead>
                <tbody>
                    @foreach($paymentModes->where('payment_id', $pay->id) as $mode)
                    <tr>
                        <td><span class="badge" style="background:#f1f5f9; color:#475569;">{{ ucfirst($mode->mode) }}</span></td>
                        <td style="font-weight:500;">${{ number_format($mode->amount, 2) }}</td>
                        <td>
                            @if($mode->mode === 'cheque')
                                <small>Bank: {{ $mode->bank_name ?? '-' }} <br>
                                Cheque: {{ $mode->cheque_no ?? '-' }} <br>
                                Date: {{ $mode->cheque_date ?? '-' }}</small>
                            @elseif($mode->mode === 'bank_transfer')
                                <small>Ref: {{ $mode->reference_no ?? '-' }}</small>
                            @elseif($mode->mode === 'card')
                                <small>Auth: {{ $mode->reference_no ?? '-' }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $mode->notes ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endforeach

<script>
// Dropdown Logic
function toggleDropdown(btn) {
    // close all other dropdowns
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if (menu !== btn.nextElementSibling) menu.style.display = 'none';
    });
    const menu = btn.nextElementSibling;
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

function closeDropdown(element) {
    element.closest('.dropdown-menu').style.display = 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.style.display = 'none');
    }
});

function switchTab(tabId, element) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    // Deactivate all links
    document.querySelectorAll('.sidebar-secondary .nav-link').forEach(el => el.classList.remove('active'));
    
    // Show active tab
    const targetTab = document.getElementById(tabId);
    if(targetTab) targetTab.classList.add('active');
    
    // Activate link
    if(element) {
        element.classList.add('active');
    } else {
        const link = document.querySelector(`.sidebar-secondary .nav-link[href="#${tabId}"]`);
        if(link) link.classList.add('active');
    }

    // Save state
    localStorage.setItem('activeProjectTab_{{ $project->id }}', tabId);
    window.history.replaceState(null, null, '#' + tabId);
}

function fillMaxAllocation(btn, balance) {
    const allocWrapper = btn.closest('div');
    const hiddenInput = allocWrapper.querySelector('input.amount-hidden');
    const visibleInput = allocWrapper.querySelector('.amount-display-input');
    
    if (visibleInput) {
        visibleInput.value = balance;
        if(typeof formatAmountInput === 'function') formatAmountInput(visibleInput);
        if(typeof formatAmountBlur === 'function') formatAmountBlur(visibleInput);
        
        // Trigger input event to re-calculate everything
        if (hiddenInput) {
            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
}

function autoFillTotalFromAllocations() {
    let totalAlloc = 0;
    document.querySelectorAll('#recordPaymentForm input[name="alloc_amount[]"]').forEach(input => {
        totalAlloc += parseFloat(input.value) || 0;
    });

    const totalInput = document.querySelector('#recordPaymentForm input[name="total_amount"]');
    if (totalInput) {
        // Always sync the total amount to the sum of allocations when allocations change
        if (totalAlloc >= 0) {
            const visibleTotal = totalInput.parentElement.querySelector('.amount-display-input');
            if (visibleTotal) {
                visibleTotal.value = totalAlloc;
                if(typeof formatAmountInput === 'function') formatAmountInput(visibleTotal);
                if(typeof formatAmountBlur === 'function') formatAmountBlur(visibleTotal);
            } else {
                totalInput.value = totalAlloc;
            }
            
            // Also force sync the first payment mode
            const firstPmAmount = document.querySelector('#recordPaymentForm input[name="pm_amount[]"]');
            if (firstPmAmount) {
                const visiblePm = firstPmAmount.parentElement.querySelector('.amount-display-input');
                if (visiblePm) {
                    visiblePm.value = totalAlloc;
                    if(typeof formatAmountInput === 'function') formatAmountInput(visiblePm);
                    if(typeof formatAmountBlur === 'function') formatAmountBlur(visiblePm);
                } else {
                    firstPmAmount.value = totalAlloc;
                }
            }
        }
    }
}

// Invoice Line Items Logic
let isCalculatingInvoiceTotals = false;

function calculateInvoiceTotals() {
    if (isCalculatingInvoiceTotals) return;
    isCalculatingInvoiceTotals = true;
    try {
        let subtotal = 0;
        document.querySelectorAll('.invoice-item-row').forEach(row => {
            let qty = parseFloat(row.querySelector('input[name="item_qty[]"]')?.value) || 0;
            let priceInput = row.querySelector('.amount-hidden[name="item_price[]"]') || row.querySelector('input[name="item_price[]"]');
            let price = parseFloat(priceInput?.value) || 0;
            
            let lineTotal = qty * price;
            
            let totalDisplay = row.querySelector('.line-total-input');
            if (totalDisplay) {
                totalDisplay.value = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                let hiddenTotal = totalDisplay.parentElement?.querySelector('.amount-hidden');
                if (hiddenTotal) hiddenTotal.value = lineTotal.toFixed(2);
            }
            subtotal += lineTotal;
        });

        // Resolve overall invoice tax rate
        let taxRate = 0;
        const selectedTaxItem = document.querySelector('#invoice_overall_tax .tax-item-row[style*="var(--primary)"]');
        if (selectedTaxItem) {
            taxRate = parseFloat(selectedTaxItem.getAttribute('data-rate')) || 0;
        }

        // Resolve discount amount & type
        let discTypeSelect = document.getElementById('invoice_discount_type');
        let discValueInput = document.getElementById('invoice_discount_value') || document.querySelector('input[name="discount_value"]') || document.querySelector('#invoice_discount_amount');

        let discType = discTypeSelect ? discTypeSelect.value : 'fixed';
        let discVal = 0;
        if (discValueInput) {
            let hiddenDisc = discValueInput.parentElement?.querySelector('.amount-hidden');
            let rawStr = (hiddenDisc && hiddenDisc.value !== '' && hiddenDisc.value !== undefined) ? hiddenDisc.value : discValueInput.value;
            discVal = parseFloat(String(rawStr || '0').replace(/,/g, '')) || 0;
        }

        let discountAmount = 0;
        if (discType === 'percentage') {
            discountAmount = subtotal * (discVal / 100);
        } else {
            discountAmount = discVal;
        }

        let netTaxableAmount = Math.max(0, subtotal - discountAmount);
        let taxAmount = netTaxableAmount * (taxRate / 100);
        let grandTotal = netTaxableAmount + taxAmount;


        const subtotalEl = document.getElementById('invoice-subtotal-display');
        if (subtotalEl) subtotalEl.innerText = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const taxEl = document.getElementById('invoice-tax-display');
        if (taxEl) taxEl.innerText = taxAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        let grandDisplay = document.getElementById('invoice-grand-total');
        if (grandDisplay) {
            grandDisplay.value = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            let hiddenGrand = grandDisplay.parentElement?.querySelector('.amount-hidden');
            if (hiddenGrand) hiddenGrand.value = grandTotal.toFixed(2);
        }
    } finally {
        isCalculatingInvoiceTotals = false;
    }
}

// Global Event Delegation to recalculate totals on any modal input change
document.addEventListener('input', function(e) {
    if (e.target.closest('#createInvoiceModal')) {
        calculateInvoiceTotals();
    }
});
document.addEventListener('change', function(e) {
    if (e.target.closest('#createInvoiceModal')) {
        calculateInvoiceTotals();
    }
});



function addInvoiceRow() {
    const container = document.getElementById('invoice-items-container');
    const firstRow = container.querySelector('.invoice-item-row');
    const newRow = firstRow.cloneNode(true);
    
    // Clear inputs in cloned row
    newRow.querySelectorAll('input').forEach(input => {
        if(input.name === 'item_qty[]') input.value = '1';
        else if(input.name === 'item_tax[]') input.value = '0';
        else input.value = '';
    });
    
    // Add event listeners to new row inputs
    newRow.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', calculateInvoiceTotals);
    });
    
    container.appendChild(newRow);
    calculateInvoiceTotals();
}

function removeInvoiceRow(button) {
    const rows = document.querySelectorAll('.invoice-item-row');
    if(rows.length > 1) {
        button.closest('.invoice-item-row').remove();
        calculateInvoiceTotals();
    } else {
        alert("An invoice must have at least one line item.");
    }
}

// Bind event listeners to initial row and restore tab state
document.addEventListener('DOMContentLoaded', () => {
    // Restore Tab State
    let hash = window.location.hash.substring(1);
    let savedTab = localStorage.getItem('activeProjectTab_{{ $project->id }}');
    let tabToOpen = hash || savedTab || 'overview';
    
    if(document.getElementById(tabToOpen)) {
        switchTab(tabToOpen);
    }

    // Invoice Logic
    document.querySelectorAll('.invoice-item-row input').forEach(input => {
        input.addEventListener('input', calculateInvoiceTotals);
    });
});



// Payment Validation (Real-time and onSubmit)
document.addEventListener('input', function(e) {
    if (e.target.name === 'alloc_amount[]') {
        autoFillTotalFromAllocations();
        checkPaymentBalance();
    } else if (e.target.name === 'total_amount' || e.target.name === 'pm_amount[]') {
        checkPaymentBalance();
    }
});

function checkPaymentBalance() {
    const totalInput = document.querySelector('#recordPaymentForm input[name="total_amount"]');
    const msgDiv = document.getElementById('payment-validation-msg');
    const saveBtn = document.getElementById('savePaymentBtn');
    
    if (!totalInput) return true;
    
    const expectedTotal = parseFloat(totalInput.value) || 0;
    let actualTotal = 0;
    
    document.querySelectorAll('#recordPaymentForm input[name="pm_amount[]"]').forEach(input => {
        actualTotal += parseFloat(input.value) || 0;
    });
    
    let totalAlloc = 0;
    document.querySelectorAll('#recordPaymentForm input[name="alloc_amount[]"]').forEach(input => {
        totalAlloc += parseFloat(input.value) || 0;
    });

    const diff = expectedTotal - actualTotal;
    
    // Validate allocation limit
    if (totalAlloc > expectedTotal && expectedTotal > 0) {
        msgDiv.style.display = 'block';
        msgDiv.style.backgroundColor = '#fee2e2';
        msgDiv.style.color = '#991b1b';
        msgDiv.style.border = '1px solid #fecaca';
        saveBtn.style.opacity = '0.5';
        saveBtn.style.pointerEvents = 'none';
        msgDiv.innerHTML = '<ion-icon name="alert-circle" style="vertical-align:middle;"></ion-icon> <strong>Error:</strong> Invoice allocations ('+totalAlloc.toFixed(2)+') exceed Total Amount ('+expectedTotal.toFixed(2)+').';
        return false;
    }

    if (expectedTotal === 0 && actualTotal === 0) {
        msgDiv.style.display = 'none';
        saveBtn.style.opacity = '1';
        saveBtn.style.pointerEvents = 'auto';
        return true;
    }
    
    msgDiv.style.display = 'block';
    
    if (Math.abs(diff) <= 0.01) {
        msgDiv.style.backgroundColor = '#dcfce7';
        msgDiv.style.color = '#166534';
        msgDiv.style.border = '1px solid #bbf7d0';
        
        let allocMsg = '';
        if (totalAlloc > 0 && totalAlloc < expectedTotal) {
            allocMsg = '<br><small>Allocated to invoices: ' + totalAlloc.toFixed(2) + ' (Remaining: ' + (expectedTotal - totalAlloc).toFixed(2) + ' to General Project)</small>';
        } else if (totalAlloc === expectedTotal) {
            allocMsg = '<br><small>Fully allocated to invoices.</small>';
        }
        
        msgDiv.innerHTML = '<ion-icon name="checkmark-circle" style="vertical-align:middle;"></ion-icon> Payment modes perfectly match the total amount.' + allocMsg;
        saveBtn.style.opacity = '1';
        saveBtn.style.pointerEvents = 'auto';
        return true;
    } else {
        msgDiv.style.backgroundColor = '#fee2e2';
        msgDiv.style.color = '#991b1b';
        msgDiv.style.border = '1px solid #fecaca';
        saveBtn.style.opacity = '0.5';
        saveBtn.style.pointerEvents = 'none';
        
        if (diff > 0) {
            msgDiv.innerHTML = '<ion-icon name="alert-circle" style="vertical-align:middle;"></ion-icon> <strong>Mismatch:</strong> You have <strong>' + diff.toFixed(2) + '</strong> remaining to allocate.';
        } else {
            msgDiv.innerHTML = '<ion-icon name="alert-circle" style="vertical-align:middle;"></ion-icon> <strong>Mismatch:</strong> You have over-allocated by <strong>' + Math.abs(diff).toFixed(2) + '</strong>.';
        }
        return false;
    }
}

function validatePayment() {
    const isValid = checkPaymentBalance();
    if (!isValid) {
        const msgDiv = document.getElementById('payment-validation-msg');
        msgDiv.style.transform = 'scale(1.02)';
        setTimeout(() => msgDiv.style.transform = 'scale(1)', 200);
    }
    return isValid;
}

document.addEventListener('DOMContentLoaded', function() {
    // Financial Performance Chart
    const ctxOverview = document.getElementById('financialOverviewChart');
    if (ctxOverview) {
        new Chart(ctxOverview, {
            type: 'bar',
            data: {
                labels: ['Budget', 'CRs', 'Invoiced', 'Collected', 'Commissions', 'Cost Alloc', 'Project Profit', 'Company Profit'],
                datasets: [{
                    label: 'Amount ({{ $project->currency ?? "LKR" }})',
                    data: [
                        {{ $project->budget_limit }},
                        {{ $totalApprovedCR }},
                        {{ $totalInvoiced }},
                        {{ $invoiceCollected }},
                        {{ $totalCommission }},
                        {{ $totalCostAllocation }},
                        {{ $projectProfit }},
                        {{ $companyProfit }}
                    ],
                    backgroundColor: [
                        'rgba(79, 70, 229, 0.75)',
                        'rgba(8, 145, 178, 0.75)',
                        'rgba(217, 119, 6, 0.75)',
                        'rgba(5, 150, 105, 0.75)',
                        'rgba(219, 39, 119, 0.75)',
                        'rgba(71, 85, 105, 0.75)',
                        'rgba(16, 185, 129, 0.75)',
                        'rgba(2, 132, 199, 0.75)'
                    ],
                    borderColor: [
                        'rgb(79, 70, 229)',
                        'rgb(8, 145, 178)',
                        'rgb(217, 119, 6)',
                        'rgb(5, 150, 105)',
                        'rgb(219, 39, 119)',
                        'rgb(71, 85, 105)',
                        'rgb(16, 185, 129)',
                        'rgb(2, 132, 199)'
                    ],
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '{{ $project->currency ?? "LKR" }} ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Billing & Invoice Realization Chart
    const ctxHealth = document.getElementById('invoiceHealthChart');
    if (ctxHealth) {
        new Chart(ctxHealth, {
            type: 'doughnut',
            data: {
                labels: ['Collected', 'Outstanding', 'Unbilled Base'],
                datasets: [{
                    data: [
                        {{ $invoiceCollected }}, 
                        {{ max(0, $outstandingBalance) }},
                        {{ max(0, ($project->budget_limit + $totalApprovedCR) - $totalInvoiced) }}
                    ],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.85)',
                        'rgba(239, 68, 68, 0.85)',
                        'rgba(226, 232, 240, 0.85)'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>

@php
    $clientPivot = DB::table('project_party')->where('project_id', $project->id)->where('role', 'client')->first();
    $partnerPivot = DB::table('project_party')->where('project_id', $project->id)->where('role', 'partner')->first();
@endphp
<div class="modal-backdrop" id="editProjectModal_{{ $project->id }}">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Project</h3>
            <button class="btn-close" onclick="closeModal('editProjectModal_{{ $project->id }}')">&times;</button>
        </div>
        <form action="/projects/{{ $project->id }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Project Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $project->name }}" required>
                </div>
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Client (Optional)</label>
                        <select name="client_id" class="form-control">
                            <option value="">-- No Client --</option>
                            @foreach($allClients as $c)
                                <option value="{{ $c->id }}" {{ ($clientPivot && $clientPivot->party_id == $c->id) ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Partner (Optional)</label>
                        <select name="partner_id" class="form-control">
                            <option value="">-- No Partner --</option>
                            @foreach($allPartners as $p)
                                <option value="{{ $p->id }}" {{ ($partnerPivot && $partnerPivot->party_id == $p->id) ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Partner Share %</label>
                        <input type="number" step="0.01" name="partner_share_percentage" class="form-control" value="{{ $partnerPivot ? $partnerPivot->share_percentage : 0 }}">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Budget Limit</label>
                        <x-amount-input name="budget_limit" value="{{ $project->budget_limit }}" required="true" />
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $project->start_date }}">
                    </div>
                    <div class="form-col">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $project->end_date }}">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="draft" {{ $project->status === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ $project->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ $project->status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="on-hold" {{ $project->status === 'on-hold' ? 'selected' : '' }}>On Hold</option>
                        <option value="cancelled" {{ $project->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editProjectModal_{{ $project->id }}')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Commission Modal -->
<div class="modal-backdrop" id="createCommissionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Add Project Commission</h3>
            <button class="btn-close" onclick="closeModal('createCommissionModal')">&times;</button>
        </div>
        <form action="/projects/{{ $project->id }}/commissions" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Recipient Party *</label>
                    <x-party-selector 
                        name="party_id" 
                        id="create_commission_party_selector" 
                        :parties="$allRecipients" 
                        placeholder="Search & Select Partner / Vendor / Agent..." 
                        onchange="onProjectCommissionPartyChange" 
                    />
                </div>

                
                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Commission Type *</label>
                    <div style="display:flex; gap:2rem; align-items:center;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="commission_type" value="percentage" checked id="create_comm_type_percentage" onchange="toggleProjectCommType('create')" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Percentage (%)
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="commission_type" value="fixed" id="create_comm_type_fixed" onchange="toggleProjectCommType('create')" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Fixed Amount
                        </label>
                    </div>
                </div>

                <!-- Percentage Fields -->
                <div id="create_project_comm_percentage_fields" style="margin-top:1.5rem;">
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label">Percentage Value (%)</label>
                            <input type="number" step="0.01" name="percentage_value" id="create_percentage_value" class="form-control">
                        </div>
                        <div class="form-col">
                            <label class="form-label">Calculation Basis</label>
                            <select name="calculation_basis" class="form-control">
                                <option value="collected">Percentage of Collected (Paid) Amount</option>
                                <option value="invoiced">Percentage of Invoiced Amount</option>
                                <option value="budget">Percentage of Project Budget Limit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fixed Fields -->
                <div id="create_project_comm_fixed_fields" style="margin-top:1.5rem; display:none;">
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label">Fixed Amount</label>
                            <x-amount-input name="fixed_amount" id="create_fixed_amount" />
                        </div>
                        <div class="form-col">
                            <label class="form-label">Currency</label>
                            <input type="text" name="currency" class="form-control" value="{{ $project->currency ?? ($baseCurrency ?? 'LKR') }}">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:1.5rem;">
                        <label class="form-label">Trigger</label>
                        <select name="trigger_type" class="form-control">
                            <option value="start">One-time on Project Start</option>
                            <option value="invoice">Per Invoice Raised</option>
                            <option value="milestone">Per Milestone / Payment Recorded</option>
                            <option value="manual">Manual / On Demand</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Effective From</label>
                        <input type="date" name="effective_from" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Effective To (Optional)</label>
                        <input type="date" name="effective_to" class="form-control">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createCommissionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Add Commission</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Commission Modal -->
<div class="modal-backdrop" id="editCommissionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Project Commission</h3>
            <button class="btn-close" onclick="closeModal('editCommissionModal')">&times;</button>
        </div>
        <form id="editCommissionForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Recipient Party</label>
                    <input type="text" id="edit_comm_party_name" class="form-control" disabled>
                </div>
                
                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Commission Type *</label>
                    <div style="display:flex; gap:2rem; align-items:center;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="commission_type" value="percentage" id="edit_comm_type_percentage" onchange="toggleProjectCommType('edit')" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Percentage (%)
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="radio" name="commission_type" value="fixed" id="edit_comm_type_fixed" onchange="toggleProjectCommType('edit')" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Fixed Amount
                        </label>
                    </div>
                </div>

                <!-- Percentage Fields -->
                <div id="edit_project_comm_percentage_fields" style="margin-top:1.5rem;">
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label">Percentage Value (%)</label>
                            <input type="number" step="0.01" name="percentage_value" id="edit_comm_percentage_value" class="form-control">
                        </div>
                        <div class="form-col">
                            <label class="form-label">Calculation Basis</label>
                            <select name="calculation_basis" id="edit_comm_calculation_basis" class="form-control">
                                <option value="collected">Percentage of Collected (Paid) Amount</option>
                                <option value="invoiced">Percentage of Invoiced Amount</option>
                                <option value="budget">Percentage of Project Budget Limit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fixed Fields -->
                <div id="edit_project_comm_fixed_fields" style="margin-top:1.5rem; display:none;">
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label">Fixed Amount</label>
                            <x-amount-input name="fixed_amount" id="edit_comm_fixed_amount" />
                        </div>
                        <div class="form-col">
                            <label class="form-label">Currency</label>
                            <input type="text" name="currency" id="edit_comm_currency" class="form-control">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:1.5rem;">
                        <label class="form-label">Trigger</label>
                        <select name="trigger_type" id="edit_comm_trigger_type" class="form-control">
                            <option value="start">One-time on Project Start</option>
                            <option value="invoice">Per Invoice Raised</option>
                            <option value="milestone">Per Milestone / Payment Recorded</option>
                            <option value="manual">Manual / On Demand</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Effective From</label>
                        <input type="date" name="effective_from" id="edit_comm_effective_from" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Effective To (Optional)</label>
                        <input type="date" name="effective_to" id="edit_comm_effective_to" class="form-control">
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_comm_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                            <option value="ended">Ended</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="edit_comm_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editCommissionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Commission</button>
            </div>
        </form>
    </div>
</div>

<!-- View / Edit Change Request Modal -->
<div class="modal-backdrop" id="viewCRModal">
    <div class="modal-card" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="crModalTitle">Change Request Details</h3>
            <button type="button" class="btn-close" onclick="closeModal('viewCRModal')">&times;</button>
        </div>
        <form id="crUpdateForm" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div style="background:var(--bg-page); padding:1.1rem; border-radius:10px; border:1px solid var(--border-light); margin-bottom:1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                        <div>
                            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted);">CR Description</div>
                            <div style="font-weight:700; font-size:1.05rem; color:var(--text-heading); margin-top:0.2rem;" id="crModalDescription"></div>
                        </div>
                        <div style="text-align:right; min-width:120px;">
                            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted);">Amount</div>
                            <div style="font-weight:800; font-size:1.1rem; color:var(--primary);" id="crModalAmount"></div>
                        </div>
                    </div>
                </div>

                <!-- Existing Attachments Section -->
                <div class="form-group" style="margin-bottom:1.25rem;">
                    <label class="form-label" style="display:flex; align-items:center; gap:0.4rem; font-weight:700;">
                        <ion-icon name="attach-outline" style="color:var(--primary); font-size:1.1rem;"></ion-icon> Uploaded File Attachments
                    </label>
                    <div id="crModalAttachmentsList" style="margin-bottom:0.75rem;"></div>
                    
                    <label class="form-label" style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.3rem;">Upload Additional Files</label>
                    <input type="file" name="attachments[]" class="form-control" multiple>
                </div>

                <hr style="border:0; border-top:1px solid var(--border-light); margin:1.25rem 0;">

                <!-- External Links Section -->
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="display:flex; align-items:center; gap:0.4rem; font-weight:700;">
                        <ion-icon name="link-outline" style="color:var(--primary); font-size:1.1rem;"></ion-icon> External Links (Figma, Loom, Google Docs, Specs)
                    </label>
                    <div id="crModalLinksList" style="margin-bottom:0.75rem;"></div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem;">
                        <input type="text" name="link_title" class="form-control" placeholder="Link Title (e.g. Figma Spec)">
                        <input type="url" name="link_url" class="form-control" placeholder="URL (e.g. https://...)">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('viewCRModal')">Close</button>
                <button type="submit" class="btn btn-primary-gradient">Save Attachments & Links</button>
            </div>
        </form>
    </div>
</div>

<script>
function openViewCRModal(cr) {
    document.getElementById('crModalTitle').innerText = 'Change Request Details';
    document.getElementById('crModalDescription').innerText = cr.description;
    document.getElementById('crModalAmount').innerText = (cr.currency || 'LKR') + ' ' + parseFloat(cr.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    document.getElementById('crUpdateForm').action = '/projects/' + cr.project_id + '/change-requests/' + cr.id + '/update';

    // Populate Attachments
    const attContainer = document.getElementById('crModalAttachmentsList');
    attContainer.innerHTML = '';
    if (cr.attachments && cr.attachments.length > 0) {
        let html = '<div style="display:flex; flex-direction:column; gap:0.4rem;">';
        cr.attachments.forEach(att => {
            html += `<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-page); padding:0.5rem 0.8rem; border-radius:6px; font-size:0.85rem; border:1px solid var(--border-light);">
                        <span><ion-icon name="document-outline" style="vertical-align:middle; color:var(--primary);"></ion-icon> ${att.file_name}</span>
                        <a href="/storage/${att.file_path}" target="_blank" class="btn btn-sm btn-outline" style="font-size:0.78rem; padding:0.2rem 0.6rem;">View File</a>
                     </div>`;
        });
        html += '</div>';
        attContainer.innerHTML = html;
    } else {
        attContainer.innerHTML = '<span class="text-muted" style="font-size:0.85rem;">No files uploaded yet.</span>';
    }

    // Populate External Links
    const linkContainer = document.getElementById('crModalLinksList');
    linkContainer.innerHTML = '';
    if (cr.links && cr.links.length > 0) {
        let html = '<div style="display:flex; flex-direction:column; gap:0.4rem;">';
        cr.links.forEach(l => {
            html += `<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-page); padding:0.5rem 0.8rem; border-radius:6px; font-size:0.85rem; border:1px solid var(--border-light);">
                        <span><ion-icon name="link-outline" style="vertical-align:middle; color:var(--primary);"></ion-icon> <strong>${l.title}</strong></span>
                        <a href="${l.url}" target="_blank" class="btn btn-sm btn-outline" style="font-size:0.78rem; padding:0.2rem 0.6rem;">Open Link</a>
                     </div>`;
        });
        html += '</div>';
        linkContainer.innerHTML = html;
    } else {
        linkContainer.innerHTML = '<span class="text-muted" style="font-size:0.85rem;">No external links added yet.</span>';
    }

    openModal('viewCRModal');
}
</script>

<!-- Record Commission Payment Modal -->
<div class="modal-backdrop" id="recordCommPaymentModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Record Commission Payment</h3>
            <button class="btn-close" onclick="closeModal('recordCommPaymentModal')">&times;</button>
        </div>
        <form id="recordCommPaymentForm" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group" style="background:var(--bg-sidebar-secondary); padding:1rem; border-radius:8px; margin-bottom:1.5rem; border:1px solid var(--border);">
                    <div style="font-size:0.9rem; color:var(--text-muted);">Owed To:</div>
                    <strong id="pay_recipient_name" style="font-size:1.1rem; color:var(--text-heading);">Altria</strong>
                    <div style="margin-top:0.5rem; font-size:0.9rem; color:var(--text-muted);">Current Payable Balance:</div>
                    <strong id="pay_recipient_balance" style="font-size:1.3rem; color:var(--danger);">$0.00</strong>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" id="pay_comm_currency" class="form-control" readonly>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Amount Owed *</label>
                        <x-amount-input name="amount" id="pay_comm_amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Payment Mode *</label>
                        <select name="payment_mode" class="form-control" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.5rem;">
                    <div class="form-col">
                        <label class="form-label">Bank Account (if bank transfer/cheque)</label>
                        <select name="bank_account_id" class="form-control">
                            <option value="">-- Select Bank Account --</option>
                            @foreach($bankAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->bank_name }} - {{ $acc->account_no }} ({{ $acc->currency }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Withholding Tax (WHT)</label>
                        <x-tax-selector name="wht_type_id" category="wht" appliesTo="commission_payment" />
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Reference No.</label>
                    <input type="text" name="reference_no" class="form-control">
                </div>


                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('recordCommPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleProjectCommType(prefix) {
    const isPercentage = document.getElementById(prefix + '_comm_type_percentage').checked;
    const pctFields = document.getElementById(prefix + '_project_comm_percentage_fields');
    const fixedFields = document.getElementById(prefix + '_project_comm_fixed_fields');
    if (pctFields && fixedFields) {
        if (isPercentage) {
            pctFields.style.display = 'block';
            fixedFields.style.display = 'none';
        } else {
            pctFields.style.display = 'none';
            fixedFields.style.display = 'block';
        }
    }
}

function prefillPartyCommission(select, prefix) {
    const selectedOption = select.options[select.selectedIndex];
    const type = selectedOption.dataset.type;
    const value = selectedOption.dataset.value;

    if (type === 'percentage') {
        document.getElementById(prefix + '_comm_type_percentage').checked = true;
        document.getElementById(prefix + '_percentage_value').value = value || '';
        toggleProjectCommType(prefix);
    } else if (type === 'fixed') {
        document.getElementById(prefix + '_comm_type_fixed').checked = true;
        document.getElementById(prefix + '_fixed_amount').value = value || '';
        toggleProjectCommType(prefix);
    }
}

const recipientsData = @json($allRecipients ?? []);

function onProjectCommissionPartyChange(partyId, partyName) {
    if (!partyId) return;
    const p = recipientsData.find(item => item.id == partyId);
    if (!p) return;

    const type = p.default_commission_type;
    const value = p.default_commission_value;

    if (type === 'percentage') {
        const percRadio = document.getElementById('create_comm_type_percentage');
        if (percRadio) percRadio.checked = true;
        const valInput = document.getElementById('create_percentage_value');
        if (valInput) valInput.value = value || '';
        toggleProjectCommType('create');
    } else if (type === 'fixed') {
        const fixedRadio = document.getElementById('create_comm_type_fixed');
        if (fixedRadio) fixedRadio.checked = true;
        const fixedInput = document.getElementById('create_fixed_amount');
        if (fixedInput) fixedInput.value = value || '';
        toggleProjectCommType('create');
    }
}


function openEditCommissionModal(comm) {
    document.getElementById('editCommissionForm').action = '/projects/{{ $project->id }}/commissions/' + comm.id;
    document.getElementById('edit_comm_party_name').value = comm.party_name;
    
    if (comm.commission_type === 'percentage') {
        document.getElementById('edit_comm_type_percentage').checked = true;
        document.getElementById('edit_comm_percentage_value').value = comm.percentage_value || '';
        document.getElementById('edit_comm_calculation_basis').value = comm.calculation_basis || 'collected';
    } else {
        document.getElementById('edit_comm_type_fixed').checked = true;
        document.getElementById('edit_comm_fixed_amount').nextElementSibling.value = comm.fixed_amount || '';
        if (typeof formatAmountBlur === 'function') formatAmountBlur(document.getElementById('edit_comm_fixed_amount'));
        document.getElementById('edit_comm_currency').value = comm.currency || 'USD';
        document.getElementById('edit_comm_trigger_type').value = comm.trigger_type || 'start';
    }

    document.getElementById('edit_comm_effective_from').value = comm.effective_from || '';
    document.getElementById('edit_comm_effective_to').value = comm.effective_to || '';
    document.getElementById('edit_comm_status').value = comm.status || 'active';
    document.getElementById('edit_comm_notes').value = comm.notes || '';

    toggleProjectCommType('edit');
    openModal('editCommissionModal');
}

function openPaymentModal(comm) {
    document.getElementById('recordCommPaymentForm').action = '/projects/{{ $project->id }}/commissions/' + comm.id + '/payments';
    document.getElementById('pay_recipient_name').innerText = comm.party_name;
    
    const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: comm.currency || 'USD' });
    document.getElementById('pay_recipient_balance').innerText = formatter.format(comm.payable);
    document.getElementById('pay_comm_currency').value = comm.currency || '{{ $project->currency ?? "USD" }}';
    document.getElementById('pay_comm_amount').nextElementSibling.value = comm.payable.toFixed(2);
    if (typeof formatAmountBlur === 'function') formatAmountBlur(document.getElementById('pay_comm_amount'));
    document.getElementById('pay_comm_amount').max = comm.payable;

    openModal('recordCommPaymentModal');
}

// CR-2 Schedule repeatable rows & modal control scripts
function toggleIntervalField(prefix) {
    const freq = document.getElementById(prefix + '_frequency').value;
    const intervalCol = document.getElementById(prefix + '_interval_col');
    const dayCol = document.getElementById(prefix + '_day_col');
    
    if (freq === 'custom') {
        if (intervalCol) intervalCol.style.display = 'block';
        if (dayCol) dayCol.style.display = 'none';
    } else {
        if (intervalCol) intervalCol.style.display = 'none';
        if (dayCol) dayCol.style.display = 'block';
    }
}

function addScheduleRow(prefix, desc = '', qty = 1, price = '', tax = 0) {
    const tbody = document.getElementById(prefix + '_schedule_items_tbody');
    const tr = document.createElement('tr');
    tr.className = 'schedule-item-row';
    tr.innerHTML = `
        <td><input type="text" name="item_description[]" class="form-control" style="padding:0.4rem;" value="${desc}" required></td>
        <td><input type="number" step="0.01" name="item_qty[]" class="form-control" style="padding:0.4rem;" value="${qty}" required></td>
        <td>
            <div class="amount-input-wrapper" style="position: relative; flex-grow: 1;">
                <input type="text" class="form-control amount-display-input" style="padding:0.4rem;" placeholder="0.00" value="${price}" required oninput="if(typeof formatAmountInput !== 'undefined') formatAmountInput(this)" onblur="if(typeof formatAmountBlur !== 'undefined') formatAmountBlur(this)">
                <input type="hidden" name="item_price[]" class="amount-hidden" value="${price}">
            </div>
        </td>
        <td><input type="number" step="0.01" name="item_tax[]" class="form-control" style="padding:0.4rem;" value="${tax}"></td>
        <td style="text-align:center;"><button type="button" class="btn" style="color:var(--danger); border:none; background:transparent; font-size:1.2rem; cursor:pointer;" onclick="removeScheduleRow(this)">&times;</button></td>
    `;
    tbody.appendChild(tr);
}

function removeScheduleRow(btn) {
    const row = btn.closest('tr');
    const tbody = row.parentNode;
    if (tbody.querySelectorAll('tr').length > 1) {
        row.remove();
    } else {
        alert('At least one line item is required.');
    }
}

function openEditScheduleModal(s) {
    document.getElementById('editScheduleForm').action = '/projects/{{ $project->id }}/schedules/' + s.id;
    document.getElementById('edit_sched_name').value = s.name;
    document.getElementById('edit_sched_from_date').value = s.from_date;
    document.getElementById('edit_sched_to_date').value = s.to_date || '';
    document.getElementById('edit_sched_frequency').value = s.frequency;
    document.getElementById('edit_sched_custom_interval_days').value = s.custom_interval_days || '';
    document.getElementById('edit_sched_generate_day').value = s.generate_day || '';
    document.getElementById('edit_sched_next_generation_date').value = s.next_generation_date;
    document.getElementById('edit_sched_invoice_type_id').value = s.invoice_type_id;
    document.getElementById('edit_sched_currency').value = s.currency;
    document.getElementById('edit_sched_template_id').value = s.template_id || '';
    document.getElementById('edit_sched_notes').value = s.notes || '';
    
    document.getElementById('edit_sched_require_approval').checked = s.require_approval == 1;
    document.getElementById('edit_sched_auto_adjust_holidays').checked = s.auto_adjust_holidays == 1;
    document.getElementById('edit_sched_notify_on_generation').checked = s.notify_on_generation == 1;

    // Populate repeatable items
    const tbody = document.getElementById('edit_schedule_items_tbody');
    tbody.innerHTML = '';
    
    if (s.items && s.items.length > 0) {
        s.items.forEach(item => {
            addScheduleRow('edit', item.description, item.quantity, item.unit_price, item.tax_percentage);
        });
    } else {
        addScheduleRow('edit');
    }

    toggleIntervalField('edit');
    openModal('editScheduleModal');
}

function openApprovalSidebar() {
    const sidebar = document.getElementById('approvalInboxSidebar');
    if (sidebar) {
        sidebar.style.display = 'block';
        setTimeout(() => {
            sidebar.querySelector('.sidebar-drawer-card').style.transform = 'translateX(0)';
        }, 10);
    }
}

function closeApprovalSidebar() {
    const sidebar = document.getElementById('approvalInboxSidebar');
    if (sidebar) {
        sidebar.querySelector('.sidebar-drawer-card').style.transform = 'translateX(100%)';
        setTimeout(() => {
            sidebar.style.display = 'none';
        }, 300);
    }
}
</script>

<!-- Create Schedule Modal -->
<div class="modal-backdrop" id="createScheduleModal">
    <div class="modal-card" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Create Invoice Schedule</h3>
            <button class="btn-close" onclick="closeModal('createScheduleModal')">&times;</button>
        </div>
        <form action="/projects/{{ $project->id }}/schedules" method="POST">
            @csrf
            <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                <h4 style="margin-bottom:1rem; color:var(--text-heading); border-bottom:1px solid var(--border-light); padding-bottom:0.5rem;">Validity & Recurrence</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Schedule Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Monthly Support retainer" required>
                    </div>
                </div>
                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Start Billing Date *</label>
                        <input type="date" name="from_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">End Date (Optional)</label>
                        <input type="date" name="to_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Frequency *</label>
                        <select name="frequency" id="create_frequency" class="form-control" onchange="toggleIntervalField('create')" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                            <option value="custom">Custom (Days Interval)</option>
                        </select>
                    </div>
                    <div class="form-col" id="create_interval_col" style="display:none;">
                        <label class="form-label">Custom Days Interval *</label>
                        <input type="number" name="custom_interval_days" id="create_custom_interval_days" class="form-control" placeholder="e.g. 14">
                    </div>
                    <div class="form-col" id="create_day_col">
                        <label class="form-label">Billing Day of Month (Optional)</label>
                        <select name="generate_day" class="form-control">
                            <option value="">-- Same as Start Date --</option>
                            @for($d = 1; $d <= 28; $d++)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">First Run Date (Optional)</label>
                        <input type="date" name="next_generation_date" class="form-control">
                    </div>
                </div>

                <h4 style="margin-top:2rem; margin-bottom:1rem; color:var(--text-heading); border-bottom:1px solid var(--border-light); padding-bottom:0.5rem;">Invoice Configuration</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Invoice Type *</label>
                        <select name="invoice_type_id" class="form-control" required>
                            @foreach($allInvoiceTypes as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ ucfirst($t->maps_to) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency *</label>
                        <input type="text" name="currency" class="form-control" value="{{ $project->currency ?? ($baseCurrency ?? 'LKR') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Document Template</label>
                        <select name="template_id" class="form-control">
                            <option value="">-- Default Template --</option>
                            @foreach($documentTemplates as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label" style="font-weight:600;">Template Line Items *</label>
                    <table class="mini-table">
                        <thead>
                            <tr style="text-align:left;">
                                <th>Description</th>
                                <th style="width:15%;">Qty</th>
                                <th style="width:20%;">Price</th>
                                <th style="width:15%;">Tax %</th>
                                <th style="width:10%;"></th>
                            </tr>
                        </thead>
                        <tbody id="create_schedule_items_tbody">
                            <tr class="schedule-item-row">
                                <td><input type="text" name="item_description[]" class="form-control" style="padding:0.4rem;" required></td>
                                <td><input type="number" step="0.01" name="item_qty[]" class="form-control" style="padding:0.4rem;" value="1" required></td>
                                <td><x-amount-input name="item_price[]" class="form-control" style="padding:0.4rem;" required="true" /></td>
                                <td><input type="number" step="0.01" name="item_tax[]" class="form-control" style="padding:0.4rem;" value="0"></td>
                                <td style="text-align:center;"><button type="button" class="btn" style="color:var(--danger); border:none; background:transparent; font-size:1.2rem; cursor:pointer;" onclick="removeScheduleRow(this)">&times;</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline btn-pill" style="margin-top:0.5rem; padding:0.4rem 1rem; font-size:0.85rem;" onclick="addScheduleRow('create')">+ Add Line Item</button>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Invoice Notes / Terms</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <h4 style="margin-top:2rem; margin-bottom:1rem; color:var(--text-heading); border-bottom:1px solid var(--border-light); padding-bottom:0.5rem;">Billing Behavior</h4>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="require_approval" value="1" checked style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Hold generated invoices as Draft for Admin Approval before sending
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="auto_adjust_holidays" value="1" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Auto-adjust weekend generation runs to the next working day (Monday)
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="notify_on_generation" value="1" checked style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Create reminder and notify admin when invoice draft is generated
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Milestone Modal -->
<div class="modal-backdrop" id="createMilestoneModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">New Payment Milestone</h3>
            <button class="btn-close" onclick="closeModal('createMilestoneModal')">&times;</button>
        </div>
        <form action="/projects/{{ $project->id }}/milestones" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Milestone Name / Description</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Amount</label>
                        <x-amount-input name="amount" required="true" />
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createMilestoneModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Milestone</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal-backdrop" id="editScheduleModal">
    <div class="modal-card" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Invoice Schedule</h3>
            <button class="btn-close" onclick="closeModal('editScheduleModal')">&times;</button>
        </div>
        <form id="editScheduleForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                <h4 style="margin-bottom:1rem; color:var(--text-heading); border-bottom:1px solid var(--border-light); padding-bottom:0.5rem;">Validity & Recurrence</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Schedule Name *</label>
                        <input type="text" name="name" id="edit_sched_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Start Billing Date *</label>
                        <input type="date" name="from_date" id="edit_sched_from_date" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">End Date (Optional)</label>
                        <input type="date" name="to_date" id="edit_sched_to_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Frequency *</label>
                        <select name="frequency" id="edit_sched_frequency" class="form-control" onchange="toggleIntervalField('edit')" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                            <option value="custom">Custom (Days Interval)</option>
                        </select>
                    </div>
                    <div class="form-col" id="edit_interval_col" style="display:none;">
                        <label class="form-label">Custom Days Interval *</label>
                        <input type="number" name="custom_interval_days" id="edit_sched_custom_interval_days" class="form-control">
                    </div>
                    <div class="form-col" id="edit_day_col">
                        <label class="form-label">Billing Day of Month (Optional)</label>
                        <select name="generate_day" id="edit_sched_generate_day" class="form-control">
                            <option value="">-- Same as Start Date --</option>
                            @for($d = 1; $d <= 28; $d++)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Next Generation Run Date *</label>
                        <input type="date" name="next_generation_date" id="edit_sched_next_generation_date" class="form-control" required>
                    </div>
                </div>

                <h4 style="margin-top:2rem; margin-bottom:1rem; color:var(--text-heading); border-bottom:1px solid var(--border-light); padding-bottom:0.5rem;">Invoice Configuration</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Invoice Type *</label>
                        <select name="invoice_type_id" id="edit_sched_invoice_type_id" class="form-control" required>
                            @foreach($allInvoiceTypes as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ ucfirst($t->maps_to) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency *</label>
                        <input type="text" name="currency" id="edit_sched_currency" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Document Template</label>
                        <select name="template_id" id="edit_sched_template_id" class="form-control">
                            <option value="">-- Default Template --</option>
                            @foreach($documentTemplates as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label" style="font-weight:600;">Template Line Items *</label>
                    <table class="mini-table">
                        <thead>
                            <tr style="text-align:left;">
                                <th>Description</th>
                                <th style="width:15%;">Qty</th>
                                <th style="width:20%;">Price</th>
                                <th style="width:15%;">Tax %</th>
                                <th style="width:10%;"></th>
                            </tr>
                        </thead>
                        <tbody id="edit_schedule_items_tbody">
                            <!-- Populated dynamically via javascript -->
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline btn-pill" style="margin-top:0.5rem; padding:0.4rem 1rem; font-size:0.85rem;" onclick="addScheduleRow('edit')">+ Add Line Item</button>
                </div>

                <div class="form-group" style="margin-top:1.5rem;">
                    <label class="form-label">Invoice Notes / Terms</label>
                    <textarea name="notes" id="edit_sched_notes" class="form-control" rows="2"></textarea>
                </div>

                <h4 style="margin-top:2rem; margin-bottom:1rem; color:var(--text-heading); border-bottom:1px solid var(--border-light); padding-bottom:0.5rem;">Billing Behavior</h4>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="require_approval" id="edit_sched_require_approval" value="1" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Hold generated invoices as Draft for Admin Approval before sending
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="auto_adjust_holidays" id="edit_sched_auto_adjust_holidays" value="1" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Auto-adjust weekend generation runs to the next working day (Monday)
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="notify_on_generation" id="edit_sched_notify_on_generation" value="1" style="width:1.1rem; height:1.1rem; accent-color:var(--primary);"> Create reminder and notify admin when invoice draft is generated
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- Approval Inbox Sidebar Drawer -->
<div class="modal-backdrop" id="approvalInboxSidebar" style="background:rgba(0,0,0,0.3); z-index:900;" onclick="if(event.target === this) closeApprovalSidebar()">
    <div class="sidebar-drawer-card" style="position:absolute; right:0; top:0; width:450px; height:100vh; background:var(--bg-card); box-shadow:-10px 0 30px rgba(0,0,0,0.1); display:flex; flex-direction:column; transition:transform 0.3s ease-in-out; transform:translateX(100%);">
        <div class="modal-header" style="border-bottom:1px solid var(--border-light); padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem; font-size:1.2rem;">
                <ion-icon name="checkbox-outline" style="color:var(--primary); font-size:1.4rem;"></ion-icon> Approval Inbox
            </h3>
            <button type="button" class="btn-close" style="font-size:1.5rem; border:none; background:transparent; cursor:pointer; color:var(--text-muted);" onclick="closeApprovalSidebar()">&times;</button>
        </div>
        <div class="sidebar-drawer-body" style="flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem;">
            @forelse($draftInvoices as $draft)
                <div style="background:var(--bg-page); border:1px solid var(--border); border-radius:10px; padding:1.25rem; display:flex; flex-direction:column; gap:0.75rem; box-shadow:0 2px 4px rgba(0,0,0,0.01);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:600; color:var(--text-heading); font-size:0.95rem;">
                            {{ str_starts_with($draft->invoice_no, 'INV-SCH-') ? 'SCHED DRAFT' : $draft->invoice_no }}
                        </span>
                        <span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:0.75rem;">Awaiting Review</span>
                    </div>
                    
                    <div style="font-size:0.85rem; color:var(--text-muted); display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                        <div>Source: <strong style="color:var(--text-main);">{{ $draft->source_name }}</strong></div>
                        <div>Date: <strong style="color:var(--text-main);">{{ $draft->issue_date }}</strong></div>
                        <div>Amount: <strong style="color:var(--text-main);">${{ number_format($draft->amount, 2) }}</strong></div>
                        <div>Due: <strong style="color:var(--text-main);">{{ $draft->due_date }}</strong></div>
                    </div>
                    
                    <!-- Line Items Summary -->
                    <div style="background:var(--bg-card); border-radius:6px; padding:0.75rem; border:1px solid var(--border-light); font-size:0.85rem;">
                        <strong style="color:var(--text-heading); font-size:0.8rem; display:block; margin-bottom:0.25rem;">Template items:</strong>
                        @foreach($draft->items as $item)
                            <div style="display:flex; justify-content:space-between; color:var(--text-main); margin-bottom:0.15rem;">
                                <span>{{ $item->description }} (x{{ (int)$item->qty }})</span>
                                <span>${{ number_format($item->total, 2) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:0.25rem;">
                        <form action="/projects/{{ $project->id }}/invoices/{{ $draft->id }}/reject" method="POST" onsubmit="return confirm('Cancel/Reject this draft invoice?');">
                            @csrf
                            <button type="submit" class="btn btn-outline" style="padding:0.4rem 0.8rem; font-size:0.8rem; border-color:var(--danger); color:var(--danger);">Reject</button>
                        </form>
                        <form action="/projects/{{ $project->id }}/invoices/{{ $draft->id }}/approve" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary-gradient" style="padding:0.4rem 1rem; font-size:0.8rem;">Confirm & Approve</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted" style="text-align:center; padding:3rem 0;">No draft invoices awaiting review.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
function openCreateInvoiceForMilestone(milestoneId, amount, name) {
    // Set the hidden milestone ID
    document.getElementById('invoice_payment_milestone_id').value = milestoneId;
    
    // Find the first line item in the create invoice modal
    const firstRow = document.querySelector('#invoice-items-container .invoice-item-row');
    if (firstRow) {
        // Set description
        const descInput = firstRow.querySelector('input[name="item_description[]"]');
        if (descInput) descInput.value = name;
        
        // Set amount
        const priceInput = firstRow.querySelector('.amount-display-input');
        if (priceInput) {
            priceInput.value = amount;
            priceInput.dispatchEvent(new Event('input', {bubbles: true})); // trigger amount formatting
            priceInput.dispatchEvent(new Event('blur', {bubbles: true})); // apply final blur format
        }
    }
    
    // Open the modal
    openModal('createInvoiceModal');
}

function openEditDocumentModal(doc) {
    document.getElementById('editDocumentForm').action = '/documents/' + doc.id;
    document.getElementById('edit_doc_name').value = doc.name;
    document.getElementById('edit_doc_type').value = doc.type;
    
    if (doc.source_type === 'file') {
        document.getElementById('edit_doc_source_file').checked = true;
        document.getElementById('editDocFileWrapper').style.display = 'block';
        document.getElementById('editDocLinkWrapper').style.display = 'none';
    } else {
        document.getElementById('edit_doc_source_link').checked = true;
        document.getElementById('editDocFileWrapper').style.display = 'none';
        document.getElementById('editDocLinkWrapper').style.display = 'block';
    }
    
    document.getElementById('edit_doc_url').value = doc.url || '';
    document.getElementById('edit_doc_link_label').value = doc.link_label || '';
    document.getElementById('edit_doc_cr').value = doc.change_request_id || '';
    document.getElementById('edit_doc_date').value = doc.document_date || '';
    document.getElementById('edit_doc_tags').value = doc.tags || '';
    document.getElementById('edit_doc_notes').value = doc.notes || '';
    document.getElementById('edit_doc_visible').checked = doc.visible_to_client == 1;

    openModal('editDocumentModal');
}
</script>

<!-- Project Profit Breakdown Modal -->
<div class="modal-backdrop" id="projectProfitModal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="stats-chart-outline" style="color:var(--success);"></ion-icon>
                Project Profit Breakdown
            </h3>
            <button type="button" class="btn-close" onclick="closeModal('projectProfitModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem;">
            <div style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color:white; padding:1.25rem; border-radius:12px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span style="font-size:0.85rem; opacity:0.9;">Total Project Profit</span>
                    <h2 style="font-size:1.8rem; font-weight:700; margin:0.2rem 0 0 0;">{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($projectProfit, 2) }}</h2>
                </div>
                <div style="text-align:right; font-size:0.8rem; opacity:0.9;">
                    Formula:<br>
                    <strong>(Budget + CR) - Commission</strong>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div style="border:1px solid var(--border-light); border-radius:10px; padding:1rem; background:var(--bg-page);">
                    <div style="display:flex; justify-content:space-between; font-weight:600; color:var(--text-heading); font-size:0.95rem;">
                        <span>1. Project Budget Limit</span>
                        <span style="color:var(--success);">+{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($project->budget_limit, 2) }}</span>
                    </div>
                </div>

                <div style="border:1px solid var(--border-light); border-radius:10px; padding:1rem; background:var(--bg-page);">
                    <div style="display:flex; justify-content:space-between; font-weight:600; color:var(--text-heading); font-size:0.95rem; margin-bottom:0.5rem;">
                        <span>2. Approved / Invoiced Change Requests</span>
                        <span style="color:var(--success);">+{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalApprovedCR, 2) }}</span>
                    </div>
                    @php $approvedCRsList = $change_requests->whereIn('status', ['approved', 'invoiced']); @endphp
                    @if($approvedCRsList->count() > 0)
                        <table class="mini-table" style="margin-top:0.5rem;">
                            <thead>
                                <tr><th>Date</th><th>Description</th><th style="text-align:right;">Amount</th></tr>
                            </thead>
                            <tbody>
                                @foreach($approvedCRsList as $cr)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($cr->created_at)->format('Y-m-d') }}</td>
                                    <td>{{ $cr->description }}</td>
                                    <td style="text-align:right; font-weight:500;">+{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($cr->amount, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted" style="font-size:0.85rem; margin:0;">No approved change requests.</p>
                    @endif
                </div>

                <div style="background:var(--bg-sidebar-secondary); border:1px solid var(--border); border-radius:10px; padding:0.75rem 1rem; display:flex; justify-content:space-between; font-weight:700; color:var(--primary);">
                    <span>Total Revenue Base (Budget + CR)</span>
                    <span>{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($project->budget_limit + $totalApprovedCR, 2) }}</span>
                </div>

                <div style="border:1px solid var(--border-light); border-radius:10px; padding:1rem; background:var(--bg-page);">
                    <div style="display:flex; justify-content:space-between; font-weight:600; color:var(--text-heading); font-size:0.95rem; margin-bottom:0.5rem;">
                        <span>3. Partner / External Commissions (-)</span>
                        <span style="color:var(--danger);">-{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($totalCommission, 2) }}</span>
                    </div>
                    @if($commissions->count() > 0)
                        <table class="mini-table" style="margin-top:0.5rem;">
                            <thead>
                                <tr><th>Recipient</th><th>Basis / Rate</th><th style="text-align:right;">Commission</th></tr>
                            </thead>
                            <tbody>
                                @foreach($commissions as $comm)
                                <tr>
                                    <td>{{ $comm->party_name ?? 'Partner' }}</td>
                                    <td>
                                        @if($comm->commission_type === 'percentage')
                                            {{ $comm->percentage_value }}% of {{ ucfirst($comm->calculation_basis) }}
                                        @else
                                            Fixed {{ $comm->currency ?? ($project->currency ?? 'LKR') }} {{ number_format($comm->fixed_amount, 2) }}
                                        @endif
                                    </td>
                                    <td style="text-align:right; font-weight:500; color:var(--danger);">-{{ $project->currency ?? ($company->base_currency ?? 'LKR') }} {{ number_format($comm->total_commission, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted" style="font-size:0.85rem; margin:0;">No commissions setup.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('projectProfitModal')">Close</button>
        </div>
    </div>
</div>

<!-- Company Profit Breakdown Component Modal -->
<x-company-profit-modal 
    modalId="companyProfitModal"
    :currency="$project->currency ?? ($company->base_currency ?? 'LKR')"
    :budgetLimit="$project->budget_limit"
    :totalApprovedCR="$totalApprovedCR"
    :changeRequests="$change_requests"
    :totalCommission="$totalCommission"
    :commissions="$commissions"
    :costAllocations="$costAllocationsList"
    :companyProfit="$companyProfit"
/>

@endsection
