<div x-data="{
        open: false,
        type: 'employee',
        project_id: null,
        employee_id: null,
        employee_ids: [],
        server_id: null,
        cost_center_name: '',
        amount: null,
        currency: 'LKR',
        period_start: new Date().toISOString().slice(0,10),
        loading: false,
        submit() {
            this.loading = true;
            fetch('/api/cost-allocations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    project_id: this.project_id,
                    type: this.type,
                    employee_id: this.employee_id,
                    employee_ids: this.employee_ids,
                    server_id: this.server_id,
                    cost_center_name: this.cost_center_name,
                    amount: this.amount,
                    currency: this.currency,
                    period_start: this.period_start,
                }),
            }).then(response => {
                this.loading = false;
                if(response.ok) {
                    this.open = false;
                    this.amount = null;
                    this.employee_ids = [];
                    this.cost_center_name = '';
                    alert('Cost allocation(s) added successfully');
                    window.dispatchEvent(new CustomEvent('cost-allocation-added'));
                } else {
                    alert('Failed to add cost allocation.');
                }
            }).catch(e => {
                this.loading = false;
                alert('An error occurred.');
            });
        }
     }" @employee-selected.window="employee_id = $event.detail" class="quick-add-widget" style="position: relative;">
     
    <style>
        .widget-btn { background:none; border:none; color:var(--primary); font-size:1.8rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:color 0.2s; }
        .widget-btn:hover { color: var(--primary-hover); }
        .widget-panel { position: absolute; bottom: 0; left: 60px; width: 350px; background: var(--bg-card); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-card); border: 1px solid var(--border-light); display: flex; flex-direction: column; gap: 1rem; z-index: 1000; }
    </style>

    <button @click="open = !open" class="widget-btn" title="Quick Add Cost">
        <ion-icon name="add-circle"></ion-icon>
    </button>

    <div x-show="open" x-transition class="widget-panel" style="display: none;" @click.away="open = false">
        
        <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-heading);">Quick Add Cost</h3>

        <div>
            <label class="form-label">Project</label>
            <select x-model="project_id" class="form-control">
                <option value="null">Select project...</option>
                @foreach(\App\Models\Project::orderBy('name')->get() as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label">Cost Type</label>
            <div style="display: flex; gap: 1rem; margin-top: 0.25rem;">
                <label style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.9rem;">
                    <input type="radio" x-model="type" value="employee"> Employee(s)
                </label>
                <label style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.9rem;">
                    <input type="radio" x-model="type" value="server"> Server
                </label>
                <label style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.9rem;">
                    <input type="radio" x-model="type" value="other"> Other
                </label>
            </div>
        </div>

        <div x-show="type === 'employee'">
            <label class="form-label">Select Employee(s)</label>
            <div style="background: var(--bg-page); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem; max-height: 150px; overflow-y: auto;">
                @foreach(\App\Models\Employee::where('status', 'active')->orderBy('full_name')->get() as $emp)
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.3rem 0.4rem; border-bottom: 1px solid var(--border-light); cursor: pointer;">
                    <input type="checkbox" value="{{ $emp->id }}" x-model="employee_ids">
                    <span style="font-size: 0.85rem; color: var(--text-main);">{{ $emp->full_name }}</span>
                </label>
                @endforeach
            </div>
            <small style="color: var(--primary); font-size: 0.75rem; margin-top: 0.25rem; display: block;" x-show="employee_ids.length > 0">
                <span x-text="employee_ids.length"></span> employee(s) selected
            </small>
        </div>

        <div x-show="type === 'server'" @server-selected.window="server_id = $event.detail">
            <label class="form-label">Server</label>
            <x-server-selector />
        </div>

        <div x-show="type === 'other'">
            <label class="form-label">Cost Center</label>
            <input type="text" x-model="cost_center_name" class="form-control" placeholder="E.g. Marketing Tool">
        </div>

        <div>
            <label class="form-label">Amount & Currency</label>
            <div style="display: flex; gap: 0.5rem;">
                <div class="amount-input-wrapper" style="position: relative; flex: 2;">
                    <input type="text" class="form-control amount-display-input" placeholder="0.00" @input="formatAmountInput($event.target); amount = $event.target.parentElement.querySelector('.amount-hidden').value" @blur="formatAmountBlur($event.target); amount = $event.target.parentElement.querySelector('.amount-hidden').value">
                    <input type="hidden" class="amount-hidden" :value="amount">
                </div>
                <select x-model="currency" class="form-control" style="flex: 1;">
                    <option value="LKR">LKR</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
        </div>

        <div>
            <label class="form-label">Date</label>
            <input type="date" x-model="period_start" class="form-control">
        </div>

        <button @click="submit()" :disabled="loading || !project_id || !amount" class="btn btn-primary" style="width: 100%; padding: 0.75rem; border-radius: 8px; font-weight: 500; cursor: pointer; border: none; background: var(--primary); color: white; transition: background 0.2s;">
            <span x-show="!loading">Save Allocation</span>
            <span x-show="loading">Saving...</span>
        </button>
    </div>
</div>
