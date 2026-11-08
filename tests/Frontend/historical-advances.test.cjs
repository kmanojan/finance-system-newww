const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

class Element {
    constructor() { this.value = ''; this.disabled = false; this.textContent = ''; this.events = {}; this.dataset = {}; }
    addEventListener(type, fn) { this.events[type] = fn; }
    focus() { this.focused = true; }
    fire(type) { return this.events[type]?.(); }
}
function fixture({ failSecondOnce = false } = {}) {
    const months = [{ id: 1, version: 'first-version', count: 2, linked: 0 }, { id: 2, version: 'second-version', count: 1, linked: 0 }];
    const bank = new Element(); bank.options = [{}, {}];
    const start = new Element(), status = new Element(), progress = new Element(), recorded = new Element();
    progress.value = 0;
    const buttons = months.map(month => { const el = new Element(); el.dataset.historyRun = String(month.id); return el; });
    const states = months.map(() => new Element());
    const map = { bank, start, status, progress };
    const root = { querySelector(selector) {
        const match = selector.match(/data-history-month-status="(\d+)"/);
        return match ? states[Number(match[1]) - 1] : map[selector.match(/data-history-(.+)\]/)[1]];
    }, querySelectorAll() { return buttons; } };
    const requests = []; let failed = false;
    const fetch = async (url, options) => {
        requests.push({ url, options });
        if (failSecondOnce && url.includes('/2/') && !failed) { failed = true; return { ok: false, json: async () => ({ errors: { source: ['Source changed.'] } }) }; }
        return { ok: true, json: async () => ({ created: url.includes('/1/') ? 2 : 1, existing: 0, total: 10000 }) };
    };
    const document = { querySelector(selector) { return selector.includes('csrf-token') ? { content: 'csrf-test' } : selector.includes('recorded') ? recorded : root; }, getElementById() { return { textContent: JSON.stringify({ months }) }; } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/payroll-historical-advances.js'), 'utf8'), { document, fetch });
    return { bank, start, status, progress, recorded, buttons, requests, states };
}

test('historical advance batches require a bank and process each month sequentially', async () => {
    const f = fixture();
    await f.start.fire('click'); assert.equal(f.requests.length, 0); assert.equal(f.bank.focused, true);
    f.bank.value = '3'; await f.start.fire('click');
    assert.equal(f.requests.length, 2); assert.equal(f.progress.value, 2);
    assert.equal(f.recorded.textContent, '3 of 3'); assert.equal(f.start.disabled, true);
    assert.deepEqual(JSON.parse(f.requests[0].options.body), { bank_account_id: 3, version: 'first-version' });
    assert.equal(f.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.match(f.status.textContent, /3 advances recorded/);
});

test('a failed month preserves completed months and retries only the remaining work', async () => {
    const f = fixture({ failSecondOnce: true }); f.bank.value = '3';
    await f.start.fire('click');
    assert.equal(f.recorded.textContent, '2 of 3'); assert.match(f.status.textContent, /Source changed/);
    assert.equal(f.start.disabled, false); assert.equal(f.bank.disabled, false);
    await f.start.fire('click');
    assert.equal(f.requests.length, 3); assert.match(f.requests[2].url, /\/2\/historical-advances/);
    assert.equal(f.recorded.textContent, '3 of 3'); assert.equal(f.start.disabled, true);
});
