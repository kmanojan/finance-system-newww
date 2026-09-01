@php
    $sidebarCategories = \Illuminate\Support\Facades\DB::table('categories')->orderBy('name')->get();
    $sidebarDepartments = \Illuminate\Support\Facades\DB::table('departments')->orderBy('name')->get();
    $sidebarBaseCurrency = \Illuminate\Support\Facades\DB::table('companies')->value('base_currency') ?? 'LKR';
    $firstExpenseCat = $sidebarCategories->where('type', 'expense')->first()?->id ?? ($sidebarCategories->first()?->id ?? '');
@endphp

<div x-data="{
        open: false,
        type: 'expense',
        currency: '{{ $sidebarBaseCurrency }}',
        transaction_date: new Date().toISOString().slice(0,10),
        payment_method: 'Normal',
        description: '',
        reference_no: '',
        loading: false,
        errorMessage: '',
        setType(newType) {
            this.type = newType;
            if (typeof filterCategorySelectorByType === 'function') {
                filterCategorySelectorByType('quick_category_selector', newType);
            }
        },
        submit() {
            const rawAmount = document.querySelector('#quick_tx_amount')?.parentElement?.querySelector('.amount-hidden')?.value;
            const catId = document.querySelector('#quick_category_selector .category-id-hidden')?.value;
            const deptId = document.querySelector('#quick_dept_selector input[name=\'department_id\']')?.value || null;
            const pmVal = document.querySelector('#quick_pm_selector')?.value || 'Normal';

            if (!rawAmount || parseFloat(rawAmount) <= 0) {
                this.errorMessage = 'Please enter a valid amount.';
                return;
            }
            if (!catId) {
                this.errorMessage = 'Please select a category.';
                return;
            }
            if (!this.description || this.description.trim() === '') {
                this.errorMessage = 'Please enter a description.';
                return;
            }

            this.errorMessage = '';
            this.loading = true;

            fetch('/transactions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    type: this.type,
                    amount: parseFloat(rawAmount),
                    currency: this.currency,
                    category_id: catId,
                    transaction_date: this.transaction_date,
                    payment_method: pmVal,
                    description: this.description,
                    department_id: deptId,
                    reference_no: this.reference_no || null,
                })
            }).then(async response => {
                this.loading = false;
                if (response.ok) {
                    this.open = false;
                    if (typeof setAmountInputValue === 'function') {
                        setAmountInputValue('quick_tx_amount', '');
                    }
                    this.description = '';
                    this.reference_no = '';
                    if (typeof showToast === 'function') {
                        showToast('Transaction recorded successfully!', 'success');
                    } else {
                        alert('Transaction recorded successfully!');
                    }
                    if (window.location.pathname.startsWith('/transactions')) {
                        window.location.reload();
                    }
                } else {
                    const data = await response.json().catch(() => ({}));
                    this.errorMessage = data.message || 'Failed to save transaction.';
                }
            }).catch(e => {
                this.loading = false;
                this.errorMessage = 'An unexpected error occurred.';
            });
        }
    }" class="quick-add-widget" style="position: relative;">
    
    <style>
        .quick-tx-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        .quick-tx-btn:hover {
            color: var(--primary-hover);
            transform: scale(1.1);
        }
        .quick-tx-panel {
            position: absolute;
            bottom: 0;
            left: 65px;
            width: 400px;
            max-width: calc(100vw - 32px);
            background: var(--bg-card);
            padding: 1.25rem;
            border-radius: 14px;
            box-shadow: var(--shadow-card, 0 10px 30px rgba(0,0,0,0.25));
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            z-index: 9999;
        }
        .quick-type-toggle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg-page);
            padding: 3px;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            gap: 4px;
        }
        .quick-type-btn {
            padding: 0.4rem 0.5rem;
            font-size: 0.82rem;
            font-weight: 700;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: all 0.15s ease;
            color: var(--text-muted);
            background: transparent;
        }
        .quick-type-btn.active-expense {
            background: #fee2e2;
            color: #b91c1c;
        }
        .quick-type-btn.active-income {
            background: #dcfce7;
            color: #15803d;
        }
        @media (max-width: 768px) {
            .quick-tx-panel {
                position: fixed;
                bottom: 80px;
                left: 16px;
                right: 16px;
                width: auto;
                max-height: 80vh;
                overflow-y: auto;
            }
        }
    </style>

    <button @click="open = !open" class="quick-tx-btn" title="Quick Add Transaction">
        <ion-icon name="add-circle"></ion-icon>
    </button>

    <div x-show="open" x-transition class="quick-tx-panel" style="display: none;" @click.away="open = false">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 0.6rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <ion-icon name="flash-outline"></ion-icon>
                </div>
                <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-heading);">Quick Add Transaction</h3>
            </div>
            <button type="button" @click="open = false" style="background: none; border: none; font-size: 1.3rem; color: var(--text-muted); cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <template x-if="errorMessage">
            <div style="background: #fee2e2; color: #991b1b; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.8rem; border: 1px solid #fecaca;" x-text="errorMessage"></div>
        </template>

        <!-- Income / Expense Segmented Switch -->
        <div class="quick-type-toggle">
            <button type="button" class="quick-type-btn" :class="{ 'active-expense': type === 'expense' }" @click="setType('expense')">
                <ion-icon name="arrow-down-circle-outline"></ion-icon> Expense (Outflow)
            </button>
            <button type="button" class="quick-type-btn" :class="{ 'active-income': type === 'income' }" @click="setType('income')">
                <ion-icon name="arrow-up-circle-outline"></ion-icon> Income (Inflow)
            </button>
        </div>

        <!-- Amount & Currency using amount-input component -->
        <div>
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Amount & Currency *</label>
            <div style="display: flex; gap: 0.5rem; align-items: stretch;">
                <div style="flex: 2; display: flex;">
                    <x-amount-input 
                        name="amount" 
                        id="quick_tx_amount" 
                        placeholder="0.00" 
                        required="true" 
                        style="font-size: 1rem; font-weight: 700; height: 40px; border-radius: 8px;" 
                    />
                </div>
                <select x-model="currency" class="form-control" style="flex: 1; font-weight: 600; height: 40px; border-radius: 8px;">
                    <option value="LKR">LKR</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="GBP">GBP</option>
                    <option value="AED">AED</option>
                    <option value="SGD">SGD</option>
                </select>
            </div>
        </div>

        <!-- Category Selector Component -->
        <div>
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Category *</label>
            <x-category-selector 
                name="category_id" 
                id="quick_category_selector" 
                :categories="$sidebarCategories" 
                :selected="$firstExpenseCat" 
                type="expense"
                required="true" 
            />
        </div>

        <!-- Payment Method & Date -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
            <div>
                <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Payment Mode</label>
                <x-payment-mode-selector 
                    name="payment_method" 
                    id="quick_pm_selector" 
                    selected="Normal" 
                    style="font-size: 0.82rem; height: 40px; border-radius: 8px;"
                    onchange="this.dispatchEvent(new Event('input', { bubbles: true }))" 
                />
            </div>
            <div>
                <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Date *</label>
                <input type="date" x-model="transaction_date" class="form-control" style="font-size: 0.82rem; height: 40px; border-radius: 8px;" required>
            </div>
        </div>

        <!-- Description -->
        <div>
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Description *</label>
            <input type="text" x-model="description" class="form-control" placeholder="e.g. Office supplies / Client payment" style="font-size: 0.85rem; border-radius: 8px;" required>
        </div>

        <!-- Department Component -->
        <div>
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Department (Optional)</label>
            <x-department-selector 
                name="department_id" 
                id="quick_dept_selector" 
                :departments="$sidebarDepartments" 
            />
        </div>

        <!-- Action Button -->
        <button type="button" @click="submit()" :disabled="loading || !description" class="btn btn-primary-gradient" style="width: 100%; padding: 0.65rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin-top: 0.25rem;">
            <ion-icon name="checkmark-circle-outline" x-show="!loading" style="font-size: 1.1rem;"></ion-icon>
            <span x-show="!loading">Save Transaction</span>
            <span x-show="loading">Saving...</span>
        </button>
    </div>
</div>
