@extends('reports.layout')
@section('title', 'Party Payables & Full Ledger Report')

@section('report-content')
<style>
.party-kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.party-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}
.party-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    color: white;
    font-weight: 800;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.role-pill {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    display: inline-block;
    margin-right: 0.25rem;
    margin-bottom: 0.2rem;
}
.role-pill-client { background: #dbeafe; color: #1e40af; }
.role-pill-vendor { background: #d1fae5; color: #065f46; }
.role-pill-lender { background: #fef3c7; color: #92400e; }
.role-pill-partner { background: #f3e8ff; color: #6b21a8; }
.role-pill-default { background: var(--bg-page); color: var(--text-muted); border: 1px solid var(--border); }

.role-tab-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-page);
    border: 1px solid var(--border);
    text-decoration: none;
    transition: all 0.2s ease;
}
.role-tab-btn:hover, .role-tab-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}
</style>

<!-- Header -->
<header style="margin-bottom: 1.75rem; display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.8rem; font-weight:800; color:var(--text-heading); margin:0; letter-spacing:-0.02em;">
            Party Payables & Financial Ledger
        </h1>
        <p class="subtitle" style="margin-top:0.35rem; color:var(--text-muted); font-size:0.92rem;">
            Consolidated 360° financial position, transactions, payables, and settlements across all registered parties.
        </p>
    </div>
    <div style="display:flex; gap:0.5rem;">
        <button class="btn btn-outline" onclick="window.print()">
            <ion-icon name="print-outline" style="vertical-align:middle; margin-right:0.3rem;"></ion-icon> Print Overview
        </button>
    </div>
</header>

<!-- Consolidated Metric Cards Grid -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="party-kpi-card" style="border-left:4px solid #2563eb;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Parties Enrolled</div>
        <div style="font-size:1.65rem; font-weight:800; color:var(--text-heading); margin-top:0.3rem;">{{ $partySummaries->count() }}</div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">Active Accounts Statement</div>
    </div>

    <div class="party-kpi-card" style="border-left:4px solid #8b5cf6;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Contract Value</div>
        <div style="font-size:1.65rem; font-weight:800; color:#7c3aed; margin-top:0.3rem;">LKR {{ number_format($partySummaries->sum('total_contract_value'), 2) }}</div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">Active Project Commitments</div>
    </div>
    
    <div class="party-kpi-card" style="border-left:4px solid #3b82f6;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Invoiced (AR)</div>
        <div style="font-size:1.65rem; font-weight:800; color:#2563eb; margin-top:0.3rem;">LKR {{ number_format($partySummaries->sum('total_invoiced'), 2) }}</div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">Receivable Client Billings</div>
    </div>

    <div class="party-kpi-card" style="border-left:4px solid #10b981;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Collections & Paids Settled</div>
        <div style="font-size:1.65rem; font-weight:800; color:#059669; margin-top:0.3rem;">LKR {{ number_format($partySummaries->sum('total_collected') + $partySummaries->sum('total_paids'), 2) }}</div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">Settled Funds Flow</div>
    </div>

    <div class="party-kpi-card" style="border-left:4px solid #ef4444;">
        <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Net Payables</div>
        <div style="font-size:1.65rem; font-weight:800; color:#dc2626; margin-top:0.3rem;">LKR {{ number_format($partySummaries->sum('total_payables'), 2) }}</div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">AP Bills + Loans + Commissions</div>
    </div>
</div>

<!-- Navigation Filter Tabs & Search Bar -->
<div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; background:var(--bg-card); border:1px solid var(--border); border-radius:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        
        <!-- Role Filter Tabs -->
        <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
            <a href="{{ route('reports.party_ledger', ['role' => 'all']) }}" class="role-tab-btn {{ $roleFilter == 'all' || !$roleFilter ? 'active' : '' }}">
                All Roles ({{ $partySummaries->count() }})
            </a>
            <a href="{{ route('reports.party_ledger', ['role' => 'client']) }}" class="role-tab-btn {{ $roleFilter == 'client' ? 'active' : '' }}">
                Clients (AR)
            </a>
            <a href="{{ route('reports.party_ledger', ['role' => 'vendor']) }}" class="role-tab-btn {{ $roleFilter == 'vendor' ? 'active' : '' }}">
                Vendors (AP)
            </a>
            <a href="{{ route('reports.party_ledger', ['role' => 'lender']) }}" class="role-tab-btn {{ $roleFilter == 'lender' ? 'active' : '' }}">
                Lenders & Loans
            </a>
            <a href="{{ route('reports.party_ledger', ['role' => 'partner']) }}" class="role-tab-btn {{ $roleFilter == 'partner' ? 'active' : '' }}">
                Project Partners
            </a>
        </div>

        <!-- Real-Time Search Bar -->
        <div style="position:relative; width:280px;">
            <ion-icon name="search-outline" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.1rem;"></ion-icon>
            <input type="text" id="partyTableSearch" class="form-control" placeholder="Search party name or contact..." onkeyup="filterPartyMasterTable(this.value)" style="padding-left:2.4rem; font-size:0.85rem; border-radius:8px;">
        </div>

    </div>
</div>

<!-- Master Party Summary Table -->
<div class="card" style="padding:0; overflow-x:auto; border-radius:14px; border:1px solid var(--border); box-shadow:0 4px 20px rgba(0,0,0,0.02);">
    <table class="data-table" id="masterPartyTable" style="width:100%; margin:0; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:1rem 1.25rem; text-align:left; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted);">Party Profile</th>
                <th style="padding:1rem; text-align:left; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted);">Roles & Types</th>
                <th style="padding:1rem; text-align:right; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:#7c3aed;">Project Contract Value</th>
                <th style="padding:1rem; text-align:right; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted);">Client Invoiced</th>
                <th style="padding:1rem; text-align:right; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted);">Collections</th>
                <th style="padding:1rem; text-align:right; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted);">Payables (AP/Loans)</th>
                <th style="padding:1rem; text-align:right; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--success);">Paids Settled</th>
                <th style="padding:1rem; text-align:right; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-heading);">Net Balance Position</th>
                <th style="padding:1rem 1.25rem; text-align:center; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-muted);">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($partySummaries as $ps)
            @php
                $initial = strtoupper(substr($ps->name, 0, 1));
                $roles = explode(',', $ps->types);
            @endphp
            <tr class="party-row-item" data-search="{{ strtolower($ps->name . ' ' . $ps->contact_person . ' ' . $ps->types) }}" style="border-bottom: 1px solid var(--border-light); transition:background 0.15s ease;">
                <td style="padding:1rem 1.25rem;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="party-avatar">{{ $initial }}</div>
                        <div>
                            <strong style="color:var(--text-heading); font-size:0.95rem; display:block;">{{ $ps->name }}</strong>
                            <span style="font-size:0.78rem; color:var(--text-muted);">{{ $ps->contact_person ?: 'No contact person' }}</span>
                        </div>
                    </div>
                </td>
                
                <td style="padding:1rem;">
                    @foreach($roles as $role)
                        @php
                            $roleClean = trim($role);
                            $pillClass = 'role-pill-default';
                            if (str_contains($roleClean, 'client')) $pillClass = 'role-pill-client';
                            elseif (str_contains($roleClean, 'vendor')) $pillClass = 'role-pill-vendor';
                            elseif (str_contains($roleClean, 'lender') || str_contains($roleClean, 'director') || str_contains($roleClean, 'bank')) $pillClass = 'role-pill-lender';
                            elseif (str_contains($roleClean, 'partner')) $pillClass = 'role-pill-partner';
                        @endphp
                        <span class="role-pill {{ $pillClass }}">{{ $roleClean }}</span>
                    @endforeach
                </td>

                <td style="padding:1rem; text-align:right;">
                    @if($ps->total_contract_value > 0)
                        <div style="font-weight:700; color:#7c3aed; font-size:0.9rem;">LKR {{ number_format($ps->total_contract_value, 2) }}</div>
                        @if($ps->contract_orig_str)
                            <div style="font-size:0.72rem; color:var(--text-muted); font-weight:600;">({{ $ps->contract_orig_str }})</div>
                        @endif
                        @if($ps->unbilled_contract_value > 0)
                            <div style="font-size:0.7rem; color:#d97706; font-weight:700; margin-top:0.15rem;">Unbilled: LKR {{ number_format($ps->unbilled_contract_value, 2) }}</div>
                        @endif
                    @else
                        <span style="color:var(--text-muted);">-</span>
                    @endif
                </td>

                <td style="padding:1rem; text-align:right; font-weight:600; color:var(--text-heading);">
                    {{ $ps->total_invoiced > 0 ? 'LKR ' . number_format($ps->total_invoiced, 2) : '-' }}
                </td>
                
                <td style="padding:1rem; text-align:right; font-weight:600; color:#059669;">
                    {{ $ps->total_collected > 0 ? 'LKR ' . number_format($ps->total_collected, 2) : '-' }}
                </td>
                
                <td style="padding:1rem; text-align:right; font-weight:700; color:#dc2626;">
                    {{ $ps->total_payables > 0 ? 'LKR ' . number_format($ps->total_payables, 2) : '-' }}
                </td>

                <td style="padding:1rem; text-align:right; font-weight:600; color:#059669;">
                    {{ $ps->total_paids > 0 ? 'LKR ' . number_format($ps->total_paids, 2) : '-' }}
                </td>

                <td style="padding:1rem; text-align:right;">
                    @if($ps->net_balance >= 0)
                        <span style="font-weight:800; color:#059669; font-size:0.95rem; background:rgba(16, 185, 129, 0.1); padding:0.25rem 0.6rem; border-radius:6px;">
                            LKR {{ number_format(abs($ps->net_balance), 2) }} DR
                        </span>
                    @else
                        <span style="font-weight:800; color:#dc2626; font-size:0.95rem; background:rgba(239, 68, 68, 0.1); padding:0.25rem 0.6rem; border-radius:6px;">
                            LKR {{ number_format(abs($ps->net_balance), 2) }} CR
                        </span>
                    @endif
                </td>

                <td style="padding:1rem 1.25rem; text-align:center;">
                    <button type="button" class="btn btn-primary-gradient" style="padding:0.4rem 0.85rem; font-size:0.8rem; border-radius:8px;" onclick="openPartyLedgerModal({{ $ps->id }})">

                        Full Statement &rarr;
                    </button>
                </td>
            </tr>
            @endforeach
            
            @if($partySummaries->isEmpty())
            <tr>
                <td colspan="8" style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
                    <ion-icon name="documents-outline" style="font-size:2.5rem; opacity:0.4; margin-bottom:0.5rem;"></ion-icon><br>
                    No financial party records found for the selected filter role.
                </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

<!-- Full View Party Financial Ledger Modal Popup -->
<div class="modal-backdrop" id="partyLedgerModal">
    <div class="modal-card" style="max-width: 980px; width: 94vw; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.3);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
            <div style="display:flex; align-items:center; gap:0.85rem;">
                <div class="party-avatar" id="modalPartyAvatar" style="width:48px; height:48px; font-size:1.3rem;">P</div>
                <div>
                    <h3 class="modal-title" id="ledgerModalTitle" style="font-size: 1.35rem; font-weight: 800; color: var(--text-heading); margin: 0;">Full Financial Ledger Statement</h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0.2rem 0 0 0;" id="ledgerModalSubtitle">Detailed chronological debit/credit transaction history</p>
                </div>
            </div>
            <button type="button" class="btn-close" onclick="closeModal('partyLedgerModal')">&times;</button>
        </div>
        
        <div class="modal-body" style="padding: 1.25rem 0;" id="ledgerModalBody">
            <!-- Dynamically populated by JS -->
        </div>

        <div class="modal-footer" style="border-top: 1px solid var(--border); padding-top: 0.85rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
            <button type="button" class="btn btn-outline" onclick="window.print()">
                <ion-icon name="print-outline" style="vertical-align:middle; margin-right:0.3rem;"></ion-icon> Print Financial Statement
            </button>
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn btn-primary-gradient" onclick="openPartyPaymentModal()">
                    <ion-icon name="cash-outline" style="vertical-align:middle; margin-right:0.3rem;"></ion-icon> Record Settlement / Pay
                </button>
                <button type="button" class="btn btn-primary" onclick="closeModal('partyLedgerModal')">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Record Party Settlement Payment Modal -->
<div class="modal-backdrop" id="recordPartyPaymentModal">
    <div class="modal-card" style="max-width: 680px; width: 92vw; border-radius: 18px;">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
            <div>
                <h3 class="modal-title" id="paymentModalPartyTitle" style="font-size: 1.25rem; font-weight: 800; color: var(--text-heading); margin: 0;">Record Party Settlement Payment</h3>
                <p style="font-size:0.8rem; color:var(--text-muted); margin:0.2rem 0 0 0;">Record funds received or paid with multi-mode payment allocation</p>
            </div>
            <button type="button" class="btn-close" onclick="closeModal('recordPartyPaymentModal')">&times;</button>
        </div>
        <form action="{{ route('reports.party_settlement') }}" method="POST">
            @csrf
            <input type="hidden" name="party_id" id="payment_modal_party_id">
            
            <div class="modal-body" style="padding: 1.25rem 0;">
                <div class="form-group">
                    <label class="form-label font-medium">Settlement Transaction Type *</label>
                    <select name="settlement_type" id="payment_modal_settlement_type" class="form-control" required>
                        <option value="receivable_collection">Client AR Collection (Received from Client)</option>
                        <option value="vendor_bill_payment">Vendor AP Bill Payment (Paid to Vendor)</option>
                        <option value="loan_repayment">Loan Facility Repayment / Interest Settlement</option>
                        <option value="commission_payout">Partner Commission Payout</option>
                    </select>
                </div>

                <div class="form-row" style="margin-top: 1.25rem;">
                    <div class="form-col">
                        <label class="form-label font-medium">Settlement Amount (LKR) *</label>
                        <x-amount-input name="amount" id="payment_modal_amount" required="true" placeholder="0.00" />
                    </div>
                    <div class="form-col">
                        <label class="form-label font-medium">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label font-medium">Reference / Receipt Number</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="e.g. REC-2026-001 or SETTLE-99">
                </div>

                <!-- Reusable Multi Payment Modes Component -->
                <x-payment-modes />

                <div class="form-group" style="margin-top: 1.25rem;">
                    <label class="form-label font-medium">Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Additional payment settlement notes..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer" style="border-top:1px solid var(--border); padding-top:0.85rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('recordPartyPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">
                    <ion-icon name="checkmark-circle-outline" style="vertical-align:middle; margin-right:0.3rem;"></ion-icon> Save Settlement Payment
                </button>
            </div>
        </form>
    </div>
</div>


<script>
const partySummariesData = @json($partySummaries);
const allPartyTimelinesData = @json($allPartyTimelines);
const selectedPartyIdOnLoad = "{{ $selectedPartyId }}";
let currentModalParty = null;

function filterPartyMasterTable(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('#masterPartyTable .party-row-item').forEach(row => {
        const searchData = row.getAttribute('data-search') || '';
        if (searchData.includes(q) || !q) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openPartyLedgerModal(partyId) {
    partyId = parseInt(partyId);
    const party = partySummariesData.find(p => p.id === partyId);
    if (!party) return;
    currentModalParty = party;

    const timeline = allPartyTimelinesData[partyId] || [];


    document.getElementById('modalPartyAvatar').innerText = party.name.charAt(0).toUpperCase();
    document.getElementById('ledgerModalTitle').innerText = party.name + ' — Financial Ledger Statement';
    document.getElementById('ledgerModalSubtitle').innerText = 'Roles: ' + party.types.replace(/,/g, ', ') + ' | Contact: ' + (party.contact_person || 'N/A') + ' (' + (party.phone || party.email || 'N/A') + ')';

    const netBalFormatted = 'LKR ' + (Math.abs(party.net_balance)).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + (party.net_balance >= 0 ? 'DR (Receivable)' : 'CR (Payable)');
    const netBalColor = party.net_balance >= 0 ? '#059669' : '#dc2626';

    // Calculate original currency totals for this party
    const currMap = {};
    timeline.forEach(tx => {
        const c = tx.currency || 'LKR';
        if (!currMap[c]) currMap[c] = { debit: 0, credit: 0, sym: tx.currency_symbol || c };
        currMap[c].debit += (tx.original_debit || 0);
        currMap[c].credit += (tx.original_credit || 0);
    });

    const currBreakdownStr = Object.keys(currMap).map(c => {
        const item = currMap[c];
        const net = Math.abs(item.debit - item.credit);
        return `${item.sym} ${net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${c}`;
    }).join(' &nbsp;|&nbsp; ');

    let html = `
        <div style="background:var(--bg-page); border:1px solid var(--border); border-radius:14px; padding:1.25rem; margin-bottom:1.25rem;">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; align-items:center;">
                <div>
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; letter-spacing:0.04em;">Invoiced / Borrowed Total</div>
                    <div style="font-size:1.3rem; font-weight:800; color:var(--text-heading); margin-top:0.2rem;">
                        LKR ${(party.total_invoiced + party.total_loan_borrowed + party.total_commission_owed).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </div>
                </div>
                <div>
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; letter-spacing:0.04em;">Collections / Paids Settled</div>
                    <div style="font-size:1.3rem; font-weight:800; color:#059669; margin-top:0.2rem;">
                        LKR ${(party.total_collected + party.total_paids).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; letter-spacing:0.04em;">Net Account Position (LKR Base)</div>
                    <div style="font-size:1.4rem; font-weight:800; color:${netBalColor}; margin-top:0.2rem;">
                        ${netBalFormatted}
                    </div>
                    <div style="font-size:0.73rem; color:var(--text-muted); font-weight:600; margin-top:0.25rem;">
                        Original: ${currBreakdownStr}
                    </div>
                </div>
            </div>
        </div>

        <div style="max-height: 480px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
            <table class="data-table" style="width:100%; margin:0; border-collapse:collapse;">
                <thead style="position:sticky; top:0; background:var(--bg-page); z-index:10; border-bottom:1px solid var(--border);">
                    <tr>
                        <th style="padding:0.85rem 1rem; text-align:left; font-size:0.78rem; color:var(--text-muted);">Date</th>
                        <th style="padding:0.85rem 1rem; text-align:left; font-size:0.78rem; color:var(--text-muted);">Transaction Type</th>
                        <th style="padding:0.85rem 1rem; text-align:left; font-size:0.78rem; color:var(--text-muted);">Reference #</th>
                        <th style="padding:0.85rem 1rem; text-align:left; font-size:0.78rem; color:var(--text-muted);">Description</th>
                        <th style="padding:0.85rem 1rem; text-align:right; font-size:0.78rem; color:var(--primary);">Debit (DR)</th>
                        <th style="padding:0.85rem 1rem; text-align:right; font-size:0.78rem; color:var(--success);">Credit (CR)</th>
                    </tr>
                </thead>
                <tbody>
    `;

    if (timeline.length === 0) {
        html += `
            <tr>
                <td colspan="6" style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
                    No financial ledger transactions recorded for ${party.name}.
                </td>
            </tr>
        `;
    } else {
        timeline.forEach(tx => {
            let typeBadgeStyle = 'background:var(--bg-page); color:var(--text-muted); border:1px solid var(--border);';
            if (tx.type.includes('Contract')) {
                typeBadgeStyle = 'background:#f3e8ff; color:#6b21a8; font-weight:700; border:1px solid #e9d5ff;';
            } else if (tx.type.includes('Invoice') || tx.type.includes('Draw')) {
                typeBadgeStyle = 'background:#dbeafe; color:#1e40af;';
            } else if (tx.type.includes('Payment') || tx.type.includes('Repayment') || tx.type.includes('Paid')) {
                typeBadgeStyle = 'background:#d1fae5; color:#065f46;';
            } else if (tx.type.includes('Bill') || tx.type.includes('Taken') || tx.type.includes('Entitlement')) {
                typeBadgeStyle = 'background:#fef3c7; color:#92400e;';
            }

            let debitCell = '-';
            if (tx.debit > 0) {
                debitCell = `<div style="font-weight:700; color:var(--primary); font-size:0.9rem;">LKR ${tx.debit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>`;
                if (tx.currency && tx.currency !== 'LKR') {
                    debitCell += `<div style="font-size:0.73rem; color:var(--text-muted); font-weight:600;">(${tx.currency_symbol || tx.currency} ${tx.original_debit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${tx.currency})</div>`;
                }
            }

            let creditCell = '-';
            if (tx.credit > 0) {
                creditCell = `<div style="font-weight:700; color:#059669; font-size:0.9rem;">LKR ${tx.credit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>`;
                if (tx.currency && tx.currency !== 'LKR') {
                    creditCell += `<div style="font-size:0.73rem; color:var(--text-muted); font-weight:600;">(${tx.currency_symbol || tx.currency} ${tx.original_credit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${tx.currency})</div>`;
                }
            }

            html += `
                <tr style="border-bottom: 1px solid var(--border-light);">
                    <td style="padding:0.85rem 1rem; font-weight:600; font-size:0.88rem;">${tx.date}</td>
                    <td style="padding:0.85rem 1rem;">
                        <span style="font-size:0.7rem; font-weight:700; padding:0.15rem 0.45rem; border-radius:5px; ${typeBadgeStyle}">
                            ${tx.type}
                        </span>
                    </td>
                    <td style="padding:0.85rem 1rem; font-weight:700; color:var(--text-heading); font-size:0.88rem;">${tx.reference}</td>
                    <td style="padding:0.85rem 1rem; font-size:0.88rem;">${tx.description}</td>
                    <td style="padding:0.85rem 1rem; text-align:right;">
                        ${debitCell}
                    </td>
                    <td style="padding:0.85rem 1rem; text-align:right;">
                        ${creditCell}
                    </td>
                </tr>
            `;
        });
    }


    html += `
                </tbody>
            </table>
        </div>
    `;

    document.getElementById('ledgerModalBody').innerHTML = html;
    openModal('partyLedgerModal');
}

function openPartyPaymentModal() {
    if (!currentModalParty) return;

    document.getElementById('payment_modal_party_id').value = currentModalParty.id;
    document.getElementById('paymentModalPartyTitle').innerText = 'Record Settlement Payment: ' + currentModalParty.name;
    
    // Auto select settlement type based on party roles
    const selectType = document.getElementById('payment_modal_settlement_type');
    if (currentModalParty.types.includes('partner')) {
        selectType.value = 'commission_payout';
    } else if (currentModalParty.types.includes('vendor')) {
        selectType.value = 'vendor_bill_payment';
    } else if (currentModalParty.types.includes('lender') || currentModalParty.types.includes('director') || currentModalParty.types.includes('bank')) {
        selectType.value = 'loan_repayment';
    } else {
        selectType.value = 'receivable_collection';
    }

    // Auto fill net balance amount
    const absBal = Math.abs(currentModalParty.net_balance);
    const amountVal = currentModalParty.net_balance < 0 ? (currentModalParty.total_payables || absBal) : (currentModalParty.ar_balance || absBal);
    
    const amtInput = document.getElementById('payment_modal_amount');
    if (amtInput) {
        amtInput.value = amountVal > 0 ? amountVal : '';
        const hiddenAmt = amtInput.nextElementSibling;
        if (hiddenAmt) hiddenAmt.value = amountVal > 0 ? amountVal : '';
        if (typeof formatAmountBlur === 'function') formatAmountBlur(amtInput);
    }

    openModal('recordPartyPaymentModal');
}

// Auto open modal if selectedPartyId is passed in URL
document.addEventListener('DOMContentLoaded', function() {
    if (selectedPartyIdOnLoad && selectedPartyIdOnLoad !== '') {
        openPartyLedgerModal(selectedPartyIdOnLoad);
    }
});
</script>
@endsection

