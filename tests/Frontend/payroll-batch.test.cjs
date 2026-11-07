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
    fire(type) { const event = { prevented: false, preventDefault() { this.prevented = true; } }; this.events[type]?.(event); return event; }
}
function fixture(count = 1000) {
    const selectors = ['entries', 'rows', 'search', 'prev', 'next', 'save', 'message', 'count', 'total', 'range', 'fill', 'clear'];
    const elements = Object.fromEntries(selectors.map(key => [key, new Element()]));
    elements.entries.value = '[]';
    const form = new Element('form');
    form.querySelector = selector => elements[selector.match(/data-batch-(.+)\]/)[1]];
    const employees = Array.from({ length: count }, (_, index) => ({ id: index + 1, name: `Payroll employee ${index + 1}`, code: `EMP-${index + 1}`, net: 3000000, paid: 0, balance: 3000000 }));
    const document = { querySelector: () => form, getElementById: () => ({ textContent: JSON.stringify(employees) }), createElement: tag => new Element(tag) };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/payroll-batch.js'), 'utf8'), { document, window: { addEventListener() {} } });
    const input = () => elements.rows.children[0].children[4].children[0];
    const setAmount = value => { input().value = value; input().fire('input'); };
    return { elements, form, input, setAmount };
}

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
    const event = f.form.fire('submit');
    assert.equal(event.prevented, false);
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
