const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// A small event/element fixture exercises the real batch script without browser dependencies.
class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.events = {}; this.attributes = {}; this.value = ''; this.disabled = false; this.textContent = ''; }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
    addEventListener(type, callback) { this.events[type] = callback; }
    setAttribute(name, value) { this.attributes[name] = value; }
    focus() {}
    fire(type) { const event = { prevented: false, preventDefault() { this.prevented = true; } }; event.pending = this.events[type]?.(event); return event; }
}
function fixture(count = 1000, response = { ok: true, json: async () => ({ redirect: '/payroll/1/salary-batches/9' }) }) {
    const selectors = ['entries', 'rows', 'search', 'prev', 'next', 'save', 'message', 'count', 'total', 'selected-total', 'after', 'range', 'fill', 'clear'];
    const elements = Object.fromEntries(selectors.map(key => [key, new Element()]));
    elements.entries.value = '[]';
    const form = new Element('form');
    form.querySelector = selector => elements[selector.match(/data-batch-(.+)\]/)[1]];
    const employees = Array.from({ length: count }, (_, index) => ({ id: index + 1, name: `Payroll employee ${index + 1}`, code: `EMP-${index + 1}`, net: 3000000, paid: 0, balance: 3000000 }));
    const document = { querySelector: () => form, getElementById: () => ({ textContent: JSON.stringify(employees) }), createElement: tag => new Element(tag) };
    const windowEvents = {};
    let reloads = 0;
    const requests = [], navigations = [];
    class FormData { constructor() { this.entries_json = elements.entries.value; } }
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/payroll-batch.js'), 'utf8'), { document, FormData, fetch: async (url, options) => { requests.push(options); return response; }, window: { addEventListener(type, callback) { windowEvents[type] = callback; }, location: { reload() { reloads++; }, assign(url) { navigations.push(url); } } } });
    const input = () => elements.rows.children[0].children[4].children[0];
    const setAmount = value => { input().value = value; input().fire('input'); };
    return { elements, form, input, setAmount, windowEvents, requests, navigations, reloads: () => reloads };
}

test('returning through the back-forward cache refreshes employee balances', () => {
    const f = fixture(3);
    f.windowEvents.pageshow({ persisted: false });
    assert.equal(f.reloads(), 0);
    f.windowEvents.pageshow({ persisted: true });
    assert.equal(f.reloads(), 1);
});

test('employee amounts survive pagination and search across 1,000 employees', () => {
    const f = fixture();
    assert.equal(f.elements.rows.children.length, 50);
    assert.equal(f.elements.save.disabled, true);
    f.setAmount('20000.00');
    f.elements.next.fire('click');
    assert.equal(f.elements.rows.children.length, 50);
    f.setAmount('10000.00');
    f.elements.prev.fire('click');
    assert.equal(f.input().value, '20000.00');
    f.elements.search.value = 'EMP-1000'; f.elements.search.fire('input');
    assert.equal(f.elements.rows.children.length, 1);
    f.setAmount('0.01');
    assert.equal(f.elements.count.textContent, '3 employees selected');
    assert.equal(f.elements.total.textContent, 'LKR 30,000.01');
    assert.equal(f.elements['selected-total'].textContent, '30,000.01');
    assert.equal(f.elements.after.textContent, '29,969,999.99');
    const event = f.form.fire('submit');
    assert.equal(event.prevented, true); // AJAX owns submission and displays errors in place.
    assert.deepEqual(JSON.parse(f.elements.entries.value), [{ item_id: 1, amount: '20000.00' }, { item_id: 51, amount: '10000.00' }, { item_id: 1000, amount: '0.01' }]);
});

test('overpayments and invalid precision block submission even after changing pages', () => {
    const f = fixture();
    f.setAmount('30000.01');
    assert.equal(f.input().attributes['aria-invalid'], 'true');
    f.elements.next.fire('click');
    f.setAmount('100.00');
    assert.equal(f.elements.save.disabled, true);
    assert.equal(f.form.fire('submit').prevented, true);
    f.elements.prev.fire('click');
    f.setAmount('100.001');
    assert.equal(f.elements.save.disabled, true);
    f.setAmount('100.00');
    assert.equal(f.elements.save.disabled, false);
    f.elements.clear.fire('click');
    assert.equal(f.elements.count.textContent, '0 employees selected');
    assert.equal(f.elements.save.disabled, true);
});

test('fill balances applies only to the visible page and zero amounts are skipped', () => {
    const f = fixture();
    f.elements.fill.fire('click');
    assert.equal(f.elements.count.textContent, '50 employees selected');
    f.setAmount('0');
    f.form.fire('submit');
    const entries = JSON.parse(f.elements.entries.value);
    assert.equal(entries.length, 49);
    assert.equal(entries.some(entry => entry.item_id === 1 || entry.item_id > 50), false);
});

test('successful submission sends all allocations and prevents duplicate clicks', async () => {
    const f = fixture(56);
    f.elements.fill.fire('click');
    f.elements.next.fire('click');
    f.elements.fill.fire('click');
    const first = f.form.fire('submit');
    f.form.fire('submit');
    assert.equal(f.elements.save.disabled, true);
    assert.equal(f.requests.length, 1);
    assert.equal(JSON.parse(f.requests[0].body.entries_json).length, 56);
    await first.pending;
    assert.deepEqual(f.navigations, ['/payroll/1/salary-batches/9']);
});

test('server validation is visible and amounts remain available for correction', async () => {
    const f = fixture(3, { ok: false, status: 422, json: async () => ({ errors: { payroll: ['Salary payments cannot predate the payroll month.'] } }) });
    f.setAmount('20000.00');
    await f.form.fire('submit').pending;
    assert.equal(f.elements.message.textContent, 'Salary payments cannot predate the payroll month.');
    assert.equal(f.input().value, '20000.00');
    assert.equal(f.elements.save.disabled, false);
    assert.equal(f.navigations.length, 0);
});

test('expired sessions show a recoverable error instead of silently navigating', async () => {
    const f = fixture(1, { ok: false, status: 419, json: async () => ({}) });
    f.setAmount('100.00');
    await f.form.fire('submit').pending;
    assert.match(f.elements.message.textContent, /session expired/);
    assert.equal(f.elements.save.disabled, false);
});

test('bank options are not mistaken for company selectors', () => {
    const options = [1, 2, 3].map(id => ({ value: String(id), dataset: { company: '1' }, disabled: false, hidden: false, selected: id === 1, addEventListener() {}, closest: () => form }));
    const bank = { options, value: '1' };
    const form = { querySelectorAll: () => [bank] };
    const document = { querySelectorAll: selector => selector === '[data-company]' ? options : [], getElementById: () => null };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/payroll.js'), 'utf8'), { document });
    assert.equal(bank.value, '1');
    assert.ok(options.every(option => !option.hidden && !option.disabled));
});

test('checkbox selection fills balances, clears amounts and updates remaining preview', () => {
    const f = fixture(2);
    const checkbox = f.elements.rows.children[0].children[0].children[0].children[0].children[0];
    assert.equal(f.elements.after.textContent, '60,000.00');
    checkbox.checked = true; checkbox.fire('change');
    assert.equal(f.input().value, '30000.00');
    assert.equal(f.elements['selected-total'].textContent, '30,000.00');
    assert.equal(f.elements.after.textContent, '30,000.00');
    f.setAmount('10000');
    assert.equal(checkbox.checked, true);
    assert.equal(f.elements.after.textContent, '50,000.00');
    f.setAmount('30000.01');
    assert.equal(f.elements.after.textContent, '—');
    assert.equal(f.elements.total.textContent, 'Check amounts');
    checkbox.checked = false; checkbox.fire('change');
    assert.equal(f.input().value, '');
    assert.equal(f.elements.after.textContent, '60,000.00');
    assert.equal(f.elements.save.disabled, true);
});
