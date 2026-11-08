(() => {
    const root = document.querySelector('[data-historical-advance-import]');
    if (!root) return;
    const { months } = JSON.parse(document.getElementById('historical-advance-data').textContent);
    const bank = root.querySelector('[data-history-bank]'), start = root.querySelector('[data-history-start]');
    const status = root.querySelector('[data-history-status]'), progress = root.querySelector('[data-history-progress]');
    const buttons = [...root.querySelectorAll('[data-history-run]')];
    const csrf = root.querySelector('input[name="_token"]').value;
    let busy = false;
    const controls = () => {
        bank.disabled = busy;
        if (start) start.disabled = busy || months.every(month => month.linked === month.count) || bank.options.length < 2;
        buttons.forEach(button => { button.disabled = busy || months.find(month => month.id === Number(button.dataset.historyRun)).linked === months.find(month => month.id === Number(button.dataset.historyRun)).count; });
        document.querySelector('[data-history-recorded]').textContent = `${months.reduce((sum,month)=>sum+month.linked,0)} of ${months.reduce((sum,month)=>sum+month.count,0)}`;
    };
    const record = async queue => {
        if (busy) return;
        if (!bank.value) { status.textContent = 'Select the bank account used for these transfers.'; bank.focus(); return; }
        const bankId = Number(bank.value);
        busy = true; controls(); progress.max = Math.max(1, queue.length); progress.value = 0;
        let created = 0, verified = 0;
        try {
            for (const month of queue) {
                status.textContent = `Recording payroll #${month.id}. ${progress.value} of ${queue.length} months complete…`;
                const response = await fetch(`/payroll/${month.id}/historical-advances`, { method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ bank_account_id: bankId, version: month.version }) });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to record this month.');
                created += data.created; verified += data.existing; month.linked = month.count; progress.value++;
                root.querySelector(`[data-history-month-status="${month.id}"]`).textContent = `${month.count} of ${month.count} recorded`;
                controls();
            }
            status.textContent = `Completed. ${created} advances recorded; ${verified} existing advances verified. Refresh to see payment references in the detail table.`;
        } catch (error) {
            status.textContent = `${error.message} Completed months are saved. Refresh and retry safely; matching records are not duplicated.`;
        } finally { busy = false; controls(); }
    };
    if (start) start.addEventListener('click', () => record(months.filter(month => month.linked < month.count)));
    buttons.forEach(button => button.addEventListener('click', () => record([months.find(month => month.id === Number(button.dataset.historyRun))])));
    controls();
})();
