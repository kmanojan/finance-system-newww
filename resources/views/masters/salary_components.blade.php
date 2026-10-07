@extends('layouts.app')
@section('title', 'Salary Components - Master Data')

@section('secondary-sidebar')
    @include('masters._sidebar')
@endsection

@section('content')
<header class="page-header">
    <div class="header-titles">
        <h1>Salary Components</h1>
        <p class="subtitle">Manage earnings, allowances, employee deductions (EPF 8%, APIT), and statutory employer contributions (EPF 12%, ETF 3%).</p>
    </div>
    <button type="button" class="btn btn-primary btn-pill" onclick="openCreateModal()">
        <ion-icon name="add-outline"></ion-icon> Add New Component
    </button>
</header>

@if(session('error'))
<div style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid rgba(239, 68, 68, 0.3);">
    {{ session('error') }}
</div>
@endif

@if(session('success'))
<div style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.3);">
    {{ session('success') }}
</div>
@endif

<div class="toolbar" style="margin-bottom: 1.5rem;">
    <div class="toolbar-left" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
        <a href="/master/salary-components" class="btn btn-outline {{ !request('type') ? 'btn-primary' : '' }}" style="padding:0.4rem 0.8rem; font-size:0.85rem; text-decoration:none;">All ({{ $components->count() }})</a>
        <a href="/master/salary-components?type=earning" class="btn btn-outline {{ request('type') === 'earning' ? 'btn-primary' : '' }}" style="padding:0.4rem 0.8rem; font-size:0.85rem; text-decoration:none;">Earnings / Allowances (+)</a>
        <a href="/master/salary-components?type=deduction" class="btn btn-outline {{ request('type') === 'deduction' ? 'btn-primary' : '' }}" style="padding:0.4rem 0.8rem; font-size:0.85rem; text-decoration:none;">Employee Deductions (-)</a>
        <a href="/master/salary-components?type=employer_contribution" class="btn btn-outline {{ request('type') === 'employer_contribution' ? 'btn-primary' : '' }}" style="padding:0.4rem 0.8rem; font-size:0.85rem; text-decoration:none;">Employer Contributions (CTC)</a>
    </div>
    <div class="toolbar-right">
        <form method="GET" action="/master/salary-components" style="display:flex; gap:0.5rem;">
            @if(request('type'))
                <input type="hidden" name="type" value="{{ request('type') }}">
            @endif
            <div class="search-input" style="position:relative; width:220px;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search components..." class="form-control" style="padding-left:2.2rem; font-size:0.85rem;">
                <ion-icon name="search-outline" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted);"></ion-icon>
            </div>
            <button type="submit" class="btn btn-outline" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Filter</button>
        </form>
    </div>
</div>

<div class="data-table-container" style="background:var(--bg-card); border-radius:12px; border:1px solid var(--border); overflow:hidden;">
    <table class="data-table" style="width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Component Name & Code</th>
                <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Type</th>
                <th style="padding:1rem; text-align:left; font-weight:600; color:var(--text-muted);">Calculation Basis</th>
                <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted);">Default Value</th>
                <th style="padding:1rem; text-align:center; font-weight:600; color:var(--text-muted);">Rules & Flags</th>
                <th style="padding:1rem; text-align:center; font-weight:600; color:var(--text-muted);">Status</th>
                <th style="padding:1rem; text-align:right; font-weight:600; color:var(--text-muted);">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($components as $item)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:1rem;">
                    <div style="font-weight:600; color:var(--text-heading); display:flex; align-items:center; gap:0.5rem;">
                        {{ $item->name }}
                        @if($item->is_statutory)
                            <span class="badge" style="background:rgba(139, 92, 246, 0.15); color:var(--primary); font-size:0.7rem; padding:0.2em 0.5em; border-radius:4px;">Statutory</span>
                        @endif
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); font-family:monospace; margin-top:2px;">
                        {{ $item->code }}
                    </div>
                </td>
                <td style="padding:1rem;">
                    @if($item->type === 'earning')
                        <span class="badge badge-success" style="font-size:0.75rem; padding:0.25rem 0.5rem; border-radius:6px;">
                            <ion-icon name="add-circle-outline" style="vertical-align:middle;"></ion-icon> Earning (+)
                        </span>
                    @elseif($item->type === 'deduction')
                        <span class="badge badge-danger" style="font-size:0.75rem; padding:0.25rem 0.5rem; border-radius:6px;">
                            <ion-icon name="remove-circle-outline" style="vertical-align:middle;"></ion-icon> Deduction (-)
                        </span>
                    @else
                        <span class="badge badge-info" style="font-size:0.75rem; padding:0.25rem 0.5rem; border-radius:6px;">
                            <ion-icon name="business-outline" style="vertical-align:middle;"></ion-icon> Employer Cost (CTC)
                        </span>
                    @endif
                </td>
                <td style="padding:1rem;">
                    <div style="font-weight:500; color:var(--text-main); font-size:0.85rem;">
                        @if($item->calculation_type === 'percentage')
                            Percentage (%)
                        @else
                            Fixed Amount
                        @endif
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                        @if($item->calculation_base === 'on_basic')
                            Calculated on Basic Salary
                        @elseif($item->calculation_base === 'on_epf_base')
                            Calculated on EPF Base (Basic + Allowances)
                        @elseif($item->calculation_base === 'on_gross')
                            Calculated on Gross Earnings
                        @else
                            Custom / Flat value
                        @endif
                    </div>
                </td>
                <td style="padding:1rem; text-align:right;" class="tabular-nums">
                    @if($item->calculation_type === 'percentage')
                        <span style="font-weight:700; color:var(--primary); font-size:1rem;">{{ number_format($item->default_value, 2) }}%</span>
                    @else
                        <span style="font-weight:600; color:var(--text-heading); font-size:0.95rem;">{{ number_format($item->default_value, 2) }}</span>
                    @endif
                </td>
                <td style="padding:1rem; text-align:center;">
                    <div style="display:inline-flex; gap:0.35rem; flex-wrap:wrap; justify-content:center;">
                        @if($item->is_epf_eligible)
                            <span class="badge badge-warning" title="Counted towards EPF/ETF base earnings" style="font-size:0.7rem; padding:0.2em 0.5em; border-radius:4px;">EPF Eligible</span>
                        @endif
                        @if($item->is_taxable)
                            <span class="badge" style="background:#f1f5f9; color:#475569; font-size:0.7rem; padding:0.2em 0.5em; border-radius:4px;" title="Subject to APIT Income Tax">Taxable</span>
                        @endif
                    </div>
                </td>
                <td style="padding:1rem; text-align:center;">
                    @if($item->is_active)
                        <span class="badge badge-success" style="font-size:0.75rem; padding:0.2em 0.5em; border-radius:4px;">Active</span>
                    @else
                        <span class="badge badge-danger" style="font-size:0.75rem; padding:0.2em 0.5em; border-radius:4px;">Inactive</span>
                    @endif
                </td>
                <td style="padding:1rem; text-align:right;">
                    <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button type="button" class="btn btn-outline" style="padding:0.3rem 0.6rem; font-size:0.8rem;" 
                                data-item="{{ json_encode($item) }}" 
                                onclick="openEditModal(JSON.parse(this.dataset.item))">
                            <ion-icon name="create-outline"></ion-icon> Edit
                        </button>
                        @if(!$item->is_statutory)
                        <form method="POST" action="/master/salary-components/{{ $item->id }}" onsubmit="return confirm('Are you sure you want to delete this salary component?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline" style="padding:0.3rem 0.6rem; font-size:0.8rem; color:var(--danger); border-color:rgba(239, 68, 68, 0.3);">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
                    <ion-icon name="calculator-outline" style="font-size:3rem; opacity:0.4; margin-bottom:0.5rem;"></ion-icon><br>
                    No salary components found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
function openCreateModal() {
    var form = document.querySelector('#createModal form');
    if (form) form.reset();
    openModal('createModal');
}

function openEditModal(item) {
    document.getElementById('editForm').action = '/master/salary-components/' + item.id;
    document.getElementById('edit_name').value = item.name || '';
    document.getElementById('edit_code').value = item.code || '';
    document.getElementById('edit_type').value = item.type || 'earning';
    document.getElementById('edit_calculation_type').value = item.calculation_type || 'fixed_amount';
    document.getElementById('edit_calculation_base').value = item.calculation_base || 'on_basic';
    document.getElementById('edit_default_value').value = item.default_value || 0;
    document.getElementById('edit_is_epf_eligible').checked = !!item.is_epf_eligible;
    document.getElementById('edit_is_taxable').checked = !!item.is_taxable;
    document.getElementById('edit_is_active').checked = !!item.is_active;
    document.getElementById('edit_description').value = item.description || '';
    openModal('editModal');
}
</script>
@endsection

@section('modals')
<!-- Create Modal -->
<div class="modal-backdrop" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Add Salary Component</h3>
            <button type="button" class="btn-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST" action="/master/salary-components">
            @csrf
            <div class="modal-body" style="padding:1.5rem; max-height:75vh; overflow-y:auto;">
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Component Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="E.g. Travel Allowance, Performance Bonus" required>
                </div>

                <div class="form-row" style="margin-bottom:1rem;">
                    <div class="form-col">
                        <label class="form-label">Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="E.g. TRAVEL_ALLOW" required style="text-transform:uppercase;">
                        <small style="color:var(--text-muted); font-size:0.75rem;">Unique identifier code</small>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Component Type *</label>
                        <select name="type" class="form-control" required>
                            <option value="earning">Earning / Allowance (+)</option>
                            <option value="deduction">Employee Deduction (-)</option>
                            <option value="employer_contribution">Employer Contribution (CTC)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-bottom:1rem;">
                    <div class="form-col">
                        <label class="form-label">Calculation Method *</label>
                        <select name="calculation_type" class="form-control" required>
                            <option value="fixed_amount">Fixed Amount</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Calculation Base *</label>
                        <select name="calculation_base" class="form-control" required id="create_calc_base">
                            <option value="on_basic">On Basic Salary</option>
                            <option value="on_epf_base">On EPF Base (Basic + Allowances)</option>
                            <option value="on_gross">On Total Gross Earnings</option>
                            <option value="custom">Custom / Flat value</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Default Value (Amount or %)</label>
                    <input type="number" step="0.01" name="default_value" class="form-control" value="0.00" required>
                    <small style="color:var(--text-muted); font-size:0.75rem;">Default value applied when assigned to an employee.</small>
                </div>

                <div style="background:var(--bg-page); padding:1rem; border-radius:8px; border:1px solid var(--border-light); margin-bottom:1rem;">
                    <label style="font-weight:600; font-size:0.85rem; color:var(--text-heading); display:block; margin-bottom:0.5rem;">Statutory & Tax Rules</label>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-main); cursor:pointer;">
                            <input type="checkbox" name="is_epf_eligible" value="1">
                            <span><strong>EPF Eligible</strong> (Included in salary base for EPF 8%, 12% and ETF 3% calculations)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-main); cursor:pointer;">
                            <input type="checkbox" name="is_taxable" value="1" checked>
                            <span><strong>Taxable under APIT</strong> (Included in taxable personal income)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-main); cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span><strong>Active</strong> (Available for selection on employee profiles)</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Optional notes on statutory references or business policy"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:0.75rem; padding:1.25rem 1.5rem; border-top:1px solid var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Component</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-backdrop" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Salary Component</h3>
            <button type="button" class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" id="editForm">
            @csrf
            @method('PUT')
            <div class="modal-body" style="padding:1.5rem; max-height:75vh; overflow-y:auto;">
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Component Name *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>

                <div class="form-row" style="margin-bottom:1rem;">
                    <div class="form-col">
                        <label class="form-label">Code *</label>
                        <input type="text" id="edit_code" name="code" class="form-control" required style="text-transform:uppercase;">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Component Type *</label>
                        <select id="edit_type" name="type" class="form-control" required>
                            <option value="earning">Earning / Allowance (+)</option>
                            <option value="deduction">Employee Deduction (-)</option>
                            <option value="employer_contribution">Employer Contribution (CTC)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-bottom:1rem;">
                    <div class="form-col">
                        <label class="form-label">Calculation Method *</label>
                        <select id="edit_calculation_type" name="calculation_type" class="form-control" required>
                            <option value="fixed_amount">Fixed Amount</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Calculation Base *</label>
                        <select id="edit_calculation_base" name="calculation_base" class="form-control" required>
                            <option value="on_basic">On Basic Salary</option>
                            <option value="on_epf_base">On EPF Base (Basic + Allowances)</option>
                            <option value="on_gross">On Total Gross Earnings</option>
                            <option value="custom">Custom / Flat value</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">Default Value (Amount or %)</label>
                    <input type="number" step="0.01" id="edit_default_value" name="default_value" class="form-control" required>
                </div>

                <div style="background:var(--bg-page); padding:1rem; border-radius:8px; border:1px solid var(--border-light); margin-bottom:1rem;">
                    <label style="font-weight:600; font-size:0.85rem; color:var(--text-heading); display:block; margin-bottom:0.5rem;">Statutory & Tax Rules</label>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-main); cursor:pointer;">
                            <input type="checkbox" id="edit_is_epf_eligible" name="is_epf_eligible" value="1">
                            <span><strong>EPF Eligible</strong> (Included in EPF/ETF base earnings)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-main); cursor:pointer;">
                            <input type="checkbox" id="edit_is_taxable" name="is_taxable" value="1">
                            <span><strong>Taxable under APIT</strong> (Subject to personal income tax)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-main); cursor:pointer;">
                            <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                            <span><strong>Active</strong></span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Notes</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:0.75rem; padding:1.25rem 1.5rem; border-top:1px solid var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Component</button>
            </div>
        </form>
    </div>
</div>
@endsection
