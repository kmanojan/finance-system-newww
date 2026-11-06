(() => {
    const form = document.querySelector('[data-payroll-create]');
    if (!form) return;
    const $ = selector => form.querySelector(selector);
    const body = $('[data-payroll-rows]'), summary = $('.pr-create-totals'), status = $('[data-preview-status]');
    const initial = JSON.parse(document.getElementById('payroll-selection-state').textContent);
    const selected = new Set(initial.ids.map(String));
    const amounts = new Map(Object.entries(initial.amounts));
    const calculated = new Map(), dirty = new Set(selected);
    const money = cents => (cents / 100).toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2});
    let page = initial.page || 1, lastPage = initial.lastPage, searchTimer, previewTimer, previewRequest, pageRequest, bulkRequest;
    let previewRevision = 0, pageRevision = 0, submitting = false, bulkBusy = false;
    const csrf = $('[name="_token"]').value;
    const rows = () => [...body.querySelectorAll('[data-employee-row]')];
    const message = (text, error = false) => { status.textContent = text; status.classList.toggle('is-error', error); };
    const selectedAmounts = ids => Object.fromEntries(ids.filter(id => amounts.has(id)).map(id => [id, amounts.get(id)]));
    const errorText = data => Object.values(data.errors || {}).flat().slice(0,5).join(' ') || data.message || 'Unable to complete this request. Please try again.';

    function paintRow(row) {
        const id = row.dataset.employeeId, active = selected.has(id), c = calculated.get(id);
        row.classList.toggle('is-selected', active);
        row.querySelector('[name="employee_ids[]"]').checked = active;
        row.querySelectorAll('[data-monthly-component]').forEach(input => {
            input.disabled = !active;
            const value = amounts.get(id)?.[input.dataset.componentId] ?? '0';
            // Do not reset the caret while someone is typing.
            if (document.activeElement !== input) input.value = value;
        });
        if (c) {
            const totals = {...c,deductions:c.gross-c.net,employer:c.cost-c.gross};
            row.querySelectorAll('[data-employee-total]').forEach(el => el.textContent = money(totals[el.dataset.employeeTotal]));
            [...c.earnings,...c.deductions,...c.employer].forEach(line => {
                if (line.id) row.querySelectorAll(`[data-component-label="${line.id}"]`).forEach(el => el.textContent = money(line.amount));
            });
        }
        if (active && dirty.has(id)) row.querySelectorAll('[data-employee-total]').forEach(el => el.textContent = '—');
    }
    function totals() {
        $('[data-selection-count]').textContent = `${selected.size} selected across all pages`;
        const pending = [...selected].some(id => dirty.has(id) || !calculated.has(id));
        summary.setAttribute('aria-busy', String(pending));
        const total = {gross:0,deductions:0,net:0,cost:0};
        if (!pending) selected.forEach(id => {
            const c = calculated.get(id);
            total.gross += c.gross; total.deductions += c.gross-c.net; total.net += c.net; total.cost += c.cost;
        });
        summary.querySelectorAll('[data-preview-total]').forEach(el => el.textContent = pending ? '—' : money(total[el.dataset.previewTotal]));
    }
    function refresh() { rows().forEach(paintRow); totals(); }
    function queuePreview() {
        clearTimeout(previewTimer); previewRequest?.abort();
        const revision = ++previewRevision;
        refresh();
        const ids = [...selected].filter(id => dirty.has(id));
        if (!ids.length) { message(selected.size ? 'Totals include selected employees on every page.' : 'Select employees to calculate payroll totals.'); return; }
        message(`Calculating ${ids.length} changed employee${ids.length === 1 ? '' : 's'}…`);
        previewTimer = setTimeout(async () => {
            previewRequest = new AbortController();
            try {
                // Only changed employees are recalculated; select-all runs in bounded batches.
                for (let offset = 0; offset < ids.length; offset += 100) {
                    const batch = ids.slice(offset,offset+100);
                    const response = await fetch('/payroll/preview', {method:'POST',signal:previewRequest.signal,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({employee_ids:batch,component_amounts:selectedAmounts(batch),month:$('#month').value,company_id:$('#company_id').value})});
                    const data = await response.json();
                    if (!response.ok) throw Error(errorText(data));
                    if (revision !== previewRevision) return;
                    batch.forEach(id => { calculated.set(id,data.employees[id]); dirty.delete(id); });
                    refresh();
                    message(offset+100 < ids.length ? `Calculated ${Math.min(offset+100,ids.length)} of ${ids.length} employees…` : 'Totals include selected employees on every page. Review contributions and tax before approval.');
                }
            } catch (error) {
                if (error.name !== 'AbortError' && revision === previewRevision) message(error.message,true);
            }
        },250);
    }
    function choose(ids) {
        const union = new Set([...selected,...ids]);
        if (union.size > 1000) { message('A payroll can include up to 1,000 employees. Clear some selections or refine the search.',true); refresh(); return; }
        ids.forEach(id => { if (!selected.has(id)) {selected.add(id);dirty.add(id);} });
        queuePreview();
    }
    function navigation(busy = false) {
        $('[data-previous-page]').disabled = busy || page <= 1;
        $('[data-next-page]').disabled = busy || page >= lastPage;
        $('[data-page-number]').textContent = `Page ${page} of ${lastPage}`;
    }
    async function loadPage(next) {
        pageRequest?.abort(); pageRequest = new AbortController();
        const revision = ++pageRevision;
        body.inert = true; $('[data-payroll-scroll]').setAttribute('aria-busy','true'); navigation(true);
        try {
            const params = new URLSearchParams({page:next,per_page:$('[data-page-size]').value,search:$('[data-employee-search]').value,month:$('#month').value,company_id:$('#company_id').value});
            const response = await fetch(`/payroll/employees?${params}`, {headers:{Accept:'application/json'},signal:pageRequest.signal});
            const data = await response.json();
            if (!response.ok) throw Error(errorText(data));
            if (revision !== pageRevision) return;
            body.innerHTML = data.html;
            page = data.page; lastPage = data.last_page;
            $('[data-page-status]').textContent = `${data.from ?? 0}–${data.to ?? 0} of ${data.total} employees`;
            $('[data-payroll-scroll]').scrollTop = 0;
            refresh();
        } catch (error) {
            if (error.name !== 'AbortError' && revision === pageRevision) $('[data-page-status]').textContent = `Could not load employees. ${error.message}`;
        } finally {
            if (revision === pageRevision) {body.inert = false; $('[data-payroll-scroll]').removeAttribute('aria-busy'); navigation();}
        }
    }
    body.addEventListener('change', event => {
        if (!event.target.matches('[name="employee_ids[]"]')) return;
        const id = event.target.value;
        if (event.target.checked) choose([id]);
        else { selected.delete(id); dirty.delete(id); queuePreview(); }
    });
    body.addEventListener('input', event => {
        const input = event.target;
        if (!input.matches('[data-monthly-component]')) return;
        const id = input.closest('[data-employee-row]').dataset.employeeId;
        amounts.set(id,{...(amounts.get(id)||{}),[input.dataset.componentId]:input.value});
        dirty.add(id); queuePreview();
    });
    $('[data-select-page]').addEventListener('click', () => choose(rows().filter(row => {
        const input = row.querySelector('[name="employee_ids[]"]'); return !input.disabled && input.dataset.active === '1';
    }).map(row => row.dataset.employeeId)));
    $('[data-select-employees]').addEventListener('click', async event => {
        const button = event.currentTarget; button.disabled = true; bulkBusy = true;
        bulkRequest?.abort(); bulkRequest = new AbortController();
        try {
            const params = new URLSearchParams({select_all:'1',search:$('[data-employee-search]').value});
            const response = await fetch(`/payroll/employees?${params}`,{headers:{Accept:'application/json'},signal:bulkRequest.signal});
            const data = await response.json();
            if (!response.ok) throw Error(errorText(data));
            choose(data.ids.map(String));
        } catch (error) {if (error.name !== 'AbortError') message(error.message,true);}
        finally {button.disabled = false; bulkBusy = false;}
    });
    $('[data-clear-employees]').addEventListener('click', () => {bulkRequest?.abort();selected.clear();dirty.clear();queuePreview();});
    $('[data-employee-search]').addEventListener('input', () => {clearTimeout(searchTimer);searchTimer=setTimeout(()=>loadPage(1),300);});
    $('[data-page-size]').addEventListener('change',()=>loadPage(1));
    $('[data-previous-page]').addEventListener('click',()=>loadPage(page-1));
    $('[data-next-page]').addEventListener('click',()=>loadPage(page+1));
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        if (bulkBusy) {message('Wait for employee selection to finish before creating payroll.',true);return;}
        if (!selected.size) {message('Select at least one employee.',true);status.scrollIntoView({block:'center'});return;}
        if (!form.reportValidity()) return;
        const data = new FormData(form), payload = {employee_ids:[...selected],component_amounts:selectedAmounts([...selected])};
        ['month','company_id','department_id','currency','salary_due_date','statutory_due_date','tax_due_date','notes','salary_confirmation'].forEach(key=>payload[key]=data.get(key));
        const button = form.querySelector('[type="submit"]'), original = button.textContent;
        submitting = true; button.disabled = true; button.textContent = 'Creating payroll…';
        // JSON avoids PHP max_input_vars truncation with 1,000 employees and monthly component inputs.
        try {
            const response = await fetch('/payroll',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});
            const result = await response.json();
            if (!response.ok) throw Error(errorText(result));
            if (!/^\/payroll\/\d+$/.test(result.redirect)) throw Error('Unexpected response. Check the payroll list before retrying.');
            window.location.assign(result.redirect);
        } catch (error) {
            message(error.message,true); status.scrollIntoView({block:'center'});
            submitting=false;button.disabled=false;button.textContent=original;
        }
    });
    const changeContext = () => { selected.forEach(id => dirty.add(id)); calculated.clear(); queuePreview(); loadPage(page); };
    $('#company_id').addEventListener('change', changeContext);
    $('#month').addEventListener('change', event => {
        if (!/^\d{4}-\d{2}$/.test(event.target.value)) return;
        const [year,month] = event.target.value.split('-').map(Number);
        const format = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
        $('#salary_due_date').value=format(new Date(year,month,0));$('#statutory_due_date').value=format(new Date(year,month+1,0));$('#tax_due_date').value=format(new Date(year,month,15));
        changeContext();
    });
    $('[data-page-size]').value = String(initial.perPage || 25);
    navigation(); queuePreview();
})();
