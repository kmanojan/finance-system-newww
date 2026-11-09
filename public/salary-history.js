/* Employee salary audit views. All values come from immutable saved snapshots. */
document.addEventListener('DOMContentLoaded', () => {
    const historyPanel = document.getElementById('salary-history');
    if (!historyPanel) return;
    const profilePanel = document.getElementById('employee-profile-panel');
    const sheetsPanel = document.getElementById('employee-sheets-panel');
    const primaryTabs = [...document.querySelectorAll('[data-employee-view]')];
    const viewTabs = [...historyPanel.querySelectorAll('[data-salary-view]')];
    let selectedView = 'table';
    function selectTabs(tabs, current, attribute) {
        tabs.forEach(tab => {
            const active = tab.dataset[attribute] === current;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });
    }
    function selectView(name, updateHash = true) {
        selectedView = ['table', 'flow', 'chart'].includes(name) ? name : 'table';
        selectTabs(viewTabs, selectedView, 'salaryView');
        for (const key of ['table', 'flow', 'chart']) document.getElementById(`salary-${key}-panel`).hidden = selectedView !== key;
        const pagination = historyPanel.querySelector('[data-salary-history-pagination]');
        if (pagination) {
            pagination.hidden = selectedView === 'chart';
            pagination.querySelectorAll('a').forEach(link => { const url = new URL(link.href); url.hash = `salary-history-${selectedView}`; link.href = url.href; });
        }
        if (updateHash) history.replaceState(null, '', `#salary-history-${selectedView}`);
    }
    function selectPrimary(name, updateHash = true) {
        const isHistory = name === 'history';
        const isSheets = name === 'sheets' && sheetsPanel;
        historyPanel.hidden = !isHistory;
        profilePanel.hidden = isHistory || isSheets;
        if (sheetsPanel) sheetsPanel.hidden = !isSheets;
        document.querySelectorAll('[data-salary-profile-action]').forEach(button => {button.hidden = isHistory || isSheets;});
        selectTabs(primaryTabs, isHistory ? 'history' : (isSheets ? 'sheets' : 'profile'), 'employeeView');
        if (updateHash) history.replaceState(null, '', isHistory ? `#salary-history-${selectedView}` : (isSheets ? '#salary-sheets' : '#salary-profile'));
    }
    function keyboardTabs(tabs) {
        tabs.forEach((tab, index) => tab.addEventListener('keydown', event => {
            const next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : null;
            if (next === null) return;
            event.preventDefault(); tabs[next].click(); tabs[next].focus();
        }));
    }
    primaryTabs.forEach(tab => tab.addEventListener('click', () => selectPrimary(tab.dataset.employeeView)));
    viewTabs.forEach(tab => tab.addEventListener('click', () => selectView(tab.dataset.salaryView)));
    keyboardTabs(primaryTabs); keyboardTabs(viewTabs);
    function restore() {
        selectView(location.hash.replace('#salary-history-', ''), false);
        selectPrimary(location.hash.startsWith('#salary-history') ? 'history' : (location.hash === '#salary-sheets' ? 'sheets' : 'profile'), false);
    }
    window.addEventListener('hashchange', restore); restore();

    const chart = document.getElementById('salary-history-chart');
    if (!chart) return;
    const records = JSON.parse(document.getElementById('salary-history-data').textContent);
    const points = [{...records[0].before, date:'Previous profile · start date unknown'}, ...records.map(record => ({...record.after, date:record.date}))];
    const metric = document.getElementById('salary-chart-metric');
    const currency = document.getElementById('salary-chart-currency');
    const output = document.getElementById('salary-chart-value');
    [...new Set(points.map(point => point.currency))].forEach(code => {const option = document.createElement('option'); option.value = code; option.textContent = code; currency.append(option);});
    currency.value = points.at(-1).currency;
    const money = value => Number(value).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    function svg(tag, attributes, text) {
        const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
        if (text !== undefined) element.textContent = text;
        chart.append(element); return element;
    }
    function render() {
        chart.replaceChildren();
        const data = points.filter(point => point.currency === currency.value);
        const values = data.map(point => Number(point.totals[metric.value]));
        const low = Math.min(0, ...values), high = Math.max(1, ...values);
        const span = high - low || 1;
        const x = index => data.length === 1 ? 430 : 90 + index * 670 / (data.length - 1);
        const y = value => 230 - (value - low) / span * 200;
        svg('title', {}, `${metric.selectedOptions[0].textContent} history in ${currency.value}`);
        for (let i = 0; i <= 4; i++) {
            const value = low + span * i / 4;
            svg('line', {x1:90, x2:760, y1:y(value), y2:y(value), class:'salary-chart-grid'});
            svg('text', {x:80, y:y(value)+4, 'text-anchor':'end', class:'salary-chart-axis'}, value.toLocaleString('en-US', {notation:'compact', maximumFractionDigits:1}));
        }
        let path = '';
        data.forEach((point, index) => {path += index ? ` H${x(index)} V${y(values[index])}` : `M${x(index)},${y(values[index])}`;});
        svg('path', {d:path, class:'salary-chart-line'});
        data.forEach((point, index) => {
            const description = `${point.date} · ${currency.value} ${money(values[index])}`;
            const dot = svg('circle', {cx:x(index), cy:y(values[index]), r:5, tabindex:0, role:'button', 'aria-label':description, class:'salary-chart-point'});
            const describe = () => {output.textContent = description;};
            dot.addEventListener('focus', describe); dot.addEventListener('pointerenter', describe); dot.addEventListener('click', describe);
            dot.addEventListener('keydown', event => {if (['Enter',' '].includes(event.key)) {event.preventDefault();describe();}});
            if ([0, data.length - 1].includes(index)) svg('text', {x:x(index), y:260, 'text-anchor':index === 0 ? 'start' : 'end', class:'salary-chart-axis'}, point.date.startsWith('Previous') ? 'Previous profile' : point.date.slice(0,11));
        });
        output.textContent = `${data.at(-1).date} · ${currency.value} ${money(values.at(-1))}`;
    }
    metric.addEventListener('change', render); currency.addEventListener('change', render); render();
});
