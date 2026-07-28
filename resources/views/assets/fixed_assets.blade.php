@extends('layouts.app')
@section('title', 'Fixed Assets & Depreciation')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Assets</h2>
    <nav class="nav-links">
        <a href="/assets/fixed-assets" class="nav-link active">Fixed Assets Register</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 2rem;">
    <div class="header-titles">
        <h1>Fixed Asset Register & Depreciation</h1>
        <p class="subtitle">Track company assets, calculate monthly depreciation, and post GL journal entries.</p>
    </div>
    <button class="btn btn-primary btn-pill" onclick="openModal('registerAssetModal')">
        <ion-icon name="add-outline"></ion-icon> Register New Asset
    </button>
</header>

<div class="summary-cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <div class="card" style="padding:1.5rem; border-left:4px solid var(--primary);">
        <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Total Asset Purchase Cost</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--text-heading); margin-top:0.5rem;">LKR {{ number_format($assets->sum('purchase_cost'), 2) }}</div>
    </div>
    <div class="card" style="padding:1.5rem; border-left:4px solid var(--warning);">
        <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Accumulated Depreciation</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--text-heading); margin-top:0.5rem;">LKR {{ number_format($assets->sum('accumulated_depreciation'), 2) }}</div>
    </div>
    <div class="card" style="padding:1.5rem; border-left:4px solid var(--success);">
        <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600; text-transform:uppercase;">Net Book Value (NBV)</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--text-heading); margin-top:0.5rem;">LKR {{ number_format($assets->sum('purchase_cost') - $assets->sum('accumulated_depreciation'), 2) }}</div>
    </div>
</div>

<div class="card" style="padding:0; overflow-x:auto;">
    <table class="data-table" style="width:100%; margin:0;">
        <thead>
            <tr>
                <th>Code</th>
                <th>Asset Name</th>
                <th>Category</th>
                <th>Purchase Cost</th>
                <th>Accum. Depr.</th>
                <th>Net Book Value</th>
                <th>Method</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assets as $asset)
            <tr>
                <td><span class="font-medium">{{ $asset->asset_code }}</span></td>
                <td>{{ $asset->asset_name }}</td>
                <td>{{ ucfirst($asset->category) }}</td>
                <td>LKR {{ number_format($asset->purchase_cost, 2) }}</td>
                <td>LKR {{ number_format($asset->accumulated_depreciation, 2) }}</td>
                <td style="font-weight:600; color:var(--primary);">LKR {{ number_format($asset->purchase_cost - $asset->accumulated_depreciation, 2) }}</td>
                <td><span class="badge badge-draft">{{ ucfirst(str_replace('_', ' ', $asset->depreciation_method)) }}</span></td>
                <td>
                    <form action="/assets/fixed-assets/{{ $asset->id }}/depreciate" method="POST" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn btn-outline" style="padding:0.3rem 0.6rem; font-size:0.8rem;">Run Depr.</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if($assets->isEmpty())
            <tr>
                <td colspan="8" style="text-align:center; padding:2rem; color:var(--text-muted);">No fixed assets registered yet.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

<!-- Modal: Register Asset -->
<div class="modal-backdrop" id="registerAssetModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Register Fixed Asset</h3>
            <button type="button" class="btn-close" onclick="closeModal('registerAssetModal')">&times;</button>
        </div>
        <form action="/assets/fixed-assets" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Asset Code *</label>
                        <input type="text" name="asset_code" class="form-control" required placeholder="AST-2026-001">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Asset Name *</label>
                        <input type="text" name="asset_name" class="form-control" required placeholder="MacBook Pro M3 Max">
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-col">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="equipment">IT Equipment & Laptops</option>
                            <option value="furniture">Office Furniture</option>
                            <option value="vehicle">Vehicles</option>
                            <option value="building">Buildings & Real Estate</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Purchase Date *</label>
                        <input type="date" name="purchase_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-col">
                        <label class="form-label">Purchase Cost *</label>
                        <x-amount-input name="purchase_cost" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Salvage / Residual Value</label>
                        <x-amount-input name="salvage_value" />
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-col">
                        <label class="form-label">Lifespan (Years) *</label>
                        <input type="number" name="lifespan_years" class="form-control" value="5" min="1" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Depreciation Method *</label>
                        <select name="depreciation_method" class="form-control" required>
                            <option value="straight_line">Straight Line (SLM)</option>
                            <option value="reducing_balance">Reducing Balance (DBM)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('registerAssetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Register Asset</button>
            </div>
        </form>
    </div>
</div>
@endsection
