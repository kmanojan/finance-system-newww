@props([
    'modalId' => 'companyProfitModal',
    'currency' => 'LKR',
    'budgetLimit' => 0,
    'totalApprovedCR' => 0,
    'changeRequests' => collect(),
    'totalCommission' => 0,
    'commissions' => collect(),
    'costAllocations' => collect(),
    'companyProfit' => 0
])

@php
    $revenueBase = $budgetLimit + $totalApprovedCR;
    $employeeAllocations = $costAllocations->where('type', 'employee');
    $employeeTotal = $employeeAllocations->sum('amount');

    $serverAllocations = $costAllocations->where('type', 'server');
    $serverTotal = $serverAllocations->sum('amount');

    $otherAllocations = $costAllocations->where('type', 'other');
    $otherTotal = $otherAllocations->sum('amount');

    $totalCostAllocation = $employeeTotal + $serverTotal + $otherTotal;
@endphp

<!-- Company Profit Breakdown Main Modal -->
<div class="modal-backdrop" id="{{ $modalId }}">
    <div class="modal-card" style="max-width: 750px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="briefcase-outline" style="color:var(--primary);"></ion-icon>
                Company Profit Breakdown
            </h3>
            <button type="button" class="btn-close" onclick="closeModal('{{ $modalId }}')">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem;">
            <!-- Hero Profit Banner -->
            <div style="background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); color:white; padding:1.25rem; border-radius:12px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span style="font-size:0.85rem; opacity:0.9;">Total Company Profit</span>
                    <h2 style="font-size:1.8rem; font-weight:700; margin:0.2rem 0 0 0;">{{ $currency }} {{ number_format($companyProfit, 2) }}</h2>
                </div>
                <div style="text-align:right; font-size:0.8rem; opacity:0.9;">
                    Formula:<br>
                    <strong>(Budget + CR) - (Comm + Cost Alloc)</strong>
                </div>
            </div>

            <!-- Revenue Base & Commission summary -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                <div style="background:var(--bg-sidebar-secondary); border:1px solid var(--border); border-radius:10px; padding:1rem;">
                    <span style="font-size:0.8rem; color:var(--text-muted); display:block;">Total Revenue Base (Budget + CR)</span>
                    <strong style="font-size:1.1rem; color:var(--text-heading);">{{ $currency }} {{ number_format($revenueBase, 2) }}</strong>
                </div>
                <div style="background:var(--bg-sidebar-secondary); border:1px solid var(--border); border-radius:10px; padding:1rem;">
                    <span style="font-size:0.8rem; color:var(--text-muted); display:block;">Total External Commissions (-)</span>
                    <strong style="font-size:1.1rem; color:var(--danger);">-{{ $currency }} {{ number_format($totalCommission, 2) }}</strong>
                </div>
            </div>

            <!-- Cost Allocations Section Title -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h4 style="font-size:1rem; font-weight:600; color:var(--text-heading); margin:0; display:flex; align-items:center; gap:0.4rem;">
                    <ion-icon name="pie-chart-outline" style="color:var(--primary);"></ion-icon> Internal Cost Allocations (-{{ $currency }} {{ number_format($totalCostAllocation, 2) }})
                </h4>
            </div>

            <!-- 3 Category Cost Cards (Employee, Server, Other) -->
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:1rem; margin-bottom:1.5rem;">
                <!-- Employee Card -->
                <div onclick="openModal('{{ $modalId }}_employeeModal')" style="background:var(--bg-page); border:1.5px solid var(--border-light); border-radius:12px; padding:1rem; cursor:pointer; transition:all 0.2s ease; position:relative;" onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='var(--border-light)'; this.style.transform='translateY(0)'">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                        <span style="font-size:0.85rem; font-weight:600; color:var(--text-heading); display:flex; align-items:center; gap:0.3rem;">
                            <ion-icon name="people-outline" style="color:var(--primary);"></ion-icon> Employee
                        </span>
                        <ion-icon name="eye-outline" style="color:var(--text-muted); font-size:1.1rem;"></ion-icon>
                    </div>
                    <div style="font-size:1.15rem; font-weight:700; color:var(--danger);">-{{ $currency }} {{ number_format($employeeTotal, 2) }}</div>
                    <span style="font-size:0.75rem; color:var(--primary); font-weight:500; display:inline-block; margin-top:0.4rem;">Click for details &rarr;</span>
                </div>

                <!-- Server Card -->
                <div onclick="openModal('{{ $modalId }}_serverModal')" style="background:var(--bg-page); border:1.5px solid var(--border-light); border-radius:12px; padding:1rem; cursor:pointer; transition:all 0.2s ease; position:relative;" onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='var(--border-light)'; this.style.transform='translateY(0)'">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                        <span style="font-size:0.85rem; font-weight:600; color:var(--text-heading); display:flex; align-items:center; gap:0.3rem;">
                            <ion-icon name="server-outline" style="color:var(--primary);"></ion-icon> Server
                        </span>
                        <ion-icon name="eye-outline" style="color:var(--text-muted); font-size:1.1rem;"></ion-icon>
                    </div>
                    <div style="font-size:1.15rem; font-weight:700; color:var(--danger);">-{{ $currency }} {{ number_format($serverTotal, 2) }}</div>
                    <span style="font-size:0.75rem; color:var(--primary); font-weight:500; display:inline-block; margin-top:0.4rem;">Click for details &rarr;</span>
                </div>

                <!-- Other Card -->
                <div onclick="openModal('{{ $modalId }}_otherModal')" style="background:var(--bg-page); border:1.5px solid var(--border-light); border-radius:12px; padding:1rem; cursor:pointer; transition:all 0.2s ease; position:relative;" onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='var(--border-light)'; this.style.transform='translateY(0)'">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                        <span style="font-size:0.85rem; font-weight:600; color:var(--text-heading); display:flex; align-items:center; gap:0.3rem;">
                            <ion-icon name="layers-outline" style="color:var(--primary);"></ion-icon> Other
                        </span>
                        <ion-icon name="eye-outline" style="color:var(--text-muted); font-size:1.1rem;"></ion-icon>
                    </div>
                    <div style="font-size:1.15rem; font-weight:700; color:var(--danger);">-{{ $currency }} {{ number_format($otherTotal, 2) }}</div>
                    <span style="font-size:0.75rem; color:var(--primary); font-weight:500; display:inline-block; margin-top:0.4rem;">Click for details &rarr;</span>
                </div>
            </div>

            <!-- Net Result -->
            <div style="background:var(--bg-card); border:2px solid var(--primary); border-radius:10px; padding:1rem; display:flex; justify-content:space-between; font-weight:700; color:var(--text-heading); font-size:1.05rem;">
                <span>Net Company Profit</span>
                <span style="color:{{ $companyProfit >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $currency }} {{ number_format($companyProfit, 2) }}</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('{{ $modalId }}')">Close</button>
        </div>
    </div>
</div>

<!-- Employee Allocations Detail Sub-Modal -->
<div class="modal-backdrop" id="{{ $modalId }}_employeeModal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="people-outline" style="color:var(--primary);"></ion-icon> Employee Cost Allocation Details
            </h3>
            <button type="button" class="btn-close" onclick="closeModal('{{ $modalId }}_employeeModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem;">
            <div style="background:var(--bg-sidebar-secondary); padding:1rem; border-radius:10px; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:600; color:var(--text-heading);">Total Employee Cost:</span>
                <strong style="font-size:1.2rem; color:var(--danger);">-{{ $currency }} {{ number_format($employeeTotal, 2) }}</strong>
            </div>
            @if($employeeAllocations->count() > 0)
                <table class="mini-table">
                    <thead>
                        <tr><th>Employee Name</th><th>Period Start</th><th style="text-align:right;">Allocated Amount</th></tr>
                    </thead>
                    <tbody>
                        @foreach($employeeAllocations as $alloc)
                        <tr>
                            <td style="font-weight:500;">
                                {{ trim(($alloc->first_name ?? '') . ' ' . ($alloc->last_name ?? '')) ?: 'Employee #' . $alloc->employee_id }}
                                @if(!empty($alloc->notes))
                                    <br><small class="text-muted">{{ $alloc->notes }}</small>
                                @endif
                            </td>
                            <td>{{ \Carbon\Carbon::parse($alloc->period_start)->format('Y-m-d') }}</td>
                            <td style="text-align:right; font-weight:600; color:var(--danger);">-{{ $alloc->currency ?? $currency }} {{ number_format($alloc->amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted" style="text-align:center; padding:2rem;">No employee cost allocations found.</p>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('{{ $modalId }}_employeeModal')">Close</button>
        </div>
    </div>
</div>

<!-- Server Allocations Detail Sub-Modal -->
<div class="modal-backdrop" id="{{ $modalId }}_serverModal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="server-outline" style="color:var(--primary);"></ion-icon> Server Cost Allocation Details
            </h3>
            <button type="button" class="btn-close" onclick="closeModal('{{ $modalId }}_serverModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem;">
            <div style="background:var(--bg-sidebar-secondary); padding:1rem; border-radius:10px; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:600; color:var(--text-heading);">Total Server Cost:</span>
                <strong style="font-size:1.2rem; color:var(--danger);">-{{ $currency }} {{ number_format($serverTotal, 2) }}</strong>
            </div>
            @if($serverAllocations->count() > 0)
                <table class="mini-table">
                    <thead>
                        <tr><th>Server Name / ID</th><th>Period Start</th><th style="text-align:right;">Allocated Amount</th></tr>
                    </thead>
                    <tbody>
                        @foreach($serverAllocations as $alloc)
                        <tr>
                            <td style="font-weight:500;">
                                {{ $alloc->server_name ?? 'Server #' . $alloc->server_id }}
                                @if(!empty($alloc->notes))
                                    <br><small class="text-muted">{{ $alloc->notes }}</small>
                                @endif
                            </td>
                            <td>{{ \Carbon\Carbon::parse($alloc->period_start)->format('Y-m-d') }}</td>
                            <td style="text-align:right; font-weight:600; color:var(--danger);">-{{ $alloc->currency ?? $currency }} {{ number_format($alloc->amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted" style="text-align:center; padding:2rem;">No server cost allocations found.</p>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('{{ $modalId }}_serverModal')">Close</button>
        </div>
    </div>
</div>

<!-- Other Allocations Detail Sub-Modal -->
<div class="modal-backdrop" id="{{ $modalId }}_otherModal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="layers-outline" style="color:var(--primary);"></ion-icon> Other Cost Allocation Details
            </h3>
            <button type="button" class="btn-close" onclick="closeModal('{{ $modalId }}_otherModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem;">
            <div style="background:var(--bg-sidebar-secondary); padding:1rem; border-radius:10px; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:600; color:var(--text-heading);">Total Other Cost:</span>
                <strong style="font-size:1.2rem; color:var(--danger);">-{{ $currency }} {{ number_format($otherTotal, 2) }}</strong>
            </div>
            @if($otherAllocations->count() > 0)
                <table class="mini-table">
                    <thead>
                        <tr><th>Cost Center Name</th><th>Period Start</th><th style="text-align:right;">Allocated Amount</th></tr>
                    </thead>
                    <tbody>
                        @foreach($otherAllocations as $alloc)
                        <tr>
                            <td style="font-weight:500;">
                                {{ $alloc->cost_center_name ?? 'Other Cost' }}
                                @if(!empty($alloc->notes))
                                    <br><small class="text-muted">{{ $alloc->notes }}</small>
                                @endif
                            </td>
                            <td>{{ \Carbon\Carbon::parse($alloc->period_start)->format('Y-m-d') }}</td>
                            <td style="text-align:right; font-weight:600; color:var(--danger);">-{{ $alloc->currency ?? $currency }} {{ number_format($alloc->amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted" style="text-align:center; padding:2rem;">No other cost allocations found.</p>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('{{ $modalId }}_otherModal')">Close</button>
        </div>
    </div>
</div>
