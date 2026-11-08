# Salary advances release — 8 November 2026

Production: `https://apptimus-finance.vercel.app/payroll/advances`.

## Historical advance reconciliation

The 2025 and available 2026 salary-sheet advance deductions were matched to the existing imported employee payroll snapshots. The approved import basis records past bank transfers on the 10th of each source salary month and schedules recovery in that same payroll month. Bank account selection came from user-confirmed master data. Source references identify the sheet and row; they do not fabricate original bank transaction references.

Migration `2026_11_08_000002_create_historical_salary_advance_links` and reviewed SQL `database/releases/2026-11-08-historical-advances.sql` add unique source-item/payment links with PostgreSQL row-level security and no public client policies. Apply the migration before deploying the source-link feature. Applying the migration alone does not create advance payments.

The manager/admin import creates past advance payments and balanced employee-advance-asset/bank journals, preserving payroll figures, review flags and processing status. Each month is atomic; retries validate existing links without duplicate payments. Historical advances are excluded from ordinary recovery proposals, cannot be rescheduled, and recover against their exact source payroll even if months are approved out of order. The separate salary-sheet Loan column still requires employee-loan opening-balance reconciliation before affected payrolls can be approved.

The completed production import was independently compared with the verified source plan and a saved pre-import database snapshot. Employee/date/amount/source links and balanced journals matched; payroll items, run states and recovery allocations were unchanged. This recorded past transfers; it did not initiate transfers or approve payrolls. Private payroll amounts and bank identifiers are retained outside repository documentation.

The import script's CSRF integration was corrected to use the rendered form field. Content-hash asset URLs prevent stale PWA scripts after deployment. Relevant commits: `7ab8a7c`, `e75782f`, `24a38b9`.

## Salary advance register redesign

Commit `9666f3f` replaces the long leading employee-balance table with a searchable, paginated advance register. The current-view summary separates paid, recovered and remaining amounts by currency. Employee/code/reference search spans all pages; company, deduction month and status filters persist through pagination. Outstanding includes partly recovered advances; reversed payments stay in history outside posted totals.

Rows show employee identity, date/bank/reference, monetary breakdown, deduction month, source payroll and recovery status. The details dialog retains the full reference and permitted scheduling/reversal actions. Historical rescheduling remains prohibited and recovered advances do not offer payment reversal. Employee balances and the grouped recording form remain available below the register. Payment/recovery services and financial amounts are unchanged; this UI release needs no migration.

The design reuses Lexend Deca, shared neutral surfaces, purple balance emphasis, 20px panels and 10px controls. Desktop uses sticky identity/headings within a bounded table; phone rows become labelled cards, with extra filters in a popover. Native dialogs support Escape, initial focus, focus return and scroll locking. Styles/scripts use content-hash URLs. See [design rules](29-ui-ux-design-system.md#salary-advance-register) and [user workflow](32-monthly-payroll.md#salary-advance-register).

## Verification

- Full backend suite: 182 tests and 1,832 assertions passed. Frontend suite: eight tests passed. Production build, PHP formatting and whitespace checks passed.
- DOM-only browser checks passed at 1440, 1280, 768 and 390px in both themes, with no document overflow or clipped money. Text contrast, mobile filters, employee search/selector, empty state, details, Escape, focus return and cancellation were checked. No screenshots were generated.
- GitHub CI and Vercel deployment succeeded for `9666f3f`. Production verification confirmed the register, filtered employee/month balances, full payment details, and restoration of the full register without changing financial records.

The older release documents retain the verification counts and limitations that applied at their respective dates.
