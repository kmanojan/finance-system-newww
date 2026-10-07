@extends('layouts.app')
@section('title', $employee->full_name . ' - Salary Configuration')

@section('content')
<script>
function salaryCalculator(initialData) {
    return {
        basic: initialData.basic || 0,
        components: initialData.components || [],
        allowanceDropdownOpen: false,
        deductionDropdownOpen: false,
        isSubmitting: false,
        saveMessage: '',
        saveError: '',
        totalAllowances: 0,
        grossSalary: 0,
        epfBaseSalary: 0,
        employeeEpf: 0,
        apitTax: 0,
        totalDeductions: 0,
        netSalary: 0,
        employerEpf: 0,
        employerEtf: 0,
        totalEmployerCost: 0,
        ctc: 0,

        init() {
            this.recalculate();
        },

        updateBasicFromInput(event) {
            const wrapper = event.target.closest('.amount-input-wrapper');
            if (wrapper) {
                const hidden = wrapper.querySelector('.amount-hidden');
                if (hidden && hidden.value !== '') {
                    this.basic = parseFloat(hidden.value) || 0;
                    this.recalculate();
                    return;
                }
                const display = wrapper.querySelector('.amount-display-input');
                if (display) {
                    const raw = (display.value || '').toString().replace(/,/g, '');
                    this.basic = parseFloat(raw) || 0;
                    this.recalculate();
                    return;
                }
            }
            const raw = (event.target.value || '').toString().replace(/,/g, '');
            this.basic = parseFloat(raw) || 0;
            this.recalculate();
        },

        formatComponentAmount(event, comp) {
            const input = event.target;
            let val = input.value.replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) {
                parts.pop();
                val = parts.join('.');
            }
            if (parts.length === 2 && parts[1].length > 2) {
                parts[1] = parts[1].substring(0, 2);
                val = parts.join('.');
            }

            if (parts[0].length > 0) {
                parts[0] = parseInt(parts[0], 10).toLocaleString('en-US');
                input.value = parts.join('.');
            } else {
                input.value = val;
            }

            comp.value = parseFloat(val) || 0;
            const hidden = input.parentElement ? input.parentElement.querySelector('.amount-hidden') : null;
            if (hidden) {
                hidden.value = val;
            }
            this.recalculate();
        },

        formatComponentBlur(event, comp) {
            const input = event.target;
            const val = parseFloat(comp.value);
            if (!isNaN(val) && val > 0) {
                input.value = val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                input.value = '';
                comp.value = 0;
            }
            this.recalculate();
        },

        toggleComponent(comp, status) {
            comp.enabled = !!status;
            this.recalculate();
        },

        calculateSingleComponent(comp) {
            if (!comp.enabled) return 0;
            const val = parseFloat(comp.value) || 0;
            if (comp.calc_type === 'fixed_amount') {
                return val;
            }
            const base = (comp.calc_base === 'on_epf_base') ? this.epfBaseSalary : this.basic;
            return Math.round((base * (val / 100)) * 100) / 100;
        },

        recalculate() {
            const basicSalary = parseFloat(this.basic) || 0;
            
            // 1. Calculate Allowances & EPF Base
            let allowances = 0;
            let epfAllowances = 0;
            this.components.forEach(c => {
                if (c.type === 'earning' && c.code !== 'BASIC' && c.enabled) {
                    const amt = parseFloat(c.value) || 0;
                    allowances += amt;
                    if (c.is_epf_eligible) {
                        epfAllowances += amt;
                    }
                }
            });

            this.totalAllowances = allowances;
            this.grossSalary = basicSalary + allowances;
            this.epfBaseSalary = basicSalary + epfAllowances;

            // 2. Calculate Deductions
            let deductions = 0;
            let eeEpf = 0;
            let tax = 0;

            this.components.forEach(c => {
                if (c.type === 'deduction' && c.enabled) {
                    const amt = this.calculateSingleComponent(c);
                    deductions += amt;
                    if (c.code === 'EPF_EE') {
                        eeEpf = amt;
                    } else if (c.code === 'APIT') {
                        tax = amt;
                    }
                }
            });

            this.employeeEpf = eeEpf;
            this.apitTax = tax;
            this.totalDeductions = deductions;
            this.netSalary = Math.max(0, this.grossSalary - deductions);

            // 3. Calculate Employer Contributions
            let erEpf = 0;
            let erEtf = 0;
            this.components.forEach(c => {
                if (c.type === 'employer_contribution' && c.enabled) {
                    const amt = this.calculateSingleComponent(c);
                    if (c.code === 'EPF_ER') erEpf = amt;
                    if (c.code === 'ETF_ER') erEtf = amt;
                }
            });

            this.employerEpf = erEpf;
            this.employerEtf = erEtf;
            this.totalEmployerCost = erEpf + erEtf;
            this.ctc = this.grossSalary + this.totalEmployerCost;
        },

        formatNumber(num) {
            return (parseFloat(num) || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        async submitForm() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;
            this.saveMessage = '';
            this.saveError = '';

            const form = document.getElementById('salaryConfigForm');
            if (form && form.reportValidity && !form.reportValidity()) {
                this.isSubmitting = false;
                return;
            }

            const basicInput = form ? form.querySelector('input[name="basic_salary"]') : null;
            const basicVal = basicInput ? (parseFloat(basicInput.value) || parseFloat(this.basic) || 0) : (parseFloat(this.basic) || 0);

            const payload = {
                basic_salary: basicVal,
                currency: form ? (form.querySelector('select[name="currency"]')?.value || 'LKR') : 'LKR',
                payment_mode: form ? (form.querySelector('select[name="payment_mode"]')?.value || 'bank_transfer') : 'bank_transfer',
                bank_name: form ? (form.querySelector('input[name="bank_name"]')?.value || null) : null,
                bank_account_no: form ? (form.querySelector('input[name="bank_account_no"]')?.value || null) : null,
                bank_branch: form ? (form.querySelector('input[name="bank_branch"]')?.value || null) : null,
                epf_number: form ? (form.querySelector('input[name="epf_number"]')?.value || null) : null,
                components: this.components.map(c => ({
                    id: c.id,
                    value: parseFloat(c.value) || 0,
                    enabled: c.enabled ? 1 : 0
                }))
            };

            try {
                const res = await fetch('/employees/{{ $employee->id }}/salary', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.saveMessage = data.message || 'Salary profile updated successfully!';
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    this.saveError = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to save salary profile.');
                }
            } catch (err) {
                console.error(err);
                this.saveError = 'Network error while saving salary profile.';
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}

window.salaryCalculator = salaryCalculator;

window.initialSalaryData = {
    basic: {{ (float) ($employee->basic_salary ?? 0) }},
    components: @json($componentsData)
};

document.addEventListener('alpine:init', () => {
    if (window.Alpine) {
        Alpine.data('salaryCalculator', salaryCalculator);
    }
});
</script>

<div id="salaryCalculatorRoot" x-data="salaryCalculator(window.initialSalaryData)">
    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div style="display:flex; align-items:center; gap:1rem;">
            <a href="/employees" class="btn btn-outline" style="padding:0.5rem; border-radius:8px;" title="Back to Employees">
                <ion-icon name="arrow-back-outline" style="font-size:1.2rem;"></ion-icon>
            </a>
            <div style="display:flex; align-items:center; gap:1rem;">
                <img src="{{ $employee->profile_picture_url ?? 'https://ui-avatars.com/api/?name='.urlencode($employee->full_name).'&background=8b5cf6&color=fff' }}" 
                     style="width:52px; height:52px; border-radius:50%; object-fit:cover; border:2px solid var(--primary-light);">
                <div>
                    <h1 style="font-size:1.5rem; font-weight:700; color:var(--text-heading); margin:0;">
                        {{ $employee->full_name }}
                    </h1>
                    <div style="font-size:0.85rem; color:var(--text-muted); display:flex; align-items:center; gap:0.75rem; margin-top:0.25rem;">
                        <span>Code: <strong style="color:var(--text-main);">{{ $employee->employee_code ?? 'N/A' }}</strong></span>
                        <span>•</span>
                        <span>{{ $employee->job_position ?? 'Team Member' }}</span>
                        <span>•</span>
                        <span class="badge {{ $employee->status === 'active' ? 'badge-success' : 'badge-danger' }}" style="font-size:0.75rem;">
                            {{ ucfirst($employee->status) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <button type="button" @click="submitForm()" :disabled="isSubmitting" class="btn btn-primary" style="display:flex; align-items:center; gap:0.5rem; padding:0.65rem 1.25rem; font-weight:600;">
                <ion-icon :name="isSubmitting ? 'hourglass-outline' : 'save-outline'" style="font-size:1.15rem;"></ion-icon>
                <span x-text="isSubmitting ? 'Saving Profile...' : 'Save Salary Profile'">Save Salary Profile</span>
            </button>
        </div>
    </div>

    <!-- Live Toast / Banner Feedback -->
    <div x-show="saveMessage" x-cloak style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.3); display: flex; align-items: center; gap: 0.5rem;">
        <ion-icon name="checkmark-circle-outline" style="font-size:1.3rem;"></ion-icon>
        <span x-text="saveMessage"></span>
    </div>

    <div x-show="saveError" x-cloak style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(239, 68, 68, 0.3); display: flex; align-items: center; gap: 0.5rem;">
        <ion-icon name="alert-circle-outline" style="font-size:1.3rem;"></ion-icon>
        <span x-text="saveError"></span>
    </div>

    @if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.3);">
        {{ session('success') }}
    </div>
    @endif

    @if(isset($errors) && $errors->any())
    <div style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(239, 68, 68, 0.3);">
        <ul style="margin:0; padding-left:1.25rem;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <style>
    .salary-layout-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        align-items: start;
    }
    @media (min-width: 1024px) {
        .salary-layout-grid {
            grid-template-columns: 2fr 1fr !important;
        }
    }
    .component-card-active {
        background: var(--bg-card);
        border: 1px solid rgba(16, 185, 129, 0.35);
        border-radius: 10px;
        padding: 0.85rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: all 0.2s ease;
    }
    .component-card-active:hover {
        border-color: var(--success);
    }
    .component-card-inactive {
        background: var(--bg-page);
        border: 1px dashed var(--border);
        border-radius: 10px;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        opacity: 0.75;
        transition: all 0.2s ease;
    }
    .component-card-inactive:hover {
        opacity: 1;
        border-color: var(--text-muted);
    }
    </style>

    <!-- Main Grid: Config Form & Live Summary Card -->
    <div class="salary-layout-grid">
        
        <!-- Left: Salary Configuration Form -->
        <form id="salaryConfigForm" method="POST" action="/employees/{{ $employee->id }}/salary" @submit.prevent="submitForm()">
            @csrf
            
            <!-- Basic Compensation Card -->
            <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-light); pb:0.75rem;">
                    <h3 style="margin:0; font-size:1.1rem; font-weight:600; color:var(--text-heading); display:flex; align-items:center; gap:0.5rem;">
                        <ion-icon name="cash-outline" style="color:var(--primary); font-size:1.3rem;"></ion-icon>
                        Basic Salary & Payment Mode
                    </h3>
                    <span style="font-size:0.8rem; color:var(--text-muted);">Anchor base for EPF/ETF</span>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Basic Salary *</label>
                        <div style="position:relative;" 
                             @input="updateBasicFromInput($event)" 
                             @change="updateBasicFromInput($event)"
                             @blur.capture="updateBasicFromInput($event)">
                            <x-amount-input 
                                name="basic_salary" 
                                id="basic_salary" 
                                :value="$employee->basic_salary ?? ''" 
                                required="true" 
                                placeholder="0.00" 
                                class="form-control" 
                                style="font-size:1.15rem; font-weight:700; color:var(--text-heading);" 
                            />
                        </div>
                        <small style="color:var(--text-muted); font-size:0.75rem;">Contracted baseline monthly amount.</small>
                    </div>

                    <div class="form-col">
                        <label class="form-label">Currency *</label>
                        <select name="currency" class="form-control" style="padding:0.75rem;">
                            <option value="LKR" {{ ($employee->currency ?? 'LKR') === 'LKR' ? 'selected' : '' }}>LKR - Sri Lankan Rupee (Rs)</option>
                            <option value="USD" {{ ($employee->currency ?? '') === 'USD' ? 'selected' : '' }}>USD - US Dollar ($)</option>
                            <option value="EUR" {{ ($employee->currency ?? '') === 'EUR' ? 'selected' : '' }}>EUR - Euro (€)</option>
                            <option value="GBP" {{ ($employee->currency ?? '') === 'GBP' ? 'selected' : '' }}>GBP - British Pound (£)</option>
                            <option value="AED" {{ ($employee->currency ?? '') === 'AED' ? 'selected' : '' }}>AED - UAE Dirham</option>
                        </select>
                    </div>

                    <div class="form-col">
                        <label class="form-label">Disbursement Mode *</label>
                        <select name="payment_mode" class="form-control" style="padding:0.75rem;">
                            <option value="bank_transfer" {{ ($employee->payment_mode ?? 'bank_transfer') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                            <option value="cash" {{ ($employee->payment_mode ?? '') === 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="cheque" {{ ($employee->payment_mode ?? '') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem;">
                    <div class="form-col">
                        <label class="form-label">EPF / ETF Member No.</label>
                        <input type="text" name="epf_number" value="{{ $employee->epf_number }}" class="form-control" placeholder="E.g. EPF/04821">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" value="{{ $employee->bank_name }}" class="form-control" placeholder="E.g. Commercial Bank of Ceylon">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Branch</label>
                        <input type="text" name="bank_branch" value="{{ $employee->bank_branch }}" class="form-control" placeholder="E.g. Colombo 03 / Jaffna">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="bank_account_no" value="{{ $employee->bank_account_no }}" class="form-control" placeholder="Account Number">
                    </div>
                </div>
            </div>

            <!-- Components Breakdown List -->
            <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-light); pb:0.75rem;">
                    <h3 style="margin:0; font-size:1.1rem; font-weight:600; color:var(--text-heading); display:flex; align-items:center; gap:0.5rem;">
                        <ion-icon name="options-outline" style="color:var(--primary); font-size:1.3rem;"></ion-icon>
                        Salary Components & Allowances
                    </h3>
                    <a href="/master/salary-components" target="_blank" style="font-size:0.8rem; color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:0.25rem;">
                        <span>Manage Master Heads</span>
                        <ion-icon name="open-outline"></ion-icon>
                    </a>
                </div>

                <!-- Earnings / Allowances -->
                <div style="margin-bottom:1.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--success); text-transform:uppercase; letter-spacing:0.5px; margin:0; display:flex; align-items:center; gap:0.4rem;">
                            <ion-icon name="add-circle-outline" style="font-size:1.2rem;"></ion-icon>
                            Earnings & Role Allowances (+)
                        </h4>

                        <div x-show="components.filter(c => c.type === 'earning' && c.code !== 'BASIC' && !c.enabled).length > 0" style="position:relative;" @click.outside="allowanceDropdownOpen = false">
                            <button type="button" @click.stop="allowanceDropdownOpen = !allowanceDropdownOpen" class="btn btn-outline btn-sm" style="font-size:0.8rem; padding:0.4rem 0.75rem; display:flex; align-items:center; gap:0.4rem; border-radius:8px;">
                                <ion-icon name="add-circle-outline" style="color:var(--success); font-size:1.1rem;"></ion-icon>
                                <span>+ Assign Role Allowance</span>
                                <ion-icon name="chevron-down-outline" style="font-size:0.75rem;"></ion-icon>
                            </button>
                            <div x-show="allowanceDropdownOpen" x-cloak
                                 style="position:absolute; right:0; top:calc(100% + 4px); background:var(--bg-card); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.18); min-width:240px; z-index:100; padding:0.4rem 0;">
                                <template x-for="c in components.filter(c => c.type === 'earning' && c.code !== 'BASIC' && !c.enabled)" :key="c.id">
                                    <button type="button" @click.stop="toggleComponent(c, true); allowanceDropdownOpen = false;"
                                            style="display:flex; justify-content:space-between; align-items:center; width:100%; padding:0.6rem 1rem; border:none; background:none; text-align:left; font-size:0.85rem; color:var(--text-heading); cursor:pointer;"
                                            onmouseover="this.style.background='var(--bg-page)'" onmouseout="this.style.background='none'">
                                        <span x-text="c.name" style="font-weight:600;"></span>
                                        <span class="badge badge-info" style="font-size:0.65rem;" x-text="c.code"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <!-- Only Assigned Active Allowances -->
                        <template x-for="(comp, idx) in components.filter(c => c.type === 'earning' && c.code !== 'BASIC' && c.enabled)" :key="comp.id">
                            <div class="component-card-active">
                                <div style="display:flex; align-items:center; gap:0.85rem; min-width:220px;">
                                    <div style="width:38px; height:38px; border-radius:8px; background:rgba(16, 185, 129, 0.12); color:var(--success); display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0;">
                                        <ion-icon name="cash-outline"></ion-icon>
                                    </div>
                                    <div>
                                        <div style="font-weight:600; color:var(--text-heading); font-size:0.92rem;" x-text="comp.name"></div>
                                        <div style="font-size:0.75rem; color:var(--text-muted); display:flex; gap:0.5rem; align-items:center; margin-top:2px;">
                                            <span class="badge badge-info" style="font-size:0.65rem;" x-text="comp.code"></span>
                                            <template x-if="comp.is_epf_eligible">
                                                <span class="badge badge-warning" style="font-size:0.65rem;">EPF Eligible</span>
                                            </template>
                                            <template x-if="!comp.is_epf_eligible">
                                                <span style="font-size:0.72rem; color:var(--text-muted);">Non-EPF</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap;">
                                    <div style="width:160px;">
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:3px;">Monthly Amount (LKR)</div>
                                        <div class="amount-input-wrapper" style="position: relative;">
                                            <input type="text" 
                                                   class="form-control amount-display-input" 
                                                   style="padding:0.45rem 0.65rem; font-size:0.95rem; font-weight:600; text-align:right;"
                                                   placeholder="0.00"
                                                   x-effect="if (document.activeElement !== $el) { $el.value = (comp.value && parseFloat(comp.value) > 0) ? formatNumber(comp.value) : ''; }"
                                                   @input="formatComponentAmount($event, comp)"
                                                   @blur="formatComponentBlur($event, comp)"
                                                   @focus="$event.target.select()">
                                            <input type="hidden" class="amount-hidden" :value="comp.value || ''">
                                        </div>
                                    </div>
                                    <div style="min-width:110px; text-align:right;">
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:3px;">Monthly Effect</div>
                                        <span class="tabular-nums" style="font-weight:700; color:var(--success); font-size:1rem;" 
                                               x-text="'+ ' + formatNumber(comp.value)"></span>
                                    </div>
                                    <button type="button" @click="toggleComponent(comp, false)" 
                                            class="btn btn-outline" style="font-size:0.8rem; color:var(--danger); border-color:rgba(239,68,68,0.3); padding:0.4rem 0.75rem; display:flex; align-items:center; gap:0.3rem;"
                                            title="Unassign this allowance">
                                        <ion-icon name="trash-outline"></ion-icon>
                                        <span>Remove</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Empty State when no allowances are assigned -->
                        <div x-show="components.filter(c => c.type === 'earning' && c.code !== 'BASIC' && c.enabled).length === 0" 
                             style="text-align:center; padding:1.75rem 1.5rem; background:var(--bg-page); border:1px dashed var(--border); border-radius:10px; color:var(--text-muted);">
                            <ion-icon name="gift-outline" style="font-size:2.2rem; color:var(--text-muted); opacity:0.6; margin-bottom:0.5rem;"></ion-icon>
                            <div style="font-weight:600; color:var(--text-heading); font-size:0.95rem;">No Role Allowances Assigned</div>
                            <p style="font-size:0.82rem; margin:0.35rem 0 0.85rem 0; color:var(--text-muted);">
                                Select allowances (e.g. Petrol, Telephone, Travel, Performance) to assign to this employee.
                            </p>
                            <div style="display:flex; justify-content:center; gap:0.5rem; flex-wrap:wrap;">
                                <template x-for="item in components.filter(c => c.type === 'earning' && c.code !== 'BASIC' && !c.enabled)" :key="'quick-'+item.id">
                                    <button type="button" @click="toggleComponent(item, true)" class="btn btn-outline btn-sm" style="font-size:0.8rem; padding:0.35rem 0.75rem; border-radius:6px; display:inline-flex; align-items:center; gap:0.35rem;">
                                        <ion-icon name="add-circle-outline" style="color:var(--success); font-size:1rem;"></ion-icon>
                                        <span x-text="'+ ' + item.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Deductions -->
                <div style="margin-bottom:1.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--danger); text-transform:uppercase; letter-spacing:0.5px; margin:0; display:flex; align-items:center; gap:0.4rem;">
                            <ion-icon name="remove-circle-outline" style="font-size:1.2rem;"></ion-icon>
                            Employee Deductions (-)
                        </h4>

                        <div x-show="components.filter(c => c.type === 'deduction' && !c.enabled).length > 0" style="position:relative;" @click.outside="deductionDropdownOpen = false">
                            <button type="button" @click.stop="deductionDropdownOpen = !deductionDropdownOpen" class="btn btn-outline btn-sm" style="font-size:0.8rem; padding:0.4rem 0.75rem; display:flex; align-items:center; gap:0.4rem; border-radius:8px;">
                                <ion-icon name="remove-circle-outline" style="color:var(--danger); font-size:1.1rem;"></ion-icon>
                                <span>+ Assign Deduction</span>
                                <ion-icon name="chevron-down-outline" style="font-size:0.75rem;"></ion-icon>
                            </button>
                            <div x-show="deductionDropdownOpen" x-cloak
                                 style="position:absolute; right:0; top:calc(100% + 4px); background:var(--bg-card); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.18); min-width:240px; z-index:100; padding:0.4rem 0;">
                                <template x-for="c in components.filter(c => c.type === 'deduction' && !c.enabled)" :key="c.id">
                                    <button type="button" @click.stop="toggleComponent(c, true); deductionDropdownOpen = false;"
                                            style="display:flex; justify-content:space-between; align-items:center; width:100%; padding:0.6rem 1rem; border:none; background:none; text-align:left; font-size:0.85rem; color:var(--text-heading); cursor:pointer;"
                                            onmouseover="this.style.background='var(--bg-page)'" onmouseout="this.style.background='none'">
                                        <span x-text="c.name" style="font-weight:600;"></span>
                                        <span class="badge badge-info" style="font-size:0.65rem;" x-text="c.code"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <template x-for="(comp, idx) in components.filter(c => c.type === 'deduction' && c.enabled)" :key="comp.id">
                            <div class="component-card-active">
                                <div style="display:flex; align-items:center; gap:0.85rem; min-width:220px;">
                                    <div style="width:38px; height:38px; border-radius:8px; background:rgba(239, 68, 68, 0.12); color:var(--danger); display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0;">
                                        <ion-icon name="remove-circle-outline"></ion-icon>
                                    </div>
                                    <div>
                                        <div style="font-weight:600; color:var(--text-heading); font-size:0.92rem;" x-text="comp.name"></div>
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                            <span class="badge badge-info" style="font-size:0.65rem; margin-right:4px;" x-text="comp.code"></span>
                                            <span x-text="comp.calc_type === 'percentage' ? comp.value + '% ' + (comp.calc_base === 'on_epf_base' ? 'of EPF Base' : 'of Basic') : 'Fixed Monthly Deduction'"></span>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap;">
                                    <div style="width:160px;">
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:3px;" x-text="comp.calc_type === 'percentage' ? 'Rate (%)' : 'Amount (LKR)'"></div>
                                        <template x-if="comp.calc_type === 'fixed_amount'">
                                            <div class="amount-input-wrapper" style="position: relative;">
                                                <input type="text" 
                                                       class="form-control amount-display-input" 
                                                       style="padding:0.45rem 0.65rem; font-size:0.95rem; font-weight:600; text-align:right;"
                                                       placeholder="0.00"
                                                       x-effect="if (document.activeElement !== $el) { $el.value = (comp.value && parseFloat(comp.value) > 0) ? formatNumber(comp.value) : ''; }"
                                                       @input="formatComponentAmount($event, comp)"
                                                       @blur="formatComponentBlur($event, comp)"
                                                       @focus="$event.target.select()">
                                                <input type="hidden" class="amount-hidden" :value="comp.value || ''">
                                            </div>
                                        </template>
                                        <template x-if="comp.calc_type === 'percentage'">
                                            <input type="number" step="0.01" min="0"
                                                   x-model.number="comp.value" @input="recalculate()" 
                                                   class="form-control" style="padding:0.45rem 0.65rem; font-size:0.95rem; font-weight:600; text-align:right;">
                                        </template>
                                    </div>
                                    <div style="min-width:110px; text-align:right;">
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:3px;">Monthly Effect</div>
                                        <span class="tabular-nums" style="font-weight:700; color:var(--danger); font-size:1rem;" 
                                              x-text="'- ' + formatNumber(calculateSingleComponent(comp))"></span>
                                    </div>
                                    <template x-if="!comp.is_statutory">
                                        <button type="button" @click="toggleComponent(comp, false)" 
                                                class="btn btn-outline" style="font-size:0.8rem; color:var(--danger); border-color:rgba(239,68,68,0.3); padding:0.4rem 0.75rem; display:flex; align-items:center; gap:0.3rem;"
                                                title="Deactivate deduction">
                                            <ion-icon name="trash-outline"></ion-icon>
                                            <span>Remove</span>
                                        </button>
                                    </template>
                                    <template x-if="comp.is_statutory">
                                        <div style="min-width:70px; text-align:center;">
                                            <span class="badge badge-warning" style="font-size:0.7rem; padding:0.25rem 0.5rem;">Statutory</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Employer Contributions (Company Cost) -->
                <div>
                    <h4 style="font-size:0.9rem; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.75rem; display:flex; align-items:center; gap:0.4rem;">
                        <ion-icon name="business-outline"></ion-icon>
                        Employer Statutory Contributions (Cost to Company)
                    </h4>

                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <template x-for="(comp, idx) in components.filter(c => c.type === 'employer_contribution')" :key="comp.id">
                            <div style="background:var(--bg-page); border:1px solid var(--border-light); border-radius:8px; padding:0.75rem 1rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                                <div style="display:flex; align-items:center; gap:0.75rem; min-width:200px;">
                                    <input type="checkbox" value="1" x-model="comp.enabled" @change="recalculate()" style="width:18px; height:18px; cursor:pointer;">
                                    <div>
                                        <div style="font-weight:600; color:var(--text-heading); font-size:0.9rem;" x-text="comp.name"></div>
                                        <div style="font-size:0.75rem; color:var(--text-muted);">
                                            <span x-text="comp.value + '% of EPF Base (Paid by Employer)'"></span>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:1rem;">
                                    <div style="width:150px;">
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:2px;">Contribution %</div>
                                        <input type="number" step="0.01" 
                                               x-model.number="comp.value" @input="recalculate()" 
                                               class="form-control" style="padding:0.4rem 0.6rem; font-size:0.9rem; text-align:right;">
                                    </div>
                                    <div style="min-width:100px; text-align:right;">
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:2px;">Company Cost</div>
                                        <span class="tabular-nums" style="font-weight:600; color:var(--primary); font-size:0.95rem;" 
                                              x-text="comp.enabled ? '+ ' + formatNumber(calculateSingleComponent(comp)) : '0.00'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Single authoritative hidden inputs container for form submission -->
                <template x-for="comp in components" :key="'sync-'+comp.id">
                    <div style="display:none;">
                        <input type="hidden" :name="'components['+comp.id+'][id]'" :value="comp.id">
                        <input type="hidden" :name="'components['+comp.id+'][value]'" :value="comp.value || 0">
                        <input type="hidden" :name="'components['+comp.id+'][enabled]'" :value="comp.enabled ? '1' : '0'">
                    </div>
                </template>

            </div>
        </form>

        <!-- Right: Real-time Live Salary Breakdown & Net Pay Card -->
        <div style="position:sticky; top:1.5rem; display:flex; flex-direction:column; gap:1.5rem;">
            
            <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-light); pb:0.5rem;">
                    <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--text-heading);">Live Salary Breakdown</h3>
                    <span class="badge badge-info" style="font-size:0.7rem;">Interactive Preview</span>
                </div>

                <div style="display:flex; flex-direction:column; gap:0.6rem; font-size:0.85rem;">
                    
                    <!-- Basic -->
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:var(--text-muted);">Basic Salary</span>
                        <span class="tabular-nums" style="font-weight:600; color:var(--text-heading);" x-text="formatNumber(basic)"></span>
                    </div>

                    <!-- Allowances -->
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:var(--text-muted);">(+) Total Allowances</span>
                        <span class="tabular-nums" style="font-weight:600; color:var(--success);" x-text="formatNumber(totalAllowances)"></span>
                    </div>

                    <div style="height:1px; background:var(--border-light); margin:0.25rem 0;"></div>

                    <!-- Gross -->
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.95rem;">
                        <span style="font-weight:600; color:var(--text-heading);">Gross Salary</span>
                        <span class="tabular-nums" style="font-weight:700; color:var(--text-heading);" x-text="formatNumber(grossSalary)"></span>
                    </div>

                    <!-- EPF Base notice -->
                    <div style="background:var(--bg-page); padding:0.4rem 0.6rem; border-radius:6px; font-size:0.75rem; color:var(--text-muted); display:flex; justify-content:space-between;">
                        <span>EPF/ETF Base Earnings:</span>
                        <strong class="tabular-nums" style="color:var(--text-main);" x-text="formatNumber(epfBaseSalary)"></strong>
                    </div>

                    <div style="height:1px; background:var(--border-light); margin:0.25rem 0;"></div>

                    <!-- Deductions -->
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:var(--text-muted);">(-) Employee EPF (8%)</span>
                        <span class="tabular-nums" style="font-weight:600; color:var(--danger);" x-text="'- ' + formatNumber(employeeEpf)"></span>
                    </div>

                    <template x-if="apitTax > 0">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:var(--text-muted);">(-) APIT / Tax</span>
                            <span class="tabular-nums" style="font-weight:600; color:var(--danger);" x-text="'- ' + formatNumber(apitTax)"></span>
                        </div>
                    </template>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:var(--text-muted);">(-) Total Deductions</span>
                        <span class="tabular-nums" style="font-weight:600; color:var(--danger);" x-text="'- ' + formatNumber(totalDeductions)"></span>
                    </div>

                    <!-- Net Pay Highlight -->
                    <div style="background:rgba(16, 185, 129, 0.12); border:1px solid rgba(16, 185, 129, 0.3); border-radius:8px; padding:0.85rem 1rem; margin-top:0.5rem;">
                        <div style="font-size:0.75rem; color:var(--success); font-weight:600; text-transform:uppercase;">Net Take-Home Pay (Payable)</div>
                        <div class="tabular-nums" style="font-size:1.5rem; font-weight:800; color:var(--success); margin-top:0.25rem;" x-text="'LKR ' + formatNumber(netSalary)"></div>
                    </div>

                    <div style="height:1px; background:var(--border-light); margin:0.5rem 0;"></div>

                    <!-- Employer Contributions -->
                    <div style="font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Employer Liabilities</div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:var(--text-muted);">(+) Employer EPF (12%)</span>
                        <span class="tabular-nums" style="font-weight:600; color:var(--primary);" x-text="formatNumber(employerEpf)"></span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:var(--text-muted);">(+) Employer ETF (3%)</span>
                        <span class="tabular-nums" style="font-weight:600; color:var(--primary);" x-text="formatNumber(employerEtf)"></span>
                    </div>

                    <!-- Total CTC Highlight -->
                    <div style="background:rgba(139, 92, 246, 0.12); border:1px solid rgba(139, 92, 246, 0.3); border-radius:8px; padding:0.85rem 1rem; margin-top:0.5rem;">
                        <div style="font-size:0.75rem; color:var(--primary); font-weight:600; text-transform:uppercase;">Total Cost to Company (CTC)</div>
                        <div class="tabular-nums" style="font-size:1.35rem; font-weight:800; color:var(--primary); margin-top:0.25rem;" x-text="'LKR ' + formatNumber(ctc)"></div>
                        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">Gross + 15% Statutory Employer Contributions</div>
                    </div>
                </div>

                <button type="button" @click="submitForm()" :disabled="isSubmitting" class="btn btn-primary btn-block" style="width:100%; margin-top:1.25rem; padding:0.75rem; font-weight:600; display:flex; align-items:center; justify-content:center; gap:0.5rem; font-size:0.95rem;">
                    <ion-icon :name="isSubmitting ? 'hourglass-outline' : 'checkmark-circle-outline'" style="font-size:1.15rem;"></ion-icon>
                    <span x-text="isSubmitting ? 'Saving Profile...' : 'Save Salary Profile'">Save Salary Profile</span>
                </button>
            </div>

            <!-- Cost Allocation Project History -->
            @if($employee->costAllocations->count() > 0)
            <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.25rem;">
                <h4 style="margin:0 0 0.75rem 0; font-size:0.95rem; font-weight:600; color:var(--text-heading); display:flex; align-items:center; gap:0.4rem;">
                    <ion-icon name="briefcase-outline" style="color:var(--primary);"></ion-icon>
                    Recent Project Allocations
                </h4>
                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                    @foreach($employee->costAllocations->take(5) as $cost)
                    <div style="background:var(--bg-page); padding:0.5rem 0.75rem; border-radius:6px; display:flex; justify-content:space-between; align-items:center; font-size:0.8rem;">
                        <div>
                            <div style="font-weight:600; color:var(--text-heading);">{{ $cost->project->name ?? 'General Project' }}</div>
                            <div style="color:var(--text-muted); font-size:0.7rem;">{{ \Carbon\Carbon::parse($cost->period_start)->format('M Y') }}</div>
                        </div>
                        <div class="tabular-nums" style="font-weight:700; color:var(--text-main);">
                            {{ number_format($cost->amount, 2) }} {{ $cost->currency }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

    </div>
</div>
@endsection
