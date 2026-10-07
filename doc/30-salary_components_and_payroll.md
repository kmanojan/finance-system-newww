# 30. Salary Components Master & Employee Compensation

## Overview
This module provides full employee compensation management, including **Salary Components Master** (heads/allowances, statutory employee deductions, and employer contributions), individual **Employee Salary Configuration**, and live net pay / CTC calculation with extensive detailing across the **Employees Index** and **Employee Single View**.

---

## 1. Salary Components Master (`/master/salary-components`)

Configured under the **Master Data** module. Components determine how payroll earnings, deductions, and liabilities are calculated.

### Component Properties & Rules
- **Component Types (`type`)**:
  - `earning` *(Plus / Allowance)*: Adds to Gross Earnings (e.g. Basic Salary, Fixed Allowance, Travel Allowance, Performance Bonus).
  - `deduction` *(Minus / Employee Deduction)*: Deducted from Gross Salary to compute Net Pay (e.g. Employee EPF 8%, APIT Tax, Loan Recovery).
  - `employer_contribution` *(Company Cost / Liability)*: Paid directly by the company (does not reduce employee take-home pay, e.g. Employer EPF 12%, Employer ETF 3%).
- **Calculation Type (`calculation_type`)**:
  - `fixed_amount`: Direct flat amount in currency (e.g., LKR 15,000).
  - `percentage`: Percentage (%) calculated off a designated base.
- **Calculation Base (`calculation_base`)**:
  - `on_basic`: Applies to Basic Salary.
  - `on_epf_base`: Applies to (Basic Salary + EPF-eligible allowances). Standard for Sri Lankan statutory EPF/ETF.
  - `on_gross`: Applies to Total Gross Earnings.
  - `custom`: Dynamic or fixed deduction (e.g. APIT withholding).
- **Statutory & Tax Flags**:
  - `is_epf_eligible`: Whether an earning is included in the EPF/ETF contribution calculation base.
  - `is_taxable`: Whether the allowance contributes to APIT taxable personal income.
  - `is_statutory`: Statutory items protected from accidental deletion.

---

## 2. Seeded Sri Lanka Baseline Components

| Code | Component Name | Type | Calculation Method | Base | Default Value | EPF Eligible | Taxable |
|---|---|---|---|---|---|---|---|
| `BASIC` | Basic Salary | Earning | Fixed Amount | Base | 0.00 | Yes | Yes |
| `FIXED_ALLOW` | Budgetary / Fixed Allowance | Earning | Fixed Amount | Base | 0.00 | Yes | Yes |
| `TRAVEL_ALLOW` | Transport / Travel Allowance | Earning | Fixed Amount | Base | 0.00 | No | Yes |
| `PERF_ALLOW` | Performance / Attendance Allowance | Earning | Fixed Amount | Base | 0.00 | No | Yes |
| `EPF_EE` | Employee EPF (8%) | Deduction | Percentage | On EPF Base | 8.00% | No | No |
| `APIT` | Advance Personal Income Tax (APIT) | Deduction | Fixed Amount | Custom | 0.00 | No | No |
| `SALARY_ADVANCE` | Salary Advance / Loan Recovery | Deduction | Fixed Amount | Custom | 0.00 | No | No |
| `EPF_ER` | Employer EPF (12%) | Employer Cost | Percentage | On EPF Base | 12.00% | No | No |
| `ETF_ER` | Employer ETF (3%) | Employer Cost | Percentage | On EPF Base | 3.00% | No | No |

---

## 3. Employee Single View (`/employees/{id}`)

Accessible by clicking an employee name or the **"Config"** action from `/employees`:
1. **Basic Compensation**:
   - Contracted Basic Salary (`basic_salary`).
   - Currency (`currency` via dropdown).
   - Payment Mode (`bank_transfer`, `cash`, `cheque`).
   - Bank Details (Bank Name, Branch, Account No).
   - EPF/ETF Member Number (`epf_number`).
2. **Component Mapping Table & Role-Specific Allowances**:
   - **Role-Specific Assignment**: Different roles receive different packages (e.g., field staff get Petrol Allowance, desk staff get Telephone Allowance).
   - **"+ Assign Allowance..." Dropdown**: Quickly attach any role allowance (e.g., Petrol, Telephone, Meal, Site Visit) to the employee.
   - **Assigned vs. All Masters View**: Filter to view only the employee's active allowances or toggle to inspect all master components.
   - **Remove Action $(\times)$**: Detach or inactivate an allowance from an employee with one click.
   - **Live Row-Level Effect**: Real-time effect indicator (`+ 15,000.00`, `- 11,200.00`, etc.).
3. **Interactive Real-Time Net Pay & CTC Calculator**:
   - Evaluates changes instantaneously via Alpine.js.
   - **Gross Salary** = Basic Salary + Total Active Allowances.
   - **EPF Base Salary** = Basic Salary + EPF-eligible Allowances.
   - **Total Deductions** = Employee EPF (8% of EPF Base) + APIT Tax + Other Deductions.
   - **Net Take-Home Pay** = Gross Salary − Total Deductions.
   - **Employer Cost** = Employer EPF (12% of EPF Base) + Employer ETF (3% of EPF Base).
   - **Total CTC (Cost to Company)** = Gross Salary + Total Employer Cost.

---

## 4. Employees Index Page Detailing (`/employees`)

1. **Executive Payroll KPI Tiles**:
   - **Active Team**: Total active headcount.
   - **Total Basic**: Sum of basic salaries across active staff.
   - **Gross Payroll**: Sum of basic + all active allowances.
   - **Net Take-Home**: Total net cash payable to team members.
   - **Employer EPF/ETF**: Total 15% statutory liability for the company.
   - **Total CTC**: Total monthly workforce cost.
2. **Comprehensive Table Columns**:
   - **Employee**: Avatar, Full Name, Code, and Role.
   - **Basic Salary**: Formatted tabular numbers.
   - **Allowances (+)**: Total allowances and count badge.
   - **Gross Salary**: Combined monthly gross and EPF base note.
   - **Deductions (-)**: Sum of deductions with breakdown of EPF 8% and APIT tax.
   - **Net Take-Home**: Prominent emerald badge displaying exact payable amount.
   - **Employer (15%)**: Employer EPF (12%) + ETF (3%).
   - **Total CTC**: Total cost to company for this employee.
   - **Action**: One-click **"Config"** button leading directly to the salary configuration panel.
