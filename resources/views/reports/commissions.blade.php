@extends('reports.layout')

@section('report-title', 'Partner Commission Report')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Partner Commission Report</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Commission calculations from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div style="display: flex; flex-direction: column; gap: 1rem;">
    @forelse($data['parties'] as $p)
        <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border); background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm);">
            <!-- Accordion Header -->
            <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: var(--bg-sidebar-secondary); flex-wrap: wrap; gap: 0.75rem;" onclick="toggleAccordion('party_{{ $p->party_id }}')">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <strong style="font-size: 1.15rem; color: var(--text-heading);">{{ $p->party_name }}</strong>
                    @foreach(explode(',', $p->party_types) as $type)
                        @if(!empty($type))
                            <span class="badge" style="background: var(--primary-light); color: var(--primary);">{{ ucfirst($type) }}</span>
                        @endif
                    @endforeach
                </div>
                <div style="display: flex; align-items: center; gap: 2rem;">
                    <div style="font-size: 0.9rem; color: var(--text-muted);" class="mobile-hide">
                        Total Earned: <strong style="color: var(--text-heading);">{{ number_format($p->total_commission, 2) }}</strong>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--text-muted);" class="mobile-hide">
                        Paid: <strong style="color: var(--success);">{{ number_format($p->total_paid, 2) }}</strong>
                    </div>
                    <div style="font-size: 1rem;">
                        Payable: <strong style="color: var(--danger); font-size: 1.1rem;">{{ number_format($p->total_payable, 2) }}</strong>
                    </div>
                    <ion-icon name="chevron-down-outline" id="icon_party_{{ $p->party_id }}" style="font-size: 1.2rem; transition: transform 0.2s; color: var(--text-muted);"></ion-icon>
                </div>
            </div>

            <!-- Accordion Body -->
            <div id="party_{{ $p->party_id }}" style="display: none; border-top: 1px solid var(--border);">
                <div class="data-table-container">
                    <table class="data-table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Commission %</th>
                                <th style="text-align: right;">Paid Invoices Total</th>
                                <th style="text-align: right;">Commission Earned</th>
                                <th style="text-align: right;">Payout Received</th>
                                <th style="text-align: right;">Net Payable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($p->items as $item)
                                <tr>
                                    <td data-label="Project" style="font-weight: 500; color: var(--text-heading);">{{ $item->project_name }}</td>
                                    <td data-label="Percentage"><span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">{{ is_numeric($item->percentage) ? number_format((float)$item->percentage, 2) . '%' : $item->percentage }}</span></td>

                                    <td data-label="Invoiced" style="text-align: right;">{{ number_format($item->invoiced_paid, 2) }}</td>
                                    <td data-label="Earned" style="text-align: right; font-weight: 600; color: var(--text-heading);">{{ number_format($item->commission_earned, 2) }}</td>
                                    <td data-label="Paid Out" style="text-align: right; color: var(--success);">{{ number_format($item->paid_amount, 2) }}</td>
                                    <td data-label="Payable" style="text-align: right; font-weight: 700; color: var(--danger);">{{ number_format($item->net_payable, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card text-center text-muted py-4" style="padding: 3rem; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border);">
            <ion-icon name="cash-outline" style="font-size: 3rem; opacity: 0.3; margin-bottom: 0.5rem;"></ion-icon><br>
            No commission project allocations found for partners or vendors in this period.
        </div>
    @endforelse
</div>

<script>
    function toggleAccordion(id) {
        const body = document.getElementById(id);
        const icon = document.getElementById('icon_' + id);
        if (body.style.display === 'none') {
            body.style.display = 'block';
            if(icon) icon.style.transform = 'rotate(180deg)';
        } else {
            body.style.display = 'none';
            if(icon) icon.style.transform = 'rotate(0deg)';
        }
    }
</script>
@endsection
