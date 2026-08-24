@extends('layouts.app')
@section('title', 'Party Loan Payables & Facilities Report')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Loan Management</h2>
    <nav class="nav-links">
        <a href="/loans" class="nav-link {{ request()->is('loans') ? 'active' : '' }}">
            <ion-icon name="cash-outline"></ion-icon> Active Loans
        </a>
        <a href="/loans/schedules" class="nav-link {{ request()->is('loans/schedules') ? 'active' : '' }}">
            <ion-icon name="calendar-outline"></ion-icon> Schedules
        </a>
        <a href="/loans/settlements" class="nav-link {{ request()->is('loans/settlements') ? 'active' : '' }}">
            <ion-icon name="checkmark-done-circle-outline"></ion-icon> Settlements
        </a>
        <a href="/loans/party-report" class="nav-link {{ request()->is('loans/party-report') ? 'active' : '' }}">
            <ion-icon name="pie-chart-outline"></ion-icon> Party Payables & Paids
        </a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Party Loan Payables & Facilities</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Facility breakdown of principal borrowed, settlements, and net payables grouped by party. Expand any party to view its individual loan facilities.</p>
    </div>
</header>

<!-- Overall Summary KPI Tiles -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <!-- Tile 1: Total Borrowed -->
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Total Facilities Borrowed</div>
            <ion-icon name="wallet-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($overallBorrowed, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">{{ $totalFacilitiesCount }} Facilities across {{ $partyReports->total() }} Parties</div>
    </div>

    <!-- Tile 2: Total Settlements Paid -->
    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Total Settlements Paid (Paids)</div>
            <ion-icon name="checkmark-done-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($overallPaids, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">All Principal Repayments + Interest Settled</div>
    </div>

    <!-- Tile 3: Net Outstanding Debt -->
    <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #f43f5e 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Net Outstanding Debt (Payables)</div>
            <ion-icon name="trending-up-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($overallPayables, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Active Principal + Pending Interest</div>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="toolbar" style="margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div class="toolbar-left"></div>
    <div class="toolbar-right">
        <form method="GET" action="/loans/party-report" style="margin:0; display:flex; gap:0.5rem; align-items:center;">
            <div class="search-input">
                <ion-icon name="search-outline"></ion-icon>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search parties or lenders...">
            </div>
            @if(request('search'))
                <a href="/loans/party-report" class="btn btn-outline" style="padding:0.4rem 0.75rem; font-size:0.85rem;" title="Clear Filters">Clear</a>
            @endif
        </form>
    </div>
</div>

<!-- Party Accordion List -->
<div class="party-accordion-container" style="display:flex; flex-direction:column; gap:1rem;">
    @foreach($partyReports as $rep)
    <div class="card party-accordion-card" id="party-card-{{ $rep->key }}" style="padding:0; border:1px solid var(--border); border-radius:12px; overflow:hidden; background:var(--bg-card); transition: box-shadow 0.2s ease;">
        <!-- Accordion Header -->
        <div class="party-accordion-header" 
             onclick="togglePartyAccordion('{{ $rep->key }}', '{{ $rep->party_id ?: 'null' }}', '{{ addslashes($rep->lender_name) }}')"
             style="padding:1.25rem 1.5rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; user-select:none; background:var(--bg-card);">
            
            <!-- Left: Party Info -->
            <div style="display:flex; align-items:center; gap:1rem; min-width:240px;">
                <div class="caret-icon-wrapper" id="caret-{{ $rep->key }}" style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:var(--bg-page); border:1px solid var(--border); transition:transform 0.25s ease; color:var(--text-heading);">
                    <ion-icon name="chevron-down-outline" style="font-size:1.1rem;"></ion-icon>
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <strong style="color:var(--text-heading); font-size:1.1rem;">{{ $rep->party_name }}</strong>
                        <span class="badge badge-draft" style="font-size:0.75rem; font-weight:700;">{{ $rep->loan_count }} {{ Str::plural('Facility', $rep->loan_count) }}</span>
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.2rem; display:flex; gap:0.75rem; align-items:center;">
                        <span><span style="color:var(--success); font-weight:600;">●</span> {{ $rep->active_count }} Active</span>
                        @if($rep->settled_count > 0)
                            <span><span style="color:#64748b; font-weight:600;">●</span> {{ $rep->settled_count }} Settled</span>
                        @endif
                        @if($rep->pending_count > 0)
                            <span><span style="color:#f59e0b; font-weight:600;">●</span> {{ $rep->pending_count }} Pending</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right: Metric Figures Summary -->
            <div style="display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap;">
                <!-- Total Borrowed -->
                <div style="text-align:right;">
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">Total Borrowed</div>
                    <div style="font-weight:700; font-size:0.95rem; color:var(--text-heading);">
                        {{ $rep->currency }} {{ number_format($rep->total_borrowed, 2) }}
                    </div>
                </div>

                <!-- Principal Repaid -->
                <div style="text-align:right;">
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">Principal Repaid</div>
                    <div style="font-weight:600; font-size:0.95rem; color:var(--text-main);">
                        {{ $rep->currency }} {{ number_format($rep->total_principal_repaid, 2) }}
                    </div>
                </div>

                <!-- Interest Paid -->
                <div style="text-align:right;">
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:600;">Interest Paid</div>
                    <div style="font-weight:600; font-size:0.95rem; color:var(--text-main);">
                        {{ $rep->currency }} {{ number_format($rep->total_interest_paid, 2) }}
                    </div>
                </div>

                <!-- Total Paid (Paids) -->
                <div style="text-align:right;">
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--success); font-weight:700;">Total Paid (Paids)</div>
                    <div style="font-weight:700; font-size:1rem; color:var(--success);">
                        {{ $rep->currency }} {{ number_format($rep->total_paids, 2) }}
                    </div>
                </div>

                <!-- Net Payable (Payables) -->
                <div style="text-align:right; min-width:130px;">
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--danger); font-weight:700;">Net Payable</div>
                    <div style="font-weight:800; font-size:1.1rem; color:{{ $rep->total_payables > 0 ? 'var(--danger)' : 'var(--text-muted)' }};">
                        {{ $rep->currency }} {{ number_format($rep->total_payables, 2) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Accordion Body: Facilities Drawer (Lazy Loaded) -->
        <div class="party-facilities-drawer" id="drawer-{{ $rep->key }}" style="display:none; border-top:1px solid var(--border); background:var(--bg-page); padding:1.25rem 1.5rem;">
            <!-- Loading Placeholder -->
            <div id="loader-{{ $rep->key }}" style="text-align:center; padding:1.75rem; color:var(--text-muted); font-size:0.9rem;">
                <ion-icon name="sync-outline" class="spin" style="font-size:1.5rem; vertical-align:middle; margin-right:0.4rem; color:var(--primary);"></ion-icon>
                Loading facilities for {{ $rep->party_name }}...
            </div>

            <!-- Facilities Container (Injected via JS) -->
            <div id="content-{{ $rep->key }}" style="display:none;"></div>
        </div>
    </div>
    @endforeach

    @if($partyReports->isEmpty())
    <div class="card" style="text-align:center; padding:3rem; color:var(--text-muted);">
        <ion-icon name="pie-chart-outline" style="font-size:3rem; opacity:0.4; margin-bottom:0.75rem;"></ion-icon>
        <h3 style="margin:0 0 0.5rem 0; color:var(--text-heading);">No Party Records Found</h3>
        <p style="margin:0; font-size:0.9rem;">No loan facilities match your search query.</p>
    </div>
    @endif
</div>

<!-- Pagination Controls -->
@if(!$partyReports->isEmpty())
<div style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="font-size:0.85rem; color:var(--text-muted);">
        Showing {{ $partyReports->firstItem() ?? 0 }} to {{ $partyReports->lastItem() ?? 0 }} of {{ $partyReports->total() }} parties
    </div>
    <div>
        {{ $partyReports->links() }}
    </div>
</div>
@endif

<style>
@keyframes spinAnimation {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.spin {
    animation: spinAnimation 1s linear infinite;
    display: inline-block;
}
.party-accordion-card:hover {
    box-shadow: var(--shadow-card);
}
.facility-row:hover {
    background-color: var(--bg-card) !important;
}
</style>

<script>
const loadedPartyFacilities = {};

async function togglePartyAccordion(key, partyId, lenderName) {
    const drawer = document.getElementById('drawer-' + key);
    const caret = document.getElementById('caret-' + key);
    const loader = document.getElementById('loader-' + key);
    const content = document.getElementById('content-' + key);

    if (!drawer) return;

    const isOpen = drawer.style.display === 'block';

    if (isOpen) {
        drawer.style.display = 'none';
        caret.style.transform = 'rotate(0deg)';
    } else {
        drawer.style.display = 'block';
        caret.style.transform = 'rotate(180deg)';

        // Fetch facilities if not already cached
        if (!loadedPartyFacilities[key]) {
            loader.style.display = 'block';
            content.style.display = 'none';

            try {
                let url = '/loans/party-report/loans?';
                if (partyId && partyId !== 'null') {
                    url += 'party_id=' + encodeURIComponent(partyId);
                } else {
                    url += 'lender_name=' + encodeURIComponent(lenderName);
                }

                const response = await fetch(url);
                const data = await response.json();
                loadedPartyFacilities[key] = data.facilities || [];
                renderFacilitiesTable(key, loadedPartyFacilities[key]);
            } catch (err) {
                console.error('Error fetching party facilities:', err);
                loader.innerHTML = '<span style="color:var(--danger);"><ion-icon name="alert-circle-outline"></ion-icon> Failed to load facilities. Please try again.</span>';
            }
        }
    }
}

function renderFacilitiesTable(key, facilities) {
    const loader = document.getElementById('loader-' + key);
    const content = document.getElementById('content-' + key);

    loader.style.display = 'none';
    content.style.display = 'block';

    if (!facilities || facilities.length === 0) {
        content.innerHTML = '<div style="text-align:center; padding:1.5rem; color:var(--text-muted); font-size:0.85rem;">No facilities recorded for this party.</div>';
        return;
    }

    let html = `
        <div style="overflow-x:auto;">
            <table class="data-table" style="margin:0; width:100%; border-collapse:collapse; background:var(--bg-card); border-radius:8px; border:1px solid var(--border);">
                <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
                    <tr>
                        <th style="padding:0.75rem 1rem; text-align:left; font-size:0.78rem; color:var(--text-muted);">Facility ID / Lender</th>
                        <th style="padding:0.75rem 1rem; text-align:center; font-size:0.78rem; color:var(--text-muted);">Status</th>
                        <th style="padding:0.75rem 1rem; text-align:left; font-size:0.78rem; color:var(--text-muted);">Claimed / Due</th>
                        <th style="padding:0.75rem 1rem; text-align:right; font-size:0.78rem; color:var(--text-muted);">Principal Borrowed</th>
                        <th style="padding:0.75rem 1rem; text-align:right; font-size:0.78rem; color:var(--text-muted);">Principal Repaid</th>
                        <th style="padding:0.75rem 1rem; text-align:right; font-size:0.78rem; color:var(--text-muted);">Interest Paid</th>
                        <th style="padding:0.75rem 1rem; text-align:right; font-size:0.78rem; color:var(--success);">Total Paid</th>
                        <th style="padding:0.75rem 1rem; text-align:right; font-size:0.78rem; color:var(--danger);">Outstanding Payable</th>
                        <th style="padding:0.75rem 1rem; text-align:center; font-size:0.78rem; color:var(--text-muted);">Action</th>
                    </tr>
                </thead>
                <tbody>
    `;

    facilities.forEach(loan => {
        let statusBadge = '';
        if (loan.status === 'active') {
            statusBadge = '<span class="badge badge-active">Active</span>';
        } else if (loan.status === 'settled') {
            statusBadge = '<span class="badge" style="background:#dcfce7; color:#166534; font-weight:600;">Settled</span>';
        } else if (loan.status === 'pending') {
            statusBadge = '<span class="badge" style="background:#e2e8f0; color:#334155; font-weight:600;">Pending</span>';
        } else {
            statusBadge = `<span class="badge badge-draft">${loan.status}</span>`;
        }

        const fmt = (num) => Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        html += `
            <tr class="facility-row" style="border-bottom:1px solid var(--border-light); transition:background-color 0.15s ease;">
                <td style="padding:0.8rem 1rem;">
                    <a href="/loans/${loan.id}" target="_blank" rel="noopener noreferrer" style="color:var(--primary); font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem; font-size:0.9rem;">
                        <ion-icon name="document-text-outline"></ion-icon>
                        Facility #${loan.id} - ${escapeHtml(loan.lender_name)}
                    </a>
                    ${loan.purpose ? `<div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">${escapeHtml(loan.purpose)}</div>` : ''}
                </td>
                <td style="padding:0.8rem 1rem; text-align:center;">
                    ${statusBadge}
                </td>
                <td style="padding:0.8rem 1rem; font-size:0.82rem;">
                    <div><span style="color:var(--text-muted);">Claimed:</span> ${loan.claimed_date || '-'}</div>
                    ${loan.maturity_date ? `<div style="color:var(--text-muted); font-size:0.75rem;">Due: ${loan.maturity_date}</div>` : ''}
                </td>
                <td style="padding:0.8rem 1rem; text-align:right; font-weight:600; font-size:0.88rem; color:var(--text-heading);">
                    ${loan.currency} ${fmt(loan.principal_amount)}
                </td>
                <td style="padding:0.8rem 1rem; text-align:right; font-size:0.85rem;">
                    ${loan.currency} ${fmt(loan.principal_repaid)}
                </td>
                <td style="padding:0.8rem 1rem; text-align:right; font-size:0.85rem;">
                    ${loan.currency} ${fmt(loan.interest_paid)}
                </td>
                <td style="padding:0.8rem 1rem; text-align:right; font-weight:700; color:var(--success); font-size:0.88rem;">
                    ${loan.currency} ${fmt(loan.total_paid)}
                </td>
                <td style="padding:0.8rem 1rem; text-align:right; font-weight:800; color:${loan.total_outstanding > 0 ? 'var(--danger)' : 'var(--text-muted)'}; font-size:0.92rem;">
                    ${loan.currency} ${fmt(loan.total_outstanding)}
                </td>
                <td style="padding:0.8rem 1rem; text-align:center;">
                    <a href="/loans/${loan.id}" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.8rem; display:inline-flex; align-items:center; gap:0.3rem; text-decoration:none;">
                        View <ion-icon name="open-outline"></ion-icon>
                    </a>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    content.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>
@endsection
