(() => {
    const form = document.getElementById('dashboard-filters');
    if (!form) return;
    const range = form.querySelector('#date_range');
    const company = form.querySelector('#company_id');
    const department = form.querySelector('#department_id');
    const syncDates = () => form.querySelectorAll('.dash-custom-date').forEach(field => {
        field.hidden = range.value !== 'custom';
        field.querySelector('input').disabled = field.hidden;
        field.querySelector('input').required = !field.hidden;
    });
    const syncDepartments = () => {
        [...department.options].forEach(option => {
            const excluded = !!(company.value && option.value && option.dataset.company !== company.value);
            option.hidden = excluded;
            option.disabled = excluded;
        });
        if (department.selectedOptions[0]?.disabled) department.value = '';
    };
    syncDates(); syncDepartments();
    range.addEventListener('change', syncDates);
    company.addEventListener('change', syncDepartments);
    const disclosure = document.querySelector('.dash-filters');
    const narrow = matchMedia('(max-width: 700px)');
    const syncDisclosure = () => { if (!narrow.matches) disclosure.open = true; };
    syncDisclosure(); narrow.addEventListener('change', syncDisclosure);

    const canvas = document.getElementById('dashboard-cash-chart');
    if (!canvas) return;
    const showFallback = () => {
        document.getElementById('dashboard-chart-region').hidden = true;
        document.getElementById('dashboard-chart-fallback').hidden = false;
        document.querySelector('.dash-chart-controls').hidden = true;
        document.querySelector('.dash-chart-data').open = true;
    };
    if (typeof Chart === 'undefined') { showFallback(); return; }
    const data = JSON.parse(document.getElementById('dashboard-chart-data').textContent);
    const colors = () => {
        const css = getComputedStyle(document.documentElement);
        return {text:css.getPropertyValue('--text-muted').trim(),line:css.getPropertyValue('--border-light').trim(),income:css.getPropertyValue('--primary').trim(),expense:css.getPropertyValue('--text-light').trim()};
    };
    const money = value => `${data.currency} ${new Intl.NumberFormat('en-LK', {minimumFractionDigits:2,maximumFractionDigits:2}).format(value)}`;
    const c = colors();
    let chart;
    try {
        chart = new Chart(canvas, {
            type:'bar',
            data:{labels:data.labels,datasets:[{label:'Received',data:data.income,backgroundColor:c.income,borderRadius:2,maxBarThickness:24},{label:'Spent',data:data.expense,backgroundColor:c.expense,borderRadius:2,maxBarThickness:24}]},
            options:{responsive:true,maintainAspectRatio:false,animation:matchMedia('(prefers-reduced-motion: reduce)').matches ? false : {duration:180},interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{callbacks:{label:context=>`${context.dataset.label}: ${money(context.parsed.y)}`}}},scales:{x:{border:{display:false},grid:{display:false},ticks:{color:c.text,maxRotation:0,maxTicksLimit:6,font:{family:'Lexend Deca',size:11}}},y:{beginAtZero:true,border:{display:false},grid:{color:c.line},ticks:{color:c.text,maxTicksLimit:5,callback:value=>new Intl.NumberFormat('en',{notation:'compact',maximumFractionDigits:1}).format(value),font:{family:'Lexend Deca',size:11}}}}}
        });
    } catch (_) { showFallback(); return; }
    document.querySelectorAll('[data-cash-series]').forEach(button => button.addEventListener('click', () => {
        const series = button.dataset.cashSeries;
        chart.setDatasetVisibility(0, series !== 'expense');
        chart.setDatasetVisibility(1, series !== 'income');
        document.querySelectorAll('[data-cash-series]').forEach(control => control.setAttribute('aria-pressed', String(control === button)));
        canvas.setAttribute('aria-label', `Cash ${series === 'both' ? 'received and spent' : series === 'income' ? 'received' : 'spent'} in ${data.currency}. Exact values are available in the data table below.`);
        chart.update();
    }));
    new MutationObserver(() => {
        const c = colors();
        chart.data.datasets[0].backgroundColor = c.income;
        chart.data.datasets[1].backgroundColor = c.expense;
        chart.options.scales.x.ticks.color = c.text;
        chart.options.scales.y.ticks.color = c.text;
        chart.options.scales.y.grid.color = c.line;
        chart.update('none');
    }).observe(document.documentElement, {attributes:true,attributeFilter:['data-theme']});
})();
