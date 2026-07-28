@props(['name' => 'server_id', 'id' => null, 'value' => null, 'class' => 'form-control'])

@php
    $serversList = \App\Models\Server::where('is_active', true)->orderBy('name')->get(['id', 'name', 'provider', 'reference']);
    $initialValue = $value ?? '';
@endphp

<div x-data="{
        open: false,
        search: '',
        selectedId: '{{ $initialValue }}',
        servers: {{ \Illuminate\Support\Js::from($serversList) }},
        getSelected() {
            return this.servers.find(s => s.id == this.selectedId) || null;
        },
        selectServer(srv) {
            this.selectedId = srv ? srv.id : '';
            this.open = false;
            $dispatch('server-selected', this.selectedId);
        },
        clearSearch() {
            this.search = '';
        },
        matches(srv) {
            if (!this.search) return true;
            var q = this.search.toLowerCase().trim();
            var nameMatch = srv.name ? srv.name.toLowerCase().includes(q) : false;
            var providerMatch = srv.provider ? srv.provider.toLowerCase().includes(q) : false;
            var refMatch = srv.reference ? srv.reference.toLowerCase().includes(q) : false;
            return nameMatch || providerMatch || refMatch;
        }
     }" @click.away="open = false" class="server-selector-component" style="position: relative;">

    {{-- Trigger Button --}}
    <button type="button" @click="open = !open" class="{{ $class }}" style="display: flex; align-items: center; justify-content: space-between; text-align: left; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; height: 38px; padding: 0.45rem 0.75rem; cursor: pointer; color: var(--text-main); transition: all 0.2s ease;">
        <template x-if="getSelected()">
            <span style="display: flex; align-items: center; gap: 0.6rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <ion-icon name="server-outline" style="color: var(--primary); font-size: 1.1rem; flex-shrink: 0;"></ion-icon>
                <span x-text="getSelected().name" style="font-weight: 600; font-size: 0.85rem; color: var(--text-heading);"></span>
                <span class="badge" x-show="getSelected().provider" x-text="getSelected().provider" style="background: var(--primary-light); color: var(--primary); font-size: 0.7rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: uppercase;"></span>
            </span>
        </template>
        <template x-if="!getSelected()">
            <span style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem;">
                <ion-icon name="server-outline" style="font-size: 1rem;"></ion-icon>
                <span>Select Server...</span>
            </span>
        </template>
        <ion-icon name="chevron-down-outline" style="color: var(--text-muted); font-size: 0.95rem; flex-shrink: 0;"></ion-icon>
    </button>

    <input type="hidden" name="{{ $name }}" {{ $id ? "id={$id}" : '' }} :value="selectedId">

    {{-- Dropdown Menu --}}
    <div x-show="open" x-transition style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 100; background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-card); margin-top: 0.35rem; max-height: 280px; overflow-y: auto; overflow-x: hidden;">
        
        <!-- Search Box -->
        <div style="padding: 0.65rem; border-bottom: 1px solid var(--border-light); position: sticky; top: 0; background: var(--bg-card); z-index: 10;">
            <div style="position: relative; width: 100%;">
                <ion-icon name="search-outline" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.05rem; pointer-events: none;"></ion-icon>
                <input type="text" x-model="search" class="form-control" placeholder="Search server name, provider..." style="width: 100%; padding: 0.45rem 2rem 0.45rem 2.3rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-page); color: var(--text-main); font-size: 0.8rem;" @click.stop>
                <button type="button" x-show="search.length > 0" @click="clearSearch()" style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 0.2rem; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <ion-icon name="close-circle-outline"></ion-icon>
                </button>
            </div>
        </div>

        <ul style="list-style: none; margin: 0; padding: 0.3rem;">
            <template x-for="srv in servers" :key="srv.id">
                <li x-show="matches(srv)" 
                    @click="selectServer(srv)" 
                    :style="selectedId == srv.id ? 'background: var(--primary-light); border-color: var(--primary);' : 'transparent'"
                    style="padding: 0.55rem 0.75rem; border-radius: 9px; border: 1px solid transparent; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.15s ease; margin-bottom: 0.15rem;" 
                    onmouseover="if(!this.style.background.includes('primary')) this.style.background='var(--bg-page)'" 
                    onmouseout="if(!this.style.background.includes('primary')) this.style.background='transparent'">
                    
                    <div style="display: flex; align-items: center; gap: 0.65rem; overflow: hidden; flex: 1;">
                        <div style="width: 28px; height: 28px; border-radius: 7px; background: var(--bg-page); border: 1px solid var(--border-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <ion-icon name="server-outline" style="color: var(--primary); font-size: 0.95rem;"></ion-icon>
                        </div>
                        <div style="overflow: hidden;">
                            <div x-text="srv.name" style="font-weight: 600; color: var(--text-heading); font-size: 0.84rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
                            <small style="color: var(--text-muted); font-size: 0.73rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="(srv.provider || '') + (srv.reference ? ' · ' + srv.reference : '')"></small>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.4rem; flex-shrink: 0;">
                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: var(--success); font-size: 0.68rem; font-weight: 700; padding: 0.1rem 0.35rem; border-radius: 4px;">Active</span>
                        <template x-if="selectedId == srv.id">
                            <ion-icon name="checkmark-circle" style="color: var(--primary); font-size: 1.1rem;"></ion-icon>
                        </template>
                    </div>
                </li>
            </template>
            <li x-show="servers.length === 0" style="padding: 1.25rem 0.75rem; text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                <ion-icon name="server-outline" style="font-size: 1.4rem; opacity: 0.5; margin-bottom: 0.2rem; display: block; margin-inline: auto;"></ion-icon>
                No active servers found. Add servers under Master Data.
            </li>
        </ul>
    </div>
</div>
