@props([
    'name' => 'bank_account_id',
    'id' => null,
    'bankAccounts' => null,
    'selected' => null,
    'placeholder' => 'Select Bank Account...',
    'required' => false,
    'showBalance' => true,
    'allowNone' => true,
    'noneLabel' => '-- Cash in Hand / Unassigned --',
    'onchange' => null
])

@php
    $elementId = $id ?? 'bank_account_selector_' . uniqid();
    
    if ($bankAccounts === null) {
        if (\Illuminate\Support\Facades\Schema::hasTable('bank_accounts')) {
            $accounts = \Illuminate\Support\Facades\DB::table('bank_accounts')->get();
            foreach ($accounts as $acc) {
                $inflow = \Illuminate\Support\Facades\DB::table('transactions')
                    ->where('bank_account_id', $acc->id)
                    ->where('type', 'income')
                    ->sum('amount');
                $outflow = \Illuminate\Support\Facades\DB::table('transactions')
                    ->where('bank_account_id', $acc->id)
                    ->where('type', 'expense')
                    ->sum('amount');
                $acc->current_balance = ($acc->opening_balance ?? 0) + $inflow - $outflow;
            }
            $bankAccounts = $accounts;
        } else {
            $bankAccounts = collect();
        }
    } else {
        // Ensure balances exist on passed collection
        foreach ($bankAccounts as $acc) {
            if (!isset($acc->current_balance)) {
                $inflow = \Illuminate\Support\Facades\DB::table('transactions')
                    ->where('bank_account_id', $acc->id)
                    ->where('type', 'income')
                    ->sum('amount');
                $outflow = \Illuminate\Support\Facades\DB::table('transactions')
                    ->where('bank_account_id', $acc->id)
                    ->where('type', 'expense')
                    ->sum('amount');
                $acc->current_balance = ($acc->opening_balance ?? 0) + $inflow - $outflow;
            }
        }
    }

    $selectedAcc = $selected ? collect($bankAccounts)->firstWhere('id', $selected) : null;
@endphp

<div class="bank-account-selector-wrapper" id="{{ $elementId }}" style="position:relative; width:100%;">
    <input 
        type="hidden" 
        name="{{ $name }}" 
        id="input_{{ $elementId }}"
        class="bank-account-id-hidden" 
        value="{{ $selected ?? '' }}"
        @if($required) required @endif
    >
    
    <!-- Trigger Button -->
    <div 
        class="bank-select-trigger" 
        onclick="toggleBankAccountDropdown('{{ $elementId }}')" 
        style="display:flex; align-items:center; justify-content:space-between; padding:0.65rem 1rem; background:var(--bg-card, #202431); border:1px solid var(--border, rgba(255,255,255,0.15)); border-radius:8px; cursor:pointer; min-height:42px; transition:all 0.15s ease;"
    >
        <div class="selected-bank-label" id="label_{{ $elementId }}" style="font-size:0.9rem; color:{{ $selectedAcc ? 'var(--text-heading, #f8fafc)' : 'var(--text-muted, #94a3b8)' }}; font-weight:500; display:flex; align-items:center; gap:0.5rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            @if($selectedAcc)
                <ion-icon name="card-outline" style="color:var(--primary, #8b5cf6); font-size:1.1rem; flex-shrink:0;"></ion-icon>
                <span>
                    <strong>{{ $selectedAcc->bank_name }}</strong>
                    @if(!empty($selectedAcc->account_no))
                        <span style="font-size:0.8rem; color:var(--text-muted, #94a3b8); margin-left:0.25rem;">({{ $selectedAcc->account_no }})</span>
                    @endif
                </span>
                @if($showBalance && isset($selectedAcc->current_balance))
                    <span class="badge" style="background:rgba(16,185,129,0.15); color:#10b981; font-size:0.75rem; font-weight:700; padding:1px 6px; border-radius:4px; margin-left:auto;">
                        {{ $selectedAcc->currency ?: 'LKR' }} {{ number_format($selectedAcc->current_balance, 2) }}
                    </span>
                @endif
            @else
                <ion-icon name="business-outline" style="color:var(--text-muted, #94a3b8); font-size:1.1rem; flex-shrink:0;"></ion-icon>
                <span>{{ $placeholder }}</span>
            @endif
        </div>
        <ion-icon name="chevron-down-outline" style="color:var(--text-muted, #94a3b8); font-size:1.1rem; margin-left:0.5rem; flex-shrink:0;"></ion-icon>
    </div>

    <!-- Dropdown Menu -->
    <div 
        class="bank-dropdown-menu" 
        id="dropdown_{{ $elementId }}" 
        style="display:none; position:absolute; top:105%; left:0; right:0; background:var(--bg-card, #202431); border:1px solid var(--border, rgba(255,255,255,0.18)); border-radius:10px; box-shadow:0 14px 35px rgba(0,0,0,0.5); z-index:999999; max-height:280px; overflow-y:auto; padding:0.5rem; backdrop-filter:blur(10px);"
    >
        <div style="position:relative; margin-bottom:0.5rem;">
            <ion-icon name="search-outline" style="position:absolute; left:8px; top:50%; transform:translateY(-50%); color:var(--text-muted, #94a3b8); font-size:0.9rem; pointer-events:none;"></ion-icon>
            <input 
                type="text" 
                class="bank-search-input form-control" 
                placeholder="Search bank name or account number..." 
                onkeyup="filterBankAccountList('{{ $elementId }}', this.value)" 
                style="padding:0.4rem 0.75rem 0.4rem 2rem; font-size:0.82rem; background:var(--bg-page, #1a1d27); border:1px solid var(--border, rgba(255,255,255,0.12)); color:var(--text-heading, #f8fafc); border-radius:6px; width:100%; box-sizing:border-box;"
            >
        </div>
        
        <div class="bank-options-list" id="list_{{ $elementId }}">
            @if($allowNone)
                <div 
                    class="bank-option-item" 
                    onclick="selectBankAccountOption('{{ $elementId }}', '', '{{ addslashes($noneLabel) }}', '', '')" 
                    style="padding:0.55rem 0.75rem; border-radius:6px; cursor:pointer; font-size:0.85rem; color:var(--text-muted, #94a3b8); transition:all 0.15s ease;"
                >
                    <em>{{ $noneLabel }}</em>
                </div>
            @endif

            @forelse($bankAccounts as $acc)
                @php
                    $bal = $acc->current_balance ?? 0;
                    $balColor = $bal > 0 ? '#10b981' : ($bal < 0 ? '#ef4444' : 'var(--text-muted, #94a3b8)');
                    $balBg = $bal > 0 ? 'rgba(16,185,129,0.12)' : ($bal < 0 ? 'rgba(239,68,68,0.12)' : 'rgba(255,255,255,0.06)');
                    $searchData = strtolower(($acc->bank_name ?? '') . ' ' . ($acc->account_no ?? '') . ' ' . ($acc->currency ?? ''));
                @endphp
                <div 
                    class="bank-option-item" 
                    data-id="{{ $acc->id }}" 
                    data-search="{{ $searchData }}" 
                    onclick="selectBankAccountOption('{{ $elementId }}', '{{ $acc->id }}', '{{ addslashes($acc->bank_name) }}', '{{ addslashes($acc->account_no ?? '') }}', '{{ $acc->currency ?: 'LKR' }}', '{{ number_format($bal, 2) }}')" 
                    style="padding:0.55rem 0.75rem; border-radius:6px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:all 0.15s ease; border-left:3px solid transparent;"
                >
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <ion-icon name="card-outline" style="color:var(--primary, #8b5cf6); font-size:1.1rem; flex-shrink:0;"></ion-icon>
                        <div>
                            <strong style="color:var(--text-heading, #f8fafc); font-size:0.88rem; display:block;">{{ $acc->bank_name }}</strong>
                            @if(!empty($acc->account_no))
                                <div style="font-size:0.75rem; color:var(--text-muted, #94a3b8);">Acc: {{ $acc->account_no }}</div>
                            @endif
                        </div>
                    </div>
                    @if($showBalance)
                        <div style="text-align:right;">
                            <span class="badge" style="background:{{ $balBg }}; color:{{ $balColor }}; font-size:0.75rem; font-weight:700; padding:2px 7px; border-radius:4px;">
                                {{ $acc->currency ?: 'LKR' }} {{ number_format($bal, 2) }}
                            </span>
                            <div style="font-size:0.68rem; color:var(--text-muted, #94a3b8); margin-top:2px;">Live Balance</div>
                        </div>
                    @endif
                </div>
            @empty
                <div style="padding:1rem; text-align:center; color:var(--text-muted, #94a3b8); font-size:0.82rem;">
                    No bank accounts found. Add one in Masters &gt; Bank Accounts.
                </div>
            @endforelse
        </div>
    </div>
</div>

<script>
if (typeof window.toggleBankAccountDropdown === 'undefined') {
    window.toggleBankAccountDropdown = function(elementId) {
        const dd = document.getElementById('dropdown_' + elementId);
        if (!dd) return;
        const isHidden = dd.style.display === 'none' || dd.style.display === '';
        
        // Close other bank dropdowns
        document.querySelectorAll('.bank-dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });

        if (isHidden) {
            dd.style.display = 'block';
            const search = dd.querySelector('.bank-search-input');
            if (search) {
                search.value = '';
                filterBankAccountList(elementId, '');
                setTimeout(() => search.focus(), 50);
            }
        } else {
            dd.style.display = 'none';
        }
    };

    window.filterBankAccountList = function(elementId, query) {
        const list = document.getElementById('list_' + elementId);
        if (!list) return;
        const q = (query || '').toLowerCase().trim();
        const items = list.querySelectorAll('.bank-option-item');
        items.forEach(item => {
            const search = item.dataset.search || '';
            if (q === '' || search.includes(q)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    };

    window.selectBankAccountOption = function(elementId, id, bankName, accountNo, currency, formattedBalance) {
        const input = document.getElementById('input_' + elementId);
        const label = document.getElementById('label_' + elementId);
        const dd = document.getElementById('dropdown_' + elementId);

        if (input) {
            input.value = id;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (label) {
            if (id) {
                let html = `
                    <ion-icon name="card-outline" style="color:var(--primary, #8b5cf6); font-size:1.1rem; flex-shrink:0;"></ion-icon>
                    <span>
                        <strong>${bankName}</strong>
                        ${accountNo ? `<span style="font-size:0.8rem; color:var(--text-muted, #94a3b8); margin-left:0.25rem;">(${accountNo})</span>` : ''}
                    </span>
                `;
                if (formattedBalance) {
                    html += `
                        <span class="badge" style="background:rgba(16,185,129,0.15); color:#10b981; font-size:0.75rem; font-weight:700; padding:1px 6px; border-radius:4px; margin-left:auto;">
                            ${currency || 'LKR'} ${formattedBalance}
                        </span>
                    `;
                }
                label.innerHTML = html;
                label.style.color = 'var(--text-heading, #f8fafc)';
            } else {
                label.innerHTML = `
                    <ion-icon name="business-outline" style="color:var(--text-muted, #94a3b8); font-size:1.1rem; flex-shrink:0;"></ion-icon>
                    <span>${bankName}</span>
                `;
                label.style.color = 'var(--text-muted, #94a3b8)';
            }
        }

        if (dd) {
            dd.style.display = 'none';
        }

        @if($onchange)
            try {
                const fn = {!! $onchange !!};
                if (typeof fn === 'function') fn(id, { bankName, accountNo, currency, formattedBalance });
            } catch(e) {}
        @endif
    };

    // Helper to set selected bank programmatically
    window.setBankAccountSelectorValue = function(elementId, id) {
        const input = document.getElementById('input_' + elementId);
        const list = document.getElementById('list_' + elementId);
        if (!input || !list) return;

        if (!id) {
            selectBankAccountOption(elementId, '', '{{ addslashes($noneLabel) }}', '', '', '');
            return;
        }

        const item = list.querySelector(`.bank-option-item[data-id="${id}"]`);
        if (item) {
            item.click();
        }
    };

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.bank-account-selector-wrapper')) {
            document.querySelectorAll('.bank-dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
}
</script>

<style>
.bank-option-item:hover {
    background: rgba(139, 92, 246, 0.15) !important;
    border-left-color: var(--primary, #8b5cf6) !important;
}
.bank-select-trigger:hover {
    border-color: var(--primary, #8b5cf6) !important;
}
</style>
