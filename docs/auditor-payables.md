# Auditor payables schedule

Open **Reports → Auditor Payables** (`/reports/audit-payables`). Choose the reporting date, then download PDF or Excel. Access is restricted to administrators, managers and accountants, including both export routes.

The report reads source records without approving payroll, posting journals, recording payments or changing loan balances. PostgreSQL reads use one repeatable, read-only snapshot. Amounts remain in original currency; there is no combined total across currencies.

## Included schedules

1. Recorded payables by currency: outstanding principal, scheduled interest due by cutoff, approved net salaries, EPF, ETF, APIT and other payroll obligations.
2. Every loan borrowed by cutoff, including fully repaid loans. Saved loan codes are accompanied by internal record IDs in all schedules, so duplicate codes cannot merge separate borrowings. Original principal, additional draws, principal repayments, interest payments, remaining balances and dated payment references are separate. Pending/unactivated loans and loans without borrowing dates appear for review but are excluded from totals.
3. Approved payroll monthly reconciliation and unpaid salary by employee. Employee portions of each salary release support partial payments and multiple batches, even when the employee has no linked party.
4. Statutory obligations by payroll month, with preparation, payment and document submission shown independently. EPF includes employee and employer portions. A document submission does not reduce the payable.
5. Payroll payment and reversal history. Payments reversed after cutoff still count at cutoff. Payroll voided after cutoff remains included until its dated reversal takes effect.
6. Unapproved payroll estimates and monthly recording coverage, kept outside recorded payables. An absent month is unknown, not zero.
7. Source-data exceptions and a management confirmation/sign-off area.

## Date and recognition basis

- Borrowings, draws and settlements use recorded effective dates, through the selected date inclusive.
- Both loan display types show principal and interest separately. Linking loans in descriptions does not settle either loan.
- Future scheduled interest is a separate commitment. The report also shows recorded payables plus future scheduled interest; this is not a daily accrued-interest calculation or a lender-approved settlement quotation.
- Legacy interest settlements without matching ledger detail use the schedule's paid date and generate a review exception. Identified upfront withholding is labelled as non-cash settlement.
- Skipped interest has no stored waiver date. It is excluded using its current state and flagged for review.
- Payroll includes whole monthly periods ended by cutoff. Current-month figures are excluded until month end, with no daily proration. Approved/completed payroll amounts use the dated payroll journal where available; later voids use the reversal journal date.
- Draft/review imports are comparison schedules. Historical payments outside the system, unresolved opening balances and imported deductions must be reconciled before they can be described as unpaid liabilities.
- Coverage begins at each company's first recorded payroll; it cannot detect an omitted employee within a run or a company's unrecorded history before its first run.
- Current master data and editable source transactions mean historical results are not immutable. Retain dated exports and supporting evidence. Deleted/edited loan payments, missing dates and undated waivers may require manual reconciliation.
- The report covers loans and payroll. It does not assert that all trade payables, penalties, daily interest accruals or other company liabilities are included.

## Export and validation

PDF uses direct paginated tables with repeated headings, wrapped evidence references, original-currency figures and page numbers. This avoids constructing the large HTML layout tree that exhausted PHP's default memory limit. Source text is retained across page boundaries, including unusually long notes. Excel retains the same schedules and qualifications; amount cells are numeric, labels are inline strings to prevent spreadsheet formula injection.

Feature tests cover date cutoffs, settled loans, separate interest, future commitments, currencies, missing dates, legacy/upfront interest, salary partial payments, statutory preparation without payment, payment reversals, voids, draft segregation, missing months, privacy and PDF/Excel exports. A 1,000-employee PDF fixture checks long references and pagination. PDF text/geometry validation uses synthetic data without screenshots.

The statutory event migration (`2026_11_08_000001_create_payroll_statutory_events`) must be applied before deploying this release. The production SQL is in `database/releases/2026-11-08-statutory.sql`.

## Plain-language report page — 9 November 2026

The web page opens with **What remains unpaid?**, one recorded total per currency and an explained breakdown: borrowed money to repay, interest due by the reporting date, employee take-home salary, employee funds/tax, and other payroll amounts where applicable. The visible parts add up to the existing service total. Principal includes amounts repayable later; the overview does not label the entire balance as overdue. Future scheduled interest and estimates without a schedule remain visibly separate and excluded.

A short review notice identifies unapproved payrolls, gaps in payroll coverage and source exceptions. An empty summary says why balances may be unavailable instead of implying nothing is owed. A genuine zero from fully paid records remains a numeric zero. Payroll's last included month is shown beside the reporting date.

Six native expandable sections retain the complete loan, salary, statutory, payment, draft/coverage and source-review schedules. Breakdown and warning links open the relevant section and move keyboard focus to its summary. Native disclosures also work without JavaScript. **How to read this report** explains payable, principal, interest, net salary, EPF, ETF, APIT, date scope and the report's limits in plain language. PDF and Excel keep the complete schedules and selected cutoff; calculation, recognition and access rules are unchanged.

The page retains the shared typography, light/dark tokens and responsive navigation. Phone summaries stack; detailed audit tables scroll inside their own frames. The page assets use content hashes. Verification: 12 auditor report tests / 122 assertions, production build, source design lint, and DOM-only browser checks at 1440, 1280, 768, 390 and 320px in both themes. Browser checks covered disclosures, keyboard activation, drilldown focus, empty dates, the glossary and export cutoff links with isolated synthetic data. No production records were changed and no screenshots were captured.

Release validation against GitHub main (`ccd9635`) passed 200 backend tests / 2,129 assertions, 22 frontend tests, PHP formatting, JavaScript syntax and the production build. The release contains only the report redesign, its tests and documentation; it requires no database migration.


### Remaining-only auditor payables (9 November 2026)

Auditor Payables now defaults to **Show → Remaining only**. Loans appear when principal, scheduled unpaid interest or a separately identified unscheduled interest estimate remains at the reporting cutoff. Fully paid loans, zero-balance salary months, and fully paid EPF, ETF, APIT and other payroll obligations are omitted. Employee salary rows remain limited to unpaid balances. Positive future interest remains separately identified, outside the current recorded-payables total; a current “settled” status does not hide an amount still outstanding by dated evidence.

Original, paid and remaining figures stay visible for partially paid items. Standalone borrowing/payment histories and employee payment-allocation tables are hidden in this view. **All records & payment history** restores the complete audit trail. Both PDF and Excel inherit the selected view and reporting date. Source exceptions, draft payrolls and missing-month warnings remain available; filtering settled items does not imply that missing records are zero. Currency totals and accounting calculations are unchanged, and genuine zero totals remain visible.

Implementation filters the completed dated report snapshot, with no database writes or migration. Verification: 14 audit-report tests / 157 assertions cover partial/full payments, future-interest-only loans, historical cutoffs, reversed statutory payments, unchanged totals, privacy and exports. Browser checks verified mode switching, export query parameters, omitted history and 320px layout without screenshots.
