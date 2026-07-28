@props([
    'from' => request('from', date('Y-m-01')),
    'to' => request('to', date('Y-m-t')),
    'action' => url()->current(),
])

<div class="date-range-picker-container" style="background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:0.75rem 1rem; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.75rem;">
    <form action="{{ $action }}" method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem; margin:0;" id="dateRangeForm">
        @foreach(request()->except(['from', 'to']) as $key => $val)
            @if(is_array($val))
                @foreach($val as $v)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $val }}">
            @endif
        @endforeach

        <div style="display:flex; align-items:center; gap:0.5rem;">
            <label style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin:0;">From:</label>
            <input type="date" name="from" value="{{ $from }}" class="form-control" style="padding:0.4rem 0.65rem; font-size:0.85rem; border-radius:8px;" onchange="this.form.submit()">
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem;">
            <label style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin:0;">To:</label>
            <input type="date" name="to" value="{{ $to }}" class="form-control" style="padding:0.4rem 0.65rem; font-size:0.85rem; border-radius:8px;" onchange="this.form.submit()">
        </div>
    </form>

    <!-- Quick Presets -->
    <div style="display:flex; align-items:center; gap:0.4rem;" class="mobile-hide">
        <span style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">Presets:</span>
        <button type="button" class="btn btn-outline" style="padding:0.25rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="setPreset('this_month')">This Month</button>
        <button type="button" class="btn btn-outline" style="padding:0.25rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="setPreset('last_month')">Last Month</button>
        <button type="button" class="btn btn-outline" style="padding:0.25rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="setPreset('ytd')">YTD</button>
        <button class="btn btn-outline mobile-hide" style="padding:0.25rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="window.print()">
            <ion-icon name="print-outline" style="vertical-align:middle;"></ion-icon> Print
        </button>
    </div>
</div>

<script>
    function setPreset(type) {
        const form = document.getElementById('dateRangeForm');
        const fromInput = form.querySelector('input[name="from"]');
        const toInput = form.querySelector('input[name="to"]');
        const now = new Date();

        if (type === 'this_month') {
            const first = new Date(now.getFullYear(), now.getMonth(), 1);
            const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            fromInput.value = formatDate(first);
            toInput.value = formatDate(last);
        } else if (type === 'last_month') {
            const first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const last = new Date(now.getFullYear(), now.getMonth(), 0);
            fromInput.value = formatDate(first);
            toInput.value = formatDate(last);
        } else if (type === 'ytd') {
            const first = new Date(now.getFullYear(), 0, 1);
            fromInput.value = formatDate(first);
            toInput.value = formatDate(now);
        }

        form.submit();
    }

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
</script>
