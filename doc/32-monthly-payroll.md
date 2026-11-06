# Monthly payroll

Implemented on 2026-11-06. Entry point: **People → Monthly payroll** (`/payroll`).

## Working through a month

1. In **Employees**, maintain basic salary, enabled earnings/deductions/employer contributions, bank details and EPF member number. Salary components remain configurable in Master Data.
2. Choose **Create payroll**. Select the company, expense department, month, LKR currency, due dates and employees. The selector includes inactive employees for a final salary; bulk selection selects active employees only. Confirm company membership and that the current salary package applies to the selected month.
3. Open **Review & adjust** for each employee. Enter overtime, bonus, additional allowance, unpaid leave and other deduction amounts. Recover only recorded salary advances. Review EPF and ETF eligible earnings separately, the enabled rates, and the APIT amount. Include calculations and reasons in review notes, then mark reviewed and save.
4. **Submit for approval**. An administrator or manager approves; an accountant can prepare and record payments but cannot approve, void or reverse. Return a submitted run to draft to correct it.
5. Approval locks the salary snapshot and posts the accrual at month end. **Record payment** separately for salary, EPF, ETF, APIT or other deductions/contributions. Choose one employee or a bulk payment, actual date, amount, cash/bank account and reference. Optionally upload a private receipt. Bulk amounts allocate in employee register order, capped by each outstanding balance.
6. The register and each employee view show due, paid and remaining amounts. Download approved payslips and the CSV register. These are records/exports, not instructions automatically submitted to a bank or government portal.

## Advances and corrections

**Salary advances** records actual advance payments and the remaining employee receivable. Recoveries are entered on monthly payroll and take effect at approval. The recurring `SALARY_ADVANCE` component is deliberately excluded from payroll calculation to prevent untracked or duplicate recovery. An advance issued after payroll month end cannot fund that month's recovery.

Payroll payments must be dated on or after the salary month's end. Earlier payments should be recorded as advances and recovered in the monthly run. Future payment dates are rejected.

A manager/admin can reverse a payment with a date and reason. The original record remains, with a new bank/cash transaction and reversing journal. Reconciled payments must be unreconciled first. Payroll-generated cash transactions cannot be edited or deleted from the general transaction screen.

To replace an approved payroll, reverse its payments and void it with a reason. This reverses the accrual without deleting payslips/history. The company/month and employee/month keys are released so a corrected run can be created. Voided payslips are visibly marked VOID. Drafts do not affect the ledger. A zero-pay month can be approved without creating an empty journal.

## Calculations and accounting

All payroll totals and allocations are integer cents; components round individually. Basic salary appears once even if the BASIC component is mapped. Earnings percentages follow the existing salary-profile convention: the base before allowances, avoiding circular calculations.

- Gross = basic + configured earnings + overtime + bonus + additional allowance − unpaid leave.
- Net = gross − employee EPF − APIT − other employee deductions − advance recovery.
- Employer cost = gross + employer EPF + ETF + other employer contributions.
- Negative net/gross, overpayments, advance over-recovery, duplicate employee-months and duplicate company-month-currency runs are rejected.
- EPF due combines employee and employer portions. Other deductions/contributions have a separate payable and settlement category.

At approval: debit payroll expense; credit net salaries payable, EPF, ETF, APIT and other payables; credit employee advances for recoveries. At payment: debit the appropriate payable, credit cash/bank. Advance issuance: debit employee advances, credit cash/bank. The ledger expense is not posted again on payment. Period locks and company/currency checks apply to journals and payment banks.

Accounts: 5210 payroll expense; 2310 salaries; 2320 EPF; 2330 ETF; 2340 APIT; 2350 other payroll payables; 1250 advances; 1010 shared cash/bank. Existing ledger service maintains company-specific accounts. Bank-account balances use the linked cash transactions.

Snapshots retain employee identity, bank/member details, current salary component definitions/rates, capture time and calculated values. Later salary-profile changes do not rewrite existing payroll. This is monthly snapshot history, not an effective-dated salary-contract scheduler. For an incorrect draft package, void and recreate after correcting the profile.

## Statutory review and boundaries

This implementation supports LKR payroll. It does not automatically determine tax residency, primary/secondary employment, cumulative tax relief, bonus/terminal-payment tax, gratuity, overtime entitlement, holiday calendars or attendance. Enter reviewed adjustment amounts, bases and APIT using the appropriate rules. It does not automatically prorate joiners/leavers. EPF/ETF enabled rates come from each employee's saved package; disabled contributions must be consciously reviewed, including their reason. ETF's initial base copies EPF's as a starting value, but both fields are separately editable and must be reviewed.

Official sources checked on 2026-11-06:

- [EPF employer FAQ](https://epf.lk/?page_id=811): employee/employer contribution guidance and next-month last-working-day remittance.
- [ETF contribution payments](https://etfb.lk/payment-of-contributions/): next-month last-working-day remittance and payment process.
- [IRD APIT overview](https://www.ird.gov.lk/en/Type%20of%20Taxes/SitePages/Advance%20Personal%20Income%20Tax%20%28APIT%29.aspx): deduction under applicable tables and remittance by the 15th of the following month.
- [IRD APIT tables](https://www.ird.gov.lk/en/publications/sitepages/apit_tax_tables.aspx?menuid=1502): different tables for regular, lump-sum, cumulative, secondary and other cases.

Due-date defaults are a starting point and are editable when creating the run. Confirm working-day/public-holiday changes and the applicable APIT deduction/remittance period, particularly when salaries are paid late. No claim of automatic statutory filing compliance is made.

## Access and storage

Payroll and employee salary endpoints, including the employee JSON API, are restricted to admin/manager/accountant roles. Receipts are in private storage and downloaded through an authorized payroll route. CSV text is escaped against spreadsheet formula injection. State changes and payments are audited.

Migrations `2026_11_06_000006` and `000007` add payroll tables and optional cashbook metadata missing in imported installations. The compatibility migration preserves existing transaction amounts and IDs; historical cashbook entries default to completed. No sample employees or payroll payments are inserted into the actual database.

## Verification

Automated coverage includes snapshot immutability, BASIC de-duplication, adjustments, review/approval, zero pay, balanced accruals, partial/bulk/statutory payments, duplicate request protection, advance recovery, reversals/void/reissue, period locks, company/bank boundaries, private receipts, role restrictions, payslips, exports and legacy cashbook preservation.

Browser validation uses DOM and actual form requests at 1440/768/390px in light/dark themes. Screenshots are disabled at the user's request.

## Employee component entry on payroll creation

`/payroll/create` uses a paginated employee/component table with a fixed first employee column and sticky grouped headers for **Income (earnings)**, **Expenses (employee deductions)**, employer contributions and totals. Each enabled salary component has its own column. Employer contributions are separate because they do not reduce net salary. Selected-employee totals show gross income, deductions, net salary and total employer cost. Search and active-employee bulk selection preserve entered amounts; deselected employees are excluded from submission and totals.

Salary Components now has **Payroll entry**:

- **Display only** (default for existing components): calculate from the employee salary profile and render a readable label, including percentage-rate context.
- **Enter amount each payroll**: render a currency amount input for enabled components assigned to that employee. Suitable for variable entries such as project commissions. Each new run starts at zero; entering an amount does not change the recurring profile. Even if the component's normal calculation method is percentage, this monthly input is the final currency amount, not a percentage.

To configure: Master Data → Salary Components → Add/Edit → Payroll entry. Then assign and enable the component on the employee's salary profile. The payroll creation screen shows it for that employee. Monthly entries are also editable on the draft employee review page and are retained in snapshots and payslips. Existing snapshots retain their original behavior when a master setting changes.

Basic salary, EPF, ETF, APIT and advance codes retain their dedicated controls; the master prevents changing them to monthly entry. Generic variable earnings, deductions and employer contributions are supported. Their existing eligibility flags still apply; choosing an input mode does not determine tax or statutory treatment.

Preview requests use the same server calculator as payroll creation and do not write payroll/ledger records. Creation recalculates independently and rejects fixed, unassigned, disabled, foreign-employee, negative or excessive-precision overrides. The new migration `2026_11_06_000008` adds the attribute with Display only as its default and preserves existing salary values.


### Preparing payroll for up to 1,000 employees

The creation table loads 25 employees initially, with 50/100 row options. Search and pagination run on the server; the browser never renders the whole 1,000-employee directory at once. **Select this page** selects eligible active employees on the visible page; **Select all matching active employees** includes all matches across pages (up to 1,000). Inactive employees can still be selected manually for a final payroll. Missing or non-LKR salary profiles remain unselectable.

Selections, monthly amounts and calculated totals are held in memory across page/search changes. The count and summary include all selected employees, including those hidden by the current search. They are not saved drafts until Create payroll succeeds; leaving/reloading the screen discards unsaved state. Fixed and unassigned components remain read-only. The employee column stays fixed during horizontal scroll, and column headers stay visible during vertical scroll.

Only changed employees are recalculated; bulk preview requests use batches of at most 100 employees. Final creation uses JSON, avoiding PHP `max_input_vars` truncation when thousands of logical employee/component inputs are submitted. Server-side validation and calculation still govern every row. Bulk selection above 1,000 prompts the user to refine the search rather than silently truncating. A failed create request keeps the selections and entered amounts in the browser.

Verification includes server pagination/search/role boundaries, a 1,000-employee JSON draft with off-page amounts, bounded browser rows, persistent edits, and fixed-column/header geometry across desktop, tablet and mobile themes. Synthetic scale data is confined to the isolated review database.

### Schedule an advance for a later payroll month

**People → Salary advances → Record advance** now requires **Deduct in payroll month**, separately from the actual payment date. Example: pay LKR 2,000 on 12 February and choose March. February payroll excludes it; March payroll proposes a 2,000 advance deduction. The deduction reduces net salary, not gross earnings or statutory bases.

The creation table has a dedicated **Salary advance / Scheduled recovery** column under Expenses. Changing payroll company/month refreshes the proposals and totals. Only posted advances for the employee, company and currency, paid by the salary month end and scheduled for that month or earlier, are proposed. A deduction is capped at available net pay; any remaining amount carries forward. Staff review the proposed amount in the draft employee payroll before approval, and may reduce it. New advances entered after a draft was created do not silently rewrite that draft: review its recovery amount explicitly.

Approval allocates actual recovered cents to specific advances, oldest eligible schedule first. Drafts and previews do not consume balances. Later payroll cannot recover the same amount twice. Voiding payroll releases its allocations while retaining history. An advance that has been partly or fully recovered cannot be reversed using some other advance's balance.

Advance payment history shows payment date, scheduled deduction month, original amount, recovered amount, remaining balance and links to the approved payroll months that actually recovered it. Use **Deduction month** in an unrecovered advance's row to reschedule it. The chosen month cannot precede its payment month. Rescheduling an advance used in an unapproved draft forces its eligibility to be rechecked at approval; already approved salaries are not changed.

Migration `2026_11_06_000009` adds a nullable recovery month and allocation records. Existing advance payments remain **Not scheduled (legacy)** rather than guessing an intended month. Previously approved recoveries are attributed FIFO without changing payroll amounts or journal entries. An unrecovered legacy advance can be scheduled explicitly; a remaining legacy balance is also available for manual review/recovery. Inconsistent historical balances cause migration to fail for reconciliation instead of silently creating money.

Verification covers the February/March example, no early or duplicate deductions, insufficient-net carry-forward, rescheduling and stale approval, source-specific reversals, void/reissue, role restrictions and legacy migration preservation. The full suite passed 96 tests / 779 assertions; browser checks used synthetic review data and no screenshots.

Employee fields in salary advances, payroll payment allocation and the cost-allocation report use the shared `employee-selector` Blade component. It follows the department picker's modal pattern, searches names/codes, renders 50 results at a time, supports keyboard selection and clearing, and preserves old form values. Payroll payment options retain payroll item IDs; clearing means all employees in that run. Advance selection remains required. Payroll creation retains its paginated multi-employee table.

### Employee parties

Every employee has a unique `party_id` linked to a party with the `employee` role. New/imported employees get a party automatically; HR name/email/phone updates keep that same identity. Salary, bank details and payroll balances are not copied into party records. Additional party roles (for example lender or vendor), finance notes and party status are preserved. Inactive employees keep their party available because they may still have unpaid balances. Linked parties cannot be deleted or have their employee identity overwritten through the party editor.

Run the `2026_11_06_000010_link_employees_to_parties.php` migration and then `php artisan employees:sync-parties` for existing data. The command is repeatable; an existing employee link is reused. It intentionally does not merge people on names or contact details alone. Bulk imports that bypass Eloquent model events must run this command afterwards.

Find these records in **Masters → Parties → Employee**, or **View party** in the employee profile. The stable employee/party link supports a future combined salary-and-loan payable report; that report is deferred and no loan or payroll history is rewritten by this change.

Employees without a party link show **Sync as party** in the employee list and profile header. The action is available to authorised finance roles and creates only that employee's party. Once linked, it becomes **View party**. Repeated submissions reuse the existing link without duplicates; salary/payroll amounts are unchanged. Automatic party creation for normal employee imports remains enabled.
