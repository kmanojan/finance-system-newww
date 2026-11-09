const employeeRegister = document.querySelector('[data-employee-register]');

employeeRegister?.querySelectorAll('[data-profile-toggle]').forEach((toggle) => {
    const breakdown = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!breakdown) return;

    breakdown.hidden = true;
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const expanded = breakdown.hidden;
        breakdown.hidden = !expanded;
        toggle.setAttribute('aria-expanded', String(expanded));
    });
});
