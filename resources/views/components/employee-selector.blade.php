<div x-data="{
        open: false,
        search: '',
        selected: null,
        employees: [],
        fetchEmployees() {
            fetch(`/api/employees?search=${encodeURIComponent(this.search)}&status=active`)
                .then(r => r.json())
                .then(data => this.employees = data.data || []);
        },
        clearSearch() {
            this.search = '';
            this.fetchEmployees();
        }
     }" x-init="fetchEmployees()" @click.away="open = false" class="employee-selector-component" style="position: relative;">

    <!-- Trigger Button -->
    <button type="button" @click="open = !open" class="form-control employee-selector-trigger" style="display: flex; align-items: center; justify-content: space-between; text-align: left; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 0.6rem 0.85rem; cursor: pointer; color: var(--text-main); transition: all 0.2s ease;">
        <template x-if="selected">
            <span style="display: flex; align-items: center; gap: 0.6rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 90%;">
                <img :src="selected.profile_picture_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(selected.full_name) + '&background=random'" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                <span x-text="selected.full_name" style="font-weight: 600; color: var(--text-heading); font-size: 0.9rem;"></span>
                <span class="text-muted" x-text="selected.job_position ? '· ' + selected.job_position : ''" style="font-size: 0.78rem; color: var(--text-muted);"></span>
            </span>
        </template>
        <template x-if="!selected">
            <span style="display: flex; align-items: center; gap: 0.6rem; color: var(--text-muted); font-size: 0.9rem;">
                <ion-icon name="person-outline" style="font-size: 1.1rem;"></ion-icon>
                <span>Select employee...</span>
            </span>
        </template>
        <ion-icon name="chevron-down-outline" style="color: var(--text-muted); font-size: 1rem; flex-shrink: 0; transition: transform 0.2s ease;"></ion-icon>
    </button>

    <!-- Dropdown Menu -->
    <div x-show="open" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 100; background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-card); margin-top: 0.35rem; max-height: 320px; overflow-y: auto; overflow-x: hidden;" x-transition>
        
        <!-- Search Bar -->
        <div style="padding: 0.75rem; border-bottom: 1px solid var(--border-light); position: sticky; top: 0; background: var(--bg-card); z-index: 10;">
            <div style="position: relative; width: 100%;">
                <ion-icon name="search-outline" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; pointer-events: none;"></ion-icon>
                <input type="text" x-model="search" @input.debounce.300ms="fetchEmployees()" class="form-control" placeholder="Search by name, position or code..." style="width: 100%; padding: 0.5rem 2.2rem 0.5rem 2.4rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-page); color: var(--text-main); font-size: 0.85rem;" @click.stop>
                <button type="button" x-show="search.length > 0" @click="clearSearch()" style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 0.2rem; display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
                    <ion-icon name="close-circle-outline"></ion-icon>
                </button>
            </div>
        </div>

        <!-- Employee List -->
        <ul style="list-style: none; margin: 0; padding: 0.35rem;">
            <template x-for="emp in employees" :key="emp.id">
                <li @click="selected = emp; $dispatch('employee-selected', emp.id); open = false" 
                    :style="selected && selected.id === emp.id ? 'background: var(--primary-light); border-color: var(--primary);' : 'transparent'"
                    style="padding: 0.6rem 0.85rem; border-radius: 10px; border: 1px solid transparent; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.15s ease; margin-bottom: 0.2rem;" 
                    onmouseover="if(!this.style.background.includes('primary')) this.style.background='var(--bg-page)'" 
                    onmouseout="if(!this.style.background.includes('primary')) this.style.background='transparent'">
                    
                    <div style="display: flex; align-items: center; gap: 0.75rem; overflow: hidden; flex: 1;">
                        <img :src="emp.profile_picture_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(emp.full_name) + '&background=random'" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                        <div style="overflow: hidden;">
                            <div x-text="emp.full_name" style="font-weight: 600; color: var(--text-heading); font-size: 0.88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
                            <small style="color: var(--text-muted); font-size: 0.75rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="`${emp.employee_code ? emp.employee_code + ' ' : ''}${emp.job_position ? '· ' + emp.job_position : ''}`"></small>
                        </div>
                    </div>

                    <template x-if="selected && selected.id === emp.id">
                        <ion-icon name="checkmark-circle" style="color: var(--primary); font-size: 1.15rem; flex-shrink: 0; margin-left: 0.5rem;"></ion-icon>
                    </template>
                </li>
            </template>
            <li x-show="employees.length === 0" style="padding: 1.5rem 1rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                <ion-icon name="person-outline" style="font-size: 1.5rem; opacity: 0.5; margin-bottom: 0.25rem; display: block; margin-inline: auto;"></ion-icon>
                No active employees found.
            </li>
        </ul>
    </div>
</div>
