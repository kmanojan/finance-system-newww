@extends('layouts.share')
@section('title', 'Project View — ' . $project->name)

@section('content')
@php
    $currency = $project->currency ?? 'LKR';
    $outstanding = max(0, $outstandingBalance);
    $invoicedPct = ($project->budget_limit > 0) ? min(100, round(($totalInvoiced / $project->budget_limit) * 100, 1)) : 100;
    $collectedPct = ($totalInvoiced > 0) ? min(100, round(($totalCollected / $totalInvoiced) * 100, 1)) : 100;
@endphp

@if(isset($link) && $link->shareable_type === 'party')
<!-- Navigation Back Button (Shown when viewing a project from a Client Account Portfolio link) -->
<div style="margin-bottom: 1.25rem;">
    <a href="/share/{{ $link->token }}" class="btn btn-outline" style="border-radius:8px; font-weight:600; font-size:0.85rem; text-decoration:none; padding:0.45rem 1rem; display:inline-flex; align-items:center; gap:0.4rem;">
        <ion-icon name="arrow-back-outline"></ion-icon> Back to Projects Portfolio
    </a>
</div>
@endif


<!-- Glassmorphic Header Card -->
<div class="glass-header" style="margin-bottom:1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                <span class="metric-pill" style="background:var(--primary-light); color:var(--primary);">
                    <ion-icon name="briefcase-outline"></ion-icon> Client Project Portal
                </span>
            </div>
            <h1 style="margin: 0; font-size: 2.2rem; font-weight: 800; color: var(--text-heading);">{{ $project->name }}</h1>
            <p style="margin-top: 0.4rem; color: var(--text-muted); font-size: 1rem; max-width:650px;">
                {{ $project->description ?? 'Interactive client financial portal, billing realization, and document repository.' }}
            </p>
        </div>
        <div style="text-align: right;">
            @if($project->status === 'active')
                <span class="metric-pill" style="background:var(--primary-light); color:var(--primary); font-size:0.9rem; padding: 0.4rem 1rem;">Active</span>
            @elseif($project->status === 'completed')
                <span class="metric-pill" style="background:#dcfce7; color:#15803d; font-size:0.9rem; padding: 0.4rem 1rem;">Completed</span>
            @else
                <span class="metric-pill" style="background:#f1f5f9; color:#475569; font-size:0.9rem; padding: 0.4rem 1rem;">{{ ucfirst($project->status) }}</span>
            @endif
        </div>
    </div>
</div>

<!-- Interactive Navigation Tab Bar -->
<div class="portal-card" style="padding:0.5rem; margin-bottom:1.75rem; background:var(--bg-card); display:flex; gap:0.5rem; flex-wrap:wrap; border-radius:12px;">
    <button type="button" class="interactive-tab-btn active" id="tab-overview" onclick="switchShareTab('overview')">
        <ion-icon name="analytics-outline" style="vertical-align:middle;"></ion-icon> Overview
    </button>
    <button type="button" class="interactive-tab-btn" id="tab-invoices" onclick="switchShareTab('invoices')">
        <ion-icon name="document-text-outline" style="vertical-align:middle;"></ion-icon> Invoices 
        <span style="background:var(--primary-light); color:var(--primary); padding:0.1rem 0.45rem; border-radius:10px; font-size:0.75rem; margin-left:0.2rem;">{{ $invoices->count() }}</span>
    </button>
    <button type="button" class="interactive-tab-btn" id="tab-payments" onclick="switchShareTab('payments')">
        <ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Payments 
        <span style="background:var(--bg-page); color:var(--text-muted); padding:0.1rem 0.45rem; border-radius:10px; font-size:0.75rem; margin-left:0.2rem;">{{ $payments->count() }}</span>
    </button>
    <button type="button" class="interactive-tab-btn" id="tab-documents" onclick="switchShareTab('documents')">
        <ion-icon name="folder-open-outline" style="vertical-align:middle;"></ion-icon> Documents 
        <span style="background:var(--bg-page); color:var(--text-muted); padding:0.1rem 0.45rem; border-radius:10px; font-size:0.75rem; margin-left:0.2rem;">{{ $documents->count() }}</span>
    </button>
    <button type="button" class="interactive-tab-btn" id="tab-cr" onclick="switchShareTab('cr')">
        <ion-icon name="git-pull-request-outline" style="vertical-align:middle;"></ion-icon> Change Requests 
        <span style="background:var(--bg-page); color:var(--text-muted); padding:0.1rem 0.45rem; border-radius:10px; font-size:0.75rem; margin-left:0.2rem;">{{ $change_requests->count() }}</span>
    </button>
</div>

<!-- ==================== TAB 1: OVERVIEW ==================== -->
<div id="content-overview" class="share-tab-content">
    <!-- 4 KPI Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="portal-card" style="margin:0; background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); color: white;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 0.8rem; text-transform: uppercase; font-weight:700; opacity:0.9;">Project Budget</span>
                <ion-icon name="wallet-outline" style="font-size:1.4rem; opacity:0.85;"></ion-icon>
            </div>
            <div style="font-size: 1.7rem; font-weight: 800; margin-top: 0.4rem;">
                {{ $currency }} {{ number_format($totalProjectValue, 2) }}
            </div>
            <div style="font-size:0.75rem; opacity:0.85; margin-top:0.3rem;">Contract Budget + Approved CRs</div>
        </div>

        <div class="portal-card" style="margin:0; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 0.8rem; text-transform: uppercase; font-weight:700; opacity:0.9;">Total Invoiced</span>
                <ion-icon name="document-text-outline" style="font-size:1.4rem; opacity:0.85;"></ion-icon>
            </div>
            <div style="font-size: 1.7rem; font-weight: 800; margin-top: 0.4rem;">
                {{ $currency }} {{ number_format($totalInvoiced, 2) }}
            </div>
            <div style="font-size:0.75rem; opacity:0.85; margin-top:0.3rem;">Total billed to date</div>
        </div>

        <div class="portal-card" style="margin:0; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 0.8rem; text-transform: uppercase; font-weight:700; opacity:0.9;">Payments Settled</span>
                <ion-icon name="checkmark-done-circle-outline" style="font-size:1.4rem; opacity:0.85;"></ion-icon>
            </div>
            <div style="font-size: 1.7rem; font-weight: 800; margin-top: 0.4rem;">
                {{ $currency }} {{ number_format($totalCollected, 2) }}
            </div>
            <div style="font-size:0.75rem; opacity:0.85; margin-top:0.3rem;">Total payments received</div>
        </div>

        <div class="portal-card" style="margin:0; background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); color: white;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 0.8rem; text-transform: uppercase; font-weight:700; opacity:0.9;">Outstanding Balance</span>
                <ion-icon name="alert-circle-outline" style="font-size:1.4rem; opacity:0.85;"></ion-icon>
            </div>
            <div style="font-size: 1.7rem; font-weight: 800; margin-top: 0.4rem;">
                {{ $currency }} {{ number_format($outstanding, 2) }}
            </div>
            <div style="font-size:0.75rem; opacity:0.85; margin-top:0.3rem;">Current balance due</div>
        </div>
    </div>


    <!-- Collection Progress Card -->
    <div class="portal-card">
        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-heading); margin-top: 0; margin-bottom: 1rem;">
            Billing & Collection Realization Rate
        </h3>
        <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:700; color:var(--text-heading); margin-bottom:0.4rem;">
            <span>Realized Collections vs Invoiced Total</span>
            <span style="color:var(--success);">{{ $collectedPct }}% Settled</span>
        </div>
        <div style="background:var(--bg-page); height:12px; border-radius:10px; overflow:hidden; border:1px solid var(--border-light); margin-bottom:1.5rem;">
            <div style="width:{{ $collectedPct }}%; background:linear-gradient(90deg, #10b981, #059669); height:100%; border-radius:10px; transition:width 1s ease;"></div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; font-size:0.9rem;">
            <div style="background:var(--bg-page); padding:1rem; border-radius:10px; border:1px solid var(--border-light);">
                <div class="text-muted" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">Project Start Date</div>
                <div style="font-weight:700; color:var(--text-heading); margin-top:0.2rem;">{{ $project->start_date ?? 'N/A' }}</div>
            </div>
            <div style="background:var(--bg-page); padding:1rem; border-radius:10px; border:1px solid var(--border-light);">
                <div class="text-muted" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">Target End Date</div>
                <div style="font-weight:700; color:var(--text-heading); margin-top:0.2rem;">{{ $project->end_date ?? 'Ongoing' }}</div>
            </div>
            <div style="background:var(--bg-page); padding:1rem; border-radius:10px; border:1px solid var(--border-light);">
                <div class="text-muted" style="font-size:0.75rem; font-weight:700; text-transform:uppercase;">Project Status</div>
                <div style="font-weight:700; color:var(--primary); margin-top:0.2rem;">{{ ucfirst($project->status) }}</div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TAB 2: INVOICES ==================== -->
<div id="content-invoices" class="share-tab-content" style="display:none;">
    <div class="portal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem;">
            <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-heading); margin:0; display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="document-text-outline" style="color:var(--primary);"></ion-icon> Issued Invoices
            </h2>
            <div style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">
                {{ $invoices->count() }} {{ Str::plural('Invoice', $invoices->count()) }}
            </div>
        </div>

        @if($invoices->isEmpty())
            <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                <ion-icon name="document-outline" style="font-size:2.8rem; opacity:0.3; margin-bottom:0.5rem;"></ion-icon><br>
                No invoices have been issued for this project yet.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; margin:0;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); color: var(--text-muted); font-size:0.8rem; text-transform:uppercase;">
                            <th style="padding: 0.85rem 0.6rem;">Invoice No</th>
                            <th style="padding: 0.85rem 0.6rem;">Issue Date</th>
                            <th style="padding: 0.85rem 0.6rem;">Due Date</th>
                            <th style="padding: 0.85rem 0.6rem; text-align:center;">Status</th>
                            <th style="padding: 0.85rem 0.6rem; text-align: right;">Amount</th>
                            <th style="padding: 0.85rem 0.6rem; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $inv)
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.85rem 0.6rem; font-weight: 700; color:var(--text-heading); font-size:0.9rem;">
                                {{ $inv->invoice_no }}
                                @if(!empty($inv->is_cr))
                                    <span class="metric-pill" style="background:#fef3c7; color:#b45309; font-size:0.7rem; padding:0.15rem 0.5rem; margin-left:0.3rem;" title="Change Request Invoice">
                                        <ion-icon name="git-pull-request-outline"></ion-icon> CR
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 0.6rem; font-size:0.88rem; color:var(--text-heading);">{{ $inv->issue_date }}</td>
                            <td style="padding: 0.85rem 0.6rem; font-size:0.88rem; color:var(--text-heading);">{{ $inv->due_date }}</td>
                            <td style="padding: 0.85rem 0.6rem; text-align:center;">
                                @if($inv->status === 'paid')
                                    <span class="metric-pill" style="background:#dcfce7; color:#15803d;">Paid</span>
                                @elseif($inv->status === 'sent' || $inv->status === 'issued')
                                    <span class="metric-pill" style="background:#e0f2fe; color:#0369a1;">Issued</span>
                                @else
                                    <span class="metric-pill" style="background:#f1f5f9; color:#475569;">{{ ucfirst($inv->status) }}</span>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 0.6rem; text-align: right; font-weight: 800; color:var(--text-heading); font-size:0.92rem;">
                                {{ $currency }} {{ number_format($inv->amount, 2) }}
                            </td>
                            <td style="padding: 0.85rem 0.6rem; text-align: right;">
                                <div style="display:inline-flex; gap:0.4rem; justify-content:flex-end;">
                                    <a href="/invoices/{{ $inv->id }}/view" target="_blank" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.8rem; text-decoration: none; border-radius:6px; font-weight:600; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <ion-icon name="eye-outline"></ion-icon> View
                                    </a>
                                    @if($link->allow_downloads)
                                        <a href="/invoices/{{ $inv->id }}/pdf" target="_blank" class="btn btn-primary-gradient" style="padding:0.35rem 0.75rem; font-size:0.8rem; text-decoration: none; border-radius:6px; font-weight:600; display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <ion-icon name="download-outline"></ion-icon> PDF
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- ==================== TAB 3: PAYMENTS ==================== -->
<div id="content-payments" class="share-tab-content" style="display:none;">
    <div class="portal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem;">
            <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-heading); margin:0; display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="card-outline" style="color:var(--success);"></ion-icon> Payments Received Ledger
            </h2>
            <div style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">
                {{ $payments->count() }} {{ Str::plural('Payment', $payments->count()) }}
            </div>
        </div>

        @if($payments->isEmpty())
            <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                <ion-icon name="receipt-outline" style="font-size:2.8rem; opacity:0.3; margin-bottom:0.5rem;"></ion-icon><br>
                No payment receipts logged yet.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; margin:0;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); color: var(--text-muted); font-size:0.8rem; text-transform:uppercase;">
                            <th style="padding: 0.85rem 0.6rem;">Payment Date</th>
                            <th style="padding: 0.85rem 0.6rem;">Payment Method</th>
                            <th style="padding: 0.85rem 0.6rem; text-align: right;">Amount Settled</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $pay)
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.85rem 0.6rem; font-size:0.9rem; font-weight:600; color:var(--text-heading);">{{ $pay->payment_date }}</td>
                            <td style="padding: 0.85rem 0.6rem; font-size:0.88rem; color:var(--text-muted);">{{ ucfirst($pay->payment_mode ?? 'Normal') }}</td>
                            <td style="padding: 0.85rem 0.6rem; text-align: right; font-weight: 800; color: var(--success); font-size:0.95rem;">
                                {{ $pay->currency ?? $currency }} {{ number_format($pay->total_amount, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- ==================== TAB 4: DOCUMENTS ==================== -->
<div id="content-documents" class="share-tab-content" style="display:none;">
    <div class="portal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem;">
            <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-heading); margin:0; display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="folder-open-outline" style="color:var(--primary);"></ion-icon> Project Documents Repository
            </h2>
            <div style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">
                {{ $documents->count() }} {{ Str::plural('Document', $documents->count()) }}
            </div>
        </div>

        @if($documents->isEmpty())
            <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                <ion-icon name="document-attach-outline" style="font-size:2.8rem; opacity:0.3; margin-bottom:0.5rem;"></ion-icon><br>
                No project documents uploaded yet.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; margin:0;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); color: var(--text-muted); font-size:0.8rem; text-transform:uppercase;">
                            <th style="padding: 0.85rem 0.6rem;">Document Title</th>
                            <th style="padding: 0.85rem 0.6rem;">Category</th>
                            <th style="padding: 0.85rem 0.6rem;">Upload Date</th>
                            <th style="padding: 0.85rem 0.6rem; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $doc)
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.85rem 0.6rem; font-weight: 700; color:var(--text-heading); font-size:0.9rem;">
                                {{ $doc->title }}
                            </td>
                            <td style="padding: 0.85rem 0.6rem; font-size:0.85rem;">
                                <span class="metric-pill" style="background:#e0f2fe; color:#0369a1;">{{ ucfirst($doc->category ?? 'General') }}</span>
                            </td>
                            <td style="padding: 0.85rem 0.6rem; font-size:0.88rem; color:var(--text-muted);">
                                {{ \Carbon\Carbon::parse($doc->created_at)->format('Y-m-d') }}
                            </td>
                            <td style="padding: 0.85rem 0.6rem; text-align: right;">
                                @if($doc->file_path)
                                    <a href="/storage/{{ $doc->file_path }}" target="_blank" class="btn btn-outline" style="padding:0.3rem 0.75rem; font-size:0.8rem; text-decoration: none; border-radius:6px; font-weight:600; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <ion-icon name="download-outline"></ion-icon> View Document
                                    </a>
                                @else
                                    <span class="text-muted" style="font-size:0.8rem;">No File</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- ==================== TAB 5: CR DOCUMENTS ==================== -->
<div id="content-cr" class="share-tab-content" style="display:none;">
    <div class="portal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem;">
            <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--text-heading); margin:0; display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="git-pull-request-outline" style="color:#b45309;"></ion-icon> Project Change Requests (CR)
            </h2>
            <div style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">
                {{ $change_requests->count() }} {{ Str::plural('Change Request', $change_requests->count()) }}
            </div>
        </div>

        @if($change_requests->isEmpty())
            <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                <ion-icon name="git-pull-request-outline" style="font-size:2.8rem; opacity:0.3; margin-bottom:0.5rem;"></ion-icon><br>
                No change request documents recorded for this project.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; margin:0;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border); color: var(--text-muted); font-size:0.8rem; text-transform:uppercase;">
                            <th style="padding: 0.85rem 0.6rem;">Request Date</th>
                            <th style="padding: 0.85rem 0.6rem;">Scope Description</th>
                            <th style="padding: 0.85rem 0.6rem; text-align: center;">Status</th>
                            <th style="padding: 0.85rem 0.6rem; text-align: right;">Amount</th>
                            <th style="padding: 0.85rem 0.6rem; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($change_requests as $cr)
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.85rem 0.6rem; font-size:0.88rem; color:var(--text-heading);">
                                {{ \Carbon\Carbon::parse($cr->created_at)->format('Y-m-d') }}
                            </td>
                            <td style="padding: 0.85rem 0.6rem; font-size:0.88rem; color:var(--text-heading);">
                                <div style="font-weight:700;">{{ $cr->description }}</div>
                                
                                <!-- Attached Files -->
                                @if(!empty($cr->attachments) && $cr->attachments->count() > 0)
                                    <div style="margin-top:0.4rem; display:flex; flex-wrap:wrap; gap:0.4rem;">
                                        @foreach($cr->attachments as $att)
                                            <a href="/storage/{{ $att->file_path }}" target="_blank" class="btn btn-outline" style="padding:0.15rem 0.5rem; font-size:0.75rem; border-radius:4px; text-decoration:none; display:inline-flex; align-items:center; gap:0.25rem;">
                                                <ion-icon name="document-outline" style="color:var(--primary);"></ion-icon> {{ $att->file_name }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- External Links -->
                                @if(!empty($cr->links) && count($cr->links) > 0)
                                    <div style="margin-top:0.4rem; display:flex; flex-wrap:wrap; gap:0.4rem;">
                                        @foreach($cr->links as $l)
                                            <a href="{{ $l['url'] }}" target="_blank" class="btn btn-outline" style="padding:0.15rem 0.5rem; font-size:0.75rem; border-radius:4px; text-decoration:none; display:inline-flex; align-items:center; gap:0.25rem; background:#f3e8ff; color:#6b21a8; border-color:#d8b4fe;">
                                                <ion-icon name="open-outline"></ion-icon> {{ $l['title'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 0.6rem; text-align:center;">
                                <span class="metric-pill" style="background:#dcfce7; color:#15803d;">{{ ucfirst($cr->status) }}</span>
                            </td>
                            <td style="padding: 0.85rem 0.6rem; text-align: right; font-weight:800; font-size:0.92rem; color:var(--text-heading);">
                                {{ $currency }} {{ number_format($cr->amount, 2) }}
                            </td>
                            <td style="padding: 0.85rem 0.6rem; text-align: right;">
                                <button type="button" class="btn btn-outline" onclick='openShareCRModal(@json($cr))' style="padding:0.3rem 0.65rem; font-size:0.8rem; border-radius:6px; font-weight:600; display:inline-flex; align-items:center; gap:0.3rem;">
                                    <ion-icon name="eye-outline"></ion-icon> View
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script>
function switchShareTab(tabName) {
    document.querySelectorAll('.interactive-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.share-tab-content').forEach(c => c.style.display = 'none');

    const activeBtn = document.getElementById('tab-' + tabName);
    const activeContent = document.getElementById('content-' + tabName);

    if (activeBtn) activeBtn.classList.add('active');
    if (activeContent) activeContent.style.display = 'block';

    window.location.hash = tabName;
}

// Auto open tab from URL hash if present
document.addEventListener("DOMContentLoaded", function() {
    const hash = window.location.hash.replace('#', '');
    if (hash && ['overview', 'invoices', 'payments', 'documents', 'cr'].includes(hash)) {
        switchShareTab(hash);
    }
});
</script>

<!-- Client Share CR Details Modal -->
<div id="shareCRModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:var(--bg-card); max-width:600px; width:90%; border-radius:16px; padding:1.75rem; box-shadow:0 20px 40px rgba(0,0,0,0.15); border:1px solid var(--border-light);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem;">
            <h3 style="margin:0; font-size:1.25rem; font-weight:800; color:var(--text-heading); display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="git-pull-request-outline" style="color:var(--primary);"></ion-icon> Change Request Specification
            </h3>
            <button type="button" onclick="closeShareCRModal()" style="background:none; border:none; font-size:1.5rem; color:var(--text-muted); cursor:pointer;">&times;</button>
        </div>

        <div style="background:var(--bg-page); padding:1.25rem; border-radius:12px; border:1px solid var(--border-light); margin-bottom:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                <div>
                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted);">Scope Description</div>
                    <div style="font-weight:700; font-size:1.1rem; color:var(--text-heading); margin-top:0.2rem;" id="shareCRDescription"></div>
                </div>
                <div style="text-align:right; min-width:120px;">
                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted);">Amount</div>
                    <div style="font-weight:800; font-size:1.15rem; color:var(--primary);" id="shareCRAmount"></div>
                </div>
            </div>
        </div>

        <!-- Attached Documents -->
        <div style="margin-bottom:1.25rem;">
            <div style="font-size:0.85rem; font-weight:700; color:var(--text-heading); margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="attach-outline" style="color:var(--primary);"></ion-icon> Attached Specification Files
            </div>
            <div id="shareCRAttachments"></div>
        </div>

        <!-- External Links -->
        <div style="margin-bottom:1.5rem;">
            <div style="font-size:0.85rem; font-weight:700; color:var(--text-heading); margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="link-outline" style="color:var(--primary);"></ion-icon> External Documentation & Design Links
            </div>
            <div id="shareCRLinks"></div>
        </div>

        <div style="text-align:right;">
            <button type="button" class="btn btn-outline" onclick="closeShareCRModal()" style="border-radius:8px; font-weight:600; padding:0.5rem 1.25rem;">Close</button>
        </div>
    </div>
</div>

<script>
function openShareCRModal(cr) {
    document.getElementById('shareCRDescription').innerText = cr.description;
    document.getElementById('shareCRAmount').innerText = '{{ $currency }} ' + parseFloat(cr.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});

    const attContainer = document.getElementById('shareCRAttachments');
    attContainer.innerHTML = '';
    if (cr.attachments && cr.attachments.length > 0) {
        let html = '<div style="display:flex; flex-direction:column; gap:0.4rem;">';
        cr.attachments.forEach(att => {
            html += `<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-page); padding:0.5rem 0.8rem; border-radius:8px; font-size:0.85rem; border:1px solid var(--border-light);">
                        <span><ion-icon name="document-outline" style="vertical-align:middle; color:var(--primary);"></ion-icon> ${att.file_name}</span>
                        <a href="/storage/${att.file_path}" target="_blank" class="btn btn-sm btn-outline" style="font-size:0.78rem; padding:0.25rem 0.6rem; text-decoration:none;">Download File</a>
                     </div>`;
        });
        html += '</div>';
        attContainer.innerHTML = html;
    } else {
        attContainer.innerHTML = '<span style="font-size:0.85rem; color:var(--text-muted);">No attached file documents.</span>';
    }

    const linkContainer = document.getElementById('shareCRLinks');
    linkContainer.innerHTML = '';
    if (cr.links && cr.links.length > 0) {
        let html = '<div style="display:flex; flex-direction:column; gap:0.4rem;">';
        cr.links.forEach(l => {
            html += `<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-page); padding:0.5rem 0.8rem; border-radius:8px; font-size:0.85rem; border:1px solid var(--border-light);">
                        <span><ion-icon name="link-outline" style="vertical-align:middle; color:var(--primary);"></ion-icon> <strong>${l.title}</strong></span>
                        <a href="${l.url}" target="_blank" class="btn btn-sm btn-outline" style="font-size:0.78rem; padding:0.25rem 0.6rem; text-decoration:none; background:#f3e8ff; color:#6b21a8; border-color:#d8b4fe;">Open Link</a>
                     </div>`;
        });
        html += '</div>';
        linkContainer.innerHTML = html;
    } else {
        linkContainer.innerHTML = '<span style="font-size:0.85rem; color:var(--text-muted);">No external documentation links.</span>';
    }

    document.getElementById('shareCRModal').style.display = 'flex';
}

function closeShareCRModal() {
    document.getElementById('shareCRModal').style.display = 'none';
}
</script>
@endsection
