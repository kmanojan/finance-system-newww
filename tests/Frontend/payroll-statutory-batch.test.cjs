const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
class Element {
    constructor() { this.events = {}; this.attributes = {}; this.value = ''; this.checked = false; this.disabled = false; this.textContent = ''; }
    addEventListener(name, fn) { this.events[name] = fn; }
    setAttribute(name, value) { this.attributes[name] = value; }
    removeAttribute(name) { delete this.attributes[name]; }
    focus() {}
    fire(name) { const e = {target: this, prevented: false, preventDefault() { this.prevented = true; }}; e.pending = this.events[name]?.(e); return e; }
}
function fixture(response = {ok: true, json: async () => ({redirect: '/payroll/statutory/batches/1'})}) {
    const elements = Object.fromEntries(['all', 'save', 'message', 'total', 'paid_on', 'entries_json'].map(key => [key, new Element()]));
    const rows = [1, 2].map(id => {
        const row = new Element(); row.dataset = {run: String(id), balance: '600000', earliest: id === 1 ? '2026-01-31' : '2026-02-28'};
        row.select = new Element(); row.amount = new Element(); row.amount.disabled = true;
        row.querySelector = selector => selector.includes('select') ? row.select : row.amount;
        return row;
    });
    const form = new Element(), scope = new Element();
    form.querySelectorAll = () => rows;
    form.querySelector = selector => elements[selector.match(/data-stat-(\w+)/)?.[1] || selector.match(/name="(\w+)"/)[1]];
    const document = {querySelector: selector => selector === '[data-statutory-batch]' ? form : scope};
    const requests = [], navigations = [];
    class FormData { constructor() { this.entries_json = elements.entries_json.value; } }
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/payroll-statutory-batch.js'), 'utf8'), {document, FormData, fetch: async (url, options) => {requests.push(options); return response;}, window: {confirm: () => false, addEventListener() {}, location: {assign(url) {navigations.push(url);}}}});
    return {rows, elements, form, scope, requests, navigations};
}
test('selection fills balances, totals partial payments and updates latest allowed month', async () => {
    const f = fixture();
    f.elements.all.checked = true; f.elements.all.fire('change');
    assert.equal(f.rows[0].amount.value, '6000.00');
    assert.equal(f.elements.paid_on.min, '2026-02-28');
    f.rows[1].amount.value = '2000.00'; f.rows[1].amount.fire('input');
    assert.equal(f.elements.total.textContent, '2 months · LKR 8,000.00');
    assert.equal(f.scope.fire('submit').prevented, true);
    f.rows[1].select.checked = false; f.rows[1].select.fire('change');
    assert.equal(f.elements.paid_on.min, '2026-01-31');
    assert.equal(f.elements.all.indeterminate, true);
    await f.form.fire('submit').pending;
    assert.deepEqual(JSON.parse(f.requests[0].body.entries_json), [{run_id: 1, amount: '6000.00'}]);
});
test('invalid partial amounts block save and unselected amounts do not count', () => {
    const f = fixture(); f.rows[0].select.checked = true; f.rows[0].select.fire('change');
    for (const value of ['6000.01', '1.001', '-1', '0', '']) {
        f.rows[0].amount.value = value; f.rows[0].amount.fire('input');
        assert.equal(f.elements.save.disabled, true);
        f.form.fire('submit'); assert.equal(f.requests.length, 0);
    }
    f.rows[0].select.checked = false; f.rows[0].select.fire('change');
    assert.equal(f.rows[0].amount.disabled, true);
    assert.equal(f.elements.total.textContent, '0 months · LKR 0.00');
});
test('one batch is sent on double-click and redirects on success', async () => {
    const f = fixture(); f.elements.all.checked = true; f.elements.all.fire('change');
    const first = f.form.fire('submit'); f.form.fire('submit');
    assert.equal(f.requests.length, 1);
    await first.pending; assert.deepEqual(f.navigations, ['/payroll/statutory/batches/1']);
});
test('validation errors retain selection and partial amounts for correction', async () => {
    const f = fixture({ok: false, status: 422, json: async () => ({errors: {payroll: ['Payment exceeds the selected outstanding balance.']}})});
    f.rows[0].select.checked = true; f.rows[0].select.fire('change'); f.rows[0].amount.value = '2000';
    await f.form.fire('submit').pending;
    assert.match(f.elements.message.textContent, /exceeds/);
    assert.equal(f.rows[0].amount.value, '2000'); assert.equal(f.rows[0].select.checked, true);
    assert.equal(f.elements.save.disabled, false); assert.equal(f.navigations.length, 0);
});
