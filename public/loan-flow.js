(() => {
    const editForm = document.querySelector('[data-loan-edit][data-terms-locked="true"]');
    if (editForm) {
        const editable = new Set(['_token', '_method', 'loan_type', 'party_id', 'lender_name', 'principal_amount', 'maturity_date', 'reminder_days', 'correction_reason', 'purpose', 'guarantor', 'collateral', 'attachments[]']);
        editForm.querySelectorAll('input, select, textarea').forEach(field => {
            const name = field.name || field.closest('.amount-input-wrapper')?.querySelector('.amount-hidden')?.name;
            if (!editable.has(name) && !field.closest('.rich-editor-wrapper, .party-selector-wrapper')) field.disabled = true;
        });
    }
    const helpButton = document.getElementById('loan-flow-help-toggle');
    const helpPanel = document.getElementById('loan-flow-help');
    const setHelpOpen = open => {
        if (!helpButton || !helpPanel) return;
        helpButton.setAttribute('aria-expanded', String(open));
        helpPanel.hidden = !open;
    };
    helpButton?.addEventListener('click', () => setHelpOpen(helpPanel.hidden));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && helpPanel && !helpPanel.hidden) {
            setHelpOpen(false);
            helpButton.focus();
        }
    });
    document.addEventListener('loan:tabchange', () => setHelpOpen(false));
    const canvas = document.querySelector('[data-loan-flow]');
    if (!canvas) return;
    const svg = canvas.querySelector('svg');
    const ns = 'http://www.w3.org/2000/svg';
    const el = (tag, attrs) => {
        const node = document.createElementNS(ns, tag);
        Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
        return node;
    };
    const draw = () => {
        svg.replaceChildren();
        const box = canvas.getBoundingClientRect();
        if (!box.width || !box.height) return;
        svg.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);
        const defs = el('defs', {});
        const marker = el('marker', {id: 'loan-flow-arrow', viewBox: '0 0 10 10', refX: 9, refY: 5, markerWidth: 6, markerHeight: 6, orient: 'auto'});
        marker.append(el('path', {d: 'M 0 0 L 10 5 L 0 10 z', class: 'flow-arrow'}));
        defs.append(marker);
        svg.append(defs);
        const connections = new Map();
        document.querySelectorAll('[data-flow-from]').forEach((edge, index) => {
            const key = `${edge.dataset.flowFrom}:${edge.dataset.flowTo}:${edge.dataset.flowKind}`;
            if (!connections.has(key)) connections.set(key, {edge, numbers: []});
            connections.get(key).numbers.push(index + 1);
        });
        const pairCounts = new Map();
        connections.forEach(({edge}) => {
            const key = `${edge.dataset.flowFrom}:${edge.dataset.flowTo}`;
            pairCounts.set(key, (pairCounts.get(key) || 0) + 1);
        });
        connections.forEach(({edge, numbers}) => {
            const from = canvas.querySelector(`[data-flow-node="${edge.dataset.flowFrom}"]`);
            const to = canvas.querySelector(`[data-flow-node="${edge.dataset.flowTo}"]`);
            if (!from || !to) return;
            const a = from.getBoundingClientRect(), b = to.getBoundingClientRect();
            const mixed = pairCounts.get(`${edge.dataset.flowFrom}:${edge.dataset.flowTo}`) > 1;
            const offset = mixed ? (edge.dataset.flowKind === 'description' ? -18 : 18) : 0;
            const x1 = a.right - box.left, y1 = a.top + a.height / 2 - box.top + offset;
            const x2 = b.left - box.left, y2 = b.top + b.height / 2 - box.top + offset;
            const mid = (x1 + x2) / 2;
            svg.append(el('path', {d: `M ${x1} ${y1} C ${mid} ${y1}, ${mid} ${y2}, ${x2 - 3} ${y2}`, 'marker-end': 'url(#loan-flow-arrow)', class: edge.dataset.flowKind === 'description' ? 'flow-borrowing' : 'flow-repayment'}));
            const text = numbers.join(', ');
            if (numbers.length === 1) {
                svg.append(el('circle', {cx: mid, cy: (y1 + y2) / 2, r: 12}));
            } else {
                const width = text.length * 8 + 16;
                svg.append(el('rect', {x: mid - width / 2, y: (y1 + y2) / 2 - 12, width, height: 24, rx: 12}));
            }
            const label = el('text', {x: mid, y: (y1 + y2) / 2});
            label.textContent = text;
            svg.append(label);
        });
    };
    let scheduled;
    const redraw = () => { cancelAnimationFrame(scheduled); scheduled = requestAnimationFrame(draw); };
    new ResizeObserver(redraw).observe(canvas);
    document.fonts.ready.then(redraw);
    document.addEventListener('loan:tabchange', redraw);
    redraw();
})();
