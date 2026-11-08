const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.events = {}; this.attributes = {}; this.value = ''; this.checked = false; this.disabled = false; this.textContent = ''; }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
    addEventListener(type, callback) { this.events[type] = callback; }
    setAttribute(name, value) { this.attributes[name] = value; }
    fire(type, extra = {}) { const event = { prevented: false, preventDefault() { this.prevented = true; }, ...extra }; this.events[type]?.(event); return event; }
}
function fixture(count = 1000) {
    const elements = Object.fromEntries(['search','filter','rows','page','prev','next','save','reset','count','message','clear','all','range','selection','action'].map(key => [key, new Element()]));
    elements.filter.value = 'all';
    const form = new Element('form');
    form.querySelector = selector => elements[selector.match(/data-review-(.+)\]/)[1]];
    const employees = Array.from({ length: count }, (_, index) => ({ id: index + 1, name: `Employee ${index + 1}`, code: `EMP-${index + 1}`, gross: 3000000, net: 2500000, reviewed: index % 2 === 0, version: `version-${index + 1}` }));
    const document = { querySelector: () => form, getElementById: () => ({ textContent: JSON.stringify({ runId: 4, employees }) }), createElement: tag => new Element(tag) };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/payroll-review.js'), 'utf8'), { document });
    const checkbox = (index = 0) => elements.rows.children[index].children[0].children[0].children[0];
    return { elements, form, checkbox };
}

test('review selection survives pagination and search across 1,000 employees', () => {
    const f = fixture();
    assert.equal(f.elements.rows.children.length, 50);
    assert.equal(f.elements.save.disabled, true);
    f.checkbox().checked = true; f.checkbox().fire('change');
    assert.equal(f.elements.page.indeterminate, true);
    f.elements.next.fire('click'); f.checkbox().checked = true; f.checkbox().fire('change');
    f.elements.prev.fire('click'); assert.equal(f.checkbox().checked, true);
    f.elements.search.value = 'EMP-1000'; f.elements.search.fire('input');
    assert.equal(f.elements.rows.children.length, 1);
    assert.match(f.elements.message.textContent, /2 selected outside/);
    f.checkbox().checked = true; f.checkbox().fire('change');
    assert.equal(f.elements.count.textContent, '3 employees selected');
    assert.equal(f.form.fire('submit').prevented, false);
    assert.deepEqual(JSON.parse(f.elements.selection.value), [{ item_id: 1, version: 'version-1' }, { item_id: 51, version: 'version-51' }, { item_id: 1000, version: 'version-1000' }]);
    assert.equal(f.elements.action.value, '1');
    f.form.fire('submit', { submitter: { dataset: { reviewValue: '0' } } });
    assert.equal(f.elements.action.value, '0');
});

test('select page and all matching employees have distinct scopes and can be cleared', () => {
    const f = fixture();
    f.elements.page.checked = true; f.elements.page.fire('change');
    assert.equal(f.elements.count.textContent, '50 employees selected');
    f.elements.all.fire('click'); assert.equal(f.elements.count.textContent, '1000 employees selected');
    f.elements.clear.fire('click'); assert.equal(f.elements.count.textContent, '0 employees selected');
    f.elements.filter.value = 'needs_review'; f.elements.filter.fire('change');
    assert.equal(f.elements.all.textContent, 'Select all 500 matching employees');
    f.elements.all.fire('click'); f.form.fire('submit');
    const entries = JSON.parse(f.elements.selection.value);
    assert.equal(entries.length, 500); assert.equal(entries.every(entry => entry.item_id % 2 === 0), true);
    f.elements.page.checked = false; f.elements.page.fire('change');
    assert.equal(f.elements.count.textContent, '450 employees selected');
});

test('empty searches and selections never submit unintended employees', () => {
    const f = fixture();
    assert.equal(f.form.fire('submit').prevented, true);
    f.elements.search.value = 'missing employee'; f.elements.search.fire('input');
    assert.equal(f.elements.all.disabled, true); assert.equal(f.elements.page.disabled, true);
    assert.equal(f.elements.range.textContent, '0 employees');
    f.elements.search.value = '1000 employee'; f.elements.search.fire('input');
    assert.equal(f.elements.rows.children.length, 1);
    assert.equal(f.elements.rows.children[0].children[1].children[0].textContent, 'Employee 1000');
    assert.equal(f.elements.rows.children[0].children[1].children[0].href, '/payroll/4/employees/1000');
    assert.equal(f.elements.search.fire('keydown', { key: 'Enter' }).prevented, true);
});
