function syncLoanExpandAll() {
    const toggles = [...document.querySelectorAll('.li-toggle')];
    const expanded = toggles.length > 0 && toggles.every(button => button.getAttribute('aria-expanded') === 'true');
    document.getElementById('btnToggleAll')?.setAttribute('aria-expanded', String(expanded));
    const text = document.getElementById('toggleAllText');
    if (text) text.textContent = expanded ? 'Collapse details' : 'Expand details';
}
function setLoanExpanded(item, expanded) {
    item.classList.toggle('expanded', expanded);
    item.querySelector('.li-toggle').setAttribute('aria-expanded', String(expanded));
    item.querySelector('.li-drawer').hidden = !expanded;
}
function toggleLoanAccordion(id) {
    const item = document.getElementById('loan-item-' + id);
    if (item) setLoanExpanded(item, !item.classList.contains('expanded'));
    syncLoanExpandAll();
}
function toggleAllAccordions() {
    const expand = document.getElementById('btnToggleAll')?.getAttribute('aria-expanded') !== 'true';
    document.querySelectorAll('.li-loan').forEach(item => setLoanExpanded(item, expand));
    syncLoanExpandAll();
}
(() => {
    const filters = document.querySelector('.li-more-filters');
    if (!filters) return;
    const mobile = matchMedia('(max-width: 700px)');
    const hasFilters = [...filters.querySelectorAll('input')].some(input => input.value);
    const sync = () => { filters.open = !mobile.matches || hasFilters; };
    sync();
    mobile.addEventListener('change', sync);
})();
