<script>
function salaryCalculator(initialData) {
    return {
        basic: initialData.basic || 0,
        currency: initialData.currency || 'LKR',
        components: initialData.components || [],
        isSubmitting: false,
        saveMessage: '',
        saveError: '',
        totalAllowances: 0,
        grossSalary: 0,
        epfBaseSalary: 0,
        employeeEpf: 0,
        apitTax: 0,
        totalDeductions: 0,
        netSalary: 0,
        employerEpf: 0,
        employerEtf: 0,
        totalEmployerCost: 0,
        ctc: 0,

        init() {
            this.recalculate();
        },

        updateBasicFromInput(event) {
            const wrapper = event.target.closest('.amount-input-wrapper');
            if (wrapper) {
                const hidden = wrapper.querySelector('.amount-hidden');
                if (hidden && hidden.value !== '') {
                    this.basic = parseFloat(hidden.value) || 0;
                    this.recalculate();
                    return;
                }
                const display = wrapper.querySelector('.amount-display-input');
                if (display) {
                    const raw = (display.value || '').toString().replace(/,/g, '');
                    this.basic = parseFloat(raw) || 0;
                    this.recalculate();
                    return;
                }
            }
            const raw = (event.target.value || '').toString().replace(/,/g, '');
            this.basic = parseFloat(raw) || 0;
            this.recalculate();
        },

        calculateSingleComponent(comp) {
            if (!comp.enabled) return 0;
            const val = parseFloat(comp.value) || 0;
            if (comp.calc_type === 'fixed_amount') {
                return val;
            }
            const base = comp.type === 'earning' ? this.basic : ({on_epf_base: this.epfBaseSalary, on_gross: this.grossSalary}[comp.calc_base] ?? this.basic);
            return Math.round((base * (val / 100)) * 100) / 100;
        },

        recalculate() {
            const basicSalary = parseFloat(this.basic) || 0;

            // 1. Calculate Allowances & EPF Base
            let allowances = 0;
            let epfAllowances = 0;
            this.components.forEach(c => {
                if (c.type === 'earning' && c.code !== 'BASIC' && c.enabled) {
                    const amt = this.calculateSingleComponent(c);
                    allowances += amt;
                    if (c.is_epf_eligible) {
                        epfAllowances += amt;
                    }
                }
            });

            this.totalAllowances = allowances;
            this.grossSalary = basicSalary + allowances;
            this.epfBaseSalary = basicSalary + epfAllowances;

            // 2. Calculate Deductions
            let deductions = 0;
            let eeEpf = 0;
            let tax = 0;

            this.components.forEach(c => {
                if (c.type === 'deduction' && c.enabled) {
                    const amt = this.calculateSingleComponent(c);
                    deductions += amt;
                    if (c.code === 'EPF_EE') {
                        eeEpf = amt;
                    } else if (c.code === 'APIT') {
                        tax = amt;
                    }
                }
            });

            this.employeeEpf = eeEpf;
            this.apitTax = tax;
            this.totalDeductions = deductions;
            this.netSalary = Math.max(0, this.grossSalary - deductions);

            // 3. Calculate Employer Contributions
            let erEpf = 0;
            let erEtf = 0;
            let employerTotal = 0;
            this.components.forEach(c => {
                if (c.type === 'employer_contribution' && c.enabled) {
                    const amt = this.calculateSingleComponent(c);
                    employerTotal += amt;
                    if (c.code === 'EPF_ER') erEpf = amt;
                    if (c.code === 'ETF_ER') erEtf = amt;
                }
            });

            this.employerEpf = erEpf;
            this.employerEtf = erEtf;
            this.totalEmployerCost = employerTotal;
            this.ctc = this.grossSalary + this.totalEmployerCost;
        },

        formatNumber(num) {
            return (parseFloat(num) || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        async submitForm() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;
            this.saveMessage = '';
            this.saveError = '';

            const form = document.getElementById('salaryConfigForm');
            if (form && form.reportValidity && !form.reportValidity()) {
                this.isSubmitting = false;
                return;
            }

            const basicInput = form ? form.querySelector('input[name="basic_salary"]') : null;
            const basicVal = basicInput ? (parseFloat(basicInput.value) || parseFloat(this.basic) || 0) : (parseFloat(this.basic) || 0);

            const payload = {
                basic_salary: basicVal,
                salary_change_reason: form?.querySelector('[name="salary_change_reason"]')?.value || null,
                currency: form ? (form.querySelector('select[name="currency"]')?.value || 'LKR') : 'LKR',
                payment_mode: form ? (form.querySelector('select[name="payment_mode"]')?.value || 'bank_transfer') : 'bank_transfer',
                bank_name: form ? (form.querySelector('input[name="bank_name"]')?.value || null) : null,
                bank_account_no: form ? (form.querySelector('input[name="bank_account_no"]')?.value || null) : null,
                bank_branch: form ? (form.querySelector('input[name="bank_branch"]')?.value || null) : null,
                epf_number: form ? (form.querySelector('input[name="epf_number"]')?.value || null) : null,
                components: this.components.map(c => ({
                    id: c.id,
                    value: parseFloat(c.value) || 0,
                    enabled: c.enabled ? 1 : 0
                }))
            };

            try {
                const res = await fetch('/employees/{{ $employee->id }}/salary', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.saveMessage = data.message || 'Salary profile updated successfully!';
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    this.saveError = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to save salary profile.');
                }
            } catch (err) {
                console.error(err);
                this.saveError = 'Network error while saving salary profile.';
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}

window.salaryCalculator = salaryCalculator;

window.initialSalaryData = {
    basic: {{ (float) ($employee->basic_salary ?? 0) }},
    currency: @json($employee->currency ?? 'LKR'),
    components: @json($componentsData)
};

document.addEventListener('alpine:init', () => {
    if (window.Alpine) {
        Alpine.data('salaryCalculator', salaryCalculator);
    }
});
</script>
