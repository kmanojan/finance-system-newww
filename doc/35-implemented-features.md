# Apptimus Finance — current feature guide

Updated: **8 November 2026**. Application baseline: commit `9666f3f`.

This guide describes features present in the application routes, controllers, services and maintained workflow documents. It is the current feature reference. Older numbered specifications include proposed capabilities; their presence in a specification does not mean they have been implemented. Historical release notes retain the facts and test results from their release dates.

The application is a Laravel/Blade finance workspace with shared CSS tokens and JavaScript, PostgreSQL/Supabase production storage, and a Vercel deployment. It supports company/department financial attribution and multiple currencies in general finance. **Monthly payroll currently supports LKR.** Company attribution is not a claim of separate tenant-level access isolation for every screen.

## Contents

1. [Module map](#module-map)
2. [Access and accounts](#access-and-accounts)
3. [Workspace, search and mobile](#workspace-search-and-mobile)
4. [Master data](#master-data)
5. [Dashboard](#dashboard)
6. [Transactions and journals](#transactions-and-journals)
7. [Projects, invoices and payments](#projects-invoices-and-payments)
8. [Budgets and project costs](#budgets-and-project-costs)
9. [Loans and loan flow](#loans-and-loan-flow)
10. [Employees, salaries and HR sync](#employees-salaries-and-hr-sync)
11. [Monthly payroll](#monthly-payroll)
12. [Salary advances](#salary-advances)
13. [Statutory obligations](#statutory-obligations)
14. [Payables, treasury and assets](#payables-treasury-and-assets)
15. [Reminders and approvals](#reminders-and-approvals)
16. [Sharing and documents](#sharing-and-documents)
17. [Reports and exports](#reports-and-exports)
18. [Accounting controls and operations](#accounting-controls-and-operations)
19. [Limits and pending work](#limits-and-pending-work)
20. [Source and verification references](#source-and-verification-references)

## Module map

| Area | Entry point | Main purpose |
|---|---|---|
| Sign-in and profile | `/login`, `/profile` | Account access, own profile/password and app settings |
| Dashboard | `/dashboard` | Cash, income/expense, debt and upcoming work |
| Master data | `/master` | Shared classifications, parties, banks, components and users |
| Daily ledger | `/transactions` | Record and find income/expense cashbook movements |
| General ledger | `/journal-entries` | Inspect journals and enter balanced manual journals |
| Projects | `/projects` | Commercial work, milestones, invoicing, payments and costs |
| All invoices | `/invoices` | Cross-project invoice management and PDF documents |
| Budgets | `/budgets` | Group/item allocations and linked transaction usage |
| Loans | `/loans` | Borrowings, principal, interest, settlements and relationships |
| Employees | `/employees` | Directory, salary profiles, history and party links |
| Monthly payroll | `/payroll` | Monthly preparation, review, approval and payments |
| Salary advances | `/payroll/advances` | Paid advances and scheduled payroll recovery |
| Statutory remittances | `/payroll/statutory` | EPF/ETF/APIT preparation, documents and remittances |
| Purchase orders/vendor bills | `/payables/purchase-orders`, `/payables/vendor-bills` | Procurement records and vendor liabilities/payments |
| Bank reconciliation | `/treasury/bank-reconciliation` | Imported statement matching |
| Fixed assets | `/assets/fixed-assets` | Asset register and monthly depreciation posting |
| Financial years | `/settings/financial-years` | Accounting periods and year-end close |
| Operations | `/reminders`, `/approvals`, `/cheques` | Due work, pending decisions and cheque lifecycle |
| Reports | `/reports` | Financial, project, party and auditor schedules |
| Sharing | `/share-links` | Scoped client/partner views with optional password/expiry |
| Administration | `/master/users`, `/activity-logs` | User access and audit history |

The [route reference](36-route-reference.md) lists the actual HTTP endpoints and controller actions, including supporting JSON and export endpoints.

## Access and accounts

Users sign in with an account email/password. Sign-in attempts are throttled; inactive accounts are rejected. The redesigned sign-in page supports light/dark presentation, password visibility, retained email after errors and an explicit keep-signed-in choice. Session duration and cookie behavior depend on deployment configuration; a long configured lifetime does not guarantee a session will never expire.

| Action | Admin | Manager | Accountant | Staff / viewer |
|---|---|---|---|---|
| Read ordinary operational screens | Yes | Yes | Yes | Yes, where no further restriction applies |
| Create/update operational records | Yes | Yes | Yes | No |
| Change master data | Yes | No | No | No |
| Manage users, financial-year settings, integrations or audit-log screen | Yes | No | No | No |
| Access employees and payroll | Yes | Yes | Yes | No |
| Prepare/review/submit payroll and record ordinary payroll payments | Yes | Yes | Yes | No |
| Approve, complete or void payroll; reverse payroll payments | Yes | Yes | No | No |
| Record historical advance reconciliation | Yes | Yes | No | No |
| Approve/reject approval-inbox items | Yes | Yes | No | No |
| Read auditor payables and salary-bearing payroll exports | Yes | Yes | Yes | No |
| Update own profile/password | Yes | Yes | Yes | Yes |

Controllers add record-level rules such as ownership of nested project/loan records, immutable posted payroll, payment recovery restrictions and closed accounting periods. Server authorization governs access; hiding a button is not the permission boundary. Admin user maintenance includes create/edit, role assignment and active/inactive management, with safeguards around privileged accounts. No default username, password, token or encryption key is documented here.

## Workspace, search and mobile

- Shared Lexend Deca typography, neutral light/dark surfaces, purple primary actions, tabular money and consistent field labels/focus states.
- A compact primary navigation rail and icon/text section navigation; desktop panels can close and reopen independently. Mobile navigation uses a drawer and a bottom destination bar.
- Global search at `/search` finds projects, invoice numbers, parties and transaction descriptions/references. Specialized employee, loan-mention, payroll and salary-advance searches use their own fields and scopes.
- Reusable employee, party, department, category, payment-mode and amount components. Employee and party components support clearing a selection where allowed.
- Rich descriptions use a theme-aware editor with related-record mentions. Loan mentions search code, linked party/lender, visible description, amount, currency and status.
- Navigation feedback covers ordinary page loads and standalone PWA navigation, with a slow-request message. Hash tabs, downloads and modified/new-window clicks are excluded.
- PWA manifest, install guidance, device safe-area spacing, static-asset caching and an offline fallback page. **Offline payroll or finance writes and a background write-sync queue are not implemented.**
- Dense comparisons may scroll within their own frame with sticky identity/headings; phone lists use labelled cards where appropriate. Browser zoom and reduced-motion preferences are preserved.

See [the maintained design system](29-ui-ux-design-system.md) and [PWA/session reference](28-pwa-session-security.md).

## Master data

| Master | Capabilities |
|---|---|
| Departments | Hierarchical organizational attribution and company association |
| Categories | Income/expense classification and parent/category structure |
| Invoice types | Reusable invoice classifications with edit/delete controls |
| Parties | Shared clients, vendors, lenders, partners and employee party records |
| Tags | Reusable classification for supported records and filters |
| Bank accounts | Company/currency accounts, account references and opening balances |
| Document templates | Invoice document presentation and template content |
| Servers | Infrastructure catalogue for project cost allocation |
| Currencies | Currency configuration, rate synchronization and historical rate lookup |
| Tax types | Configurable tax definitions and a calculation endpoint |
| Chart of accounts | Company accounts used by journal posting |
| Salary components | Earnings, employee deductions and employer contributions; fixed or percentage rules, eligibility flags and payroll input mode |
| Users | Account identity, role, department and active/inactive maintenance |

Delete/update availability depends on dependencies and the controller's safeguards. A master screen does not imply unrestricted deletion of historical financial references. Tax configuration describes software calculation settings, not automatic determination of a person's statutory liability or filing obligations.

## Dashboard

The dashboard combines cash/account balances, income and expense totals/trends, expense categories, invoice aging/overdue work and relevant project/loan/reminder summaries. Company, department and date filters are available on supported dashboard queries. Currency conversion uses the reporting service and saved rates. Figures are calculated when the page loads; this is not a streaming bank feed or a websocket-based live ledger.

## Transactions and journals

### Daily cashbook

`/transactions` supports dated income/expense records with amount/currency, classification, department, bank/cash attribution, descriptions/references and supported party/tag/payment metadata. Search and date/type/classification filters narrow the list. The shared navigation includes a quick-add transaction form.

Authorized edits/deletes apply the relevant accounting and reconciliation rules. A transaction generated by a guarded source workflow, such as payroll, must be corrected through that source workflow rather than changed directly in the cashbook. Existing historical entries are not silently reposted by opening a page or deploying code.

### Double-entry ledger

Journal entries contain a date, description, reference and lines against company accounts. Manual posting requires positive, balanced debits/credits in the same currency, valid accounts and an open posting date. Invoice, payment, loan, payroll, vendor and depreciation workflows use their posting services. Reversals preserve the link to original activity where the source workflow supplies reversal support.

See [transactions](03-daily-income-expense.md), [chart of accounts](23-chart_of_accounts.md) and [accounting controls](#accounting-controls-and-operations).

## Projects, invoices and payments

### Project workspace

Projects have searchable list/detail views and authorized create/update/delete operations subject to dependencies. Their workspace holds commercial details, linked parties, milestones, invoices, payments, recurring billing schedules, change requests, notes/interactions, commissions, timesheets, cost allocations and documents.

- **Milestones:** fixed/percentage billing amounts, dates and status; edit or remove eligible pending milestones, skip with a reason, and generate a linked invoice.
- **Change requests:** record scope/value changes and notes; update and approve eligible requests through the project workflow.
- **Timesheets:** record employee/time/cost information for the project. This does not implement an attendance or leave-management payroll engine.
- **Commissions:** configure arrangements, update/delete eligible definitions and record actual commission payments against remaining amounts.
- **Notes/interactions:** retain dated project communication and related descriptions.
- **Documents:** upload and maintain project documents with access/download rules, including client-visible versus private material.

### Invoice lifecycle and documents

The cross-project invoice screen provides search/filtering, draft creation/editing, duplication and lifecycle controls. Items and invoice configuration drive calculated amounts and PDF output. A duplicate is a new draft rather than a copied receipt/payment history. Draft deletion and posted transitions are constrained by payment and accounting state.

Approval-required invoices enter the approval workflow. Issue/cancellation synchronizes the appropriate accounting entries. Payment status follows recorded receipt allocation rather than a free-form status change. Invoice PDF can be viewed or downloaded, using the saved document/template context.

### Receipts and split payment methods

Project payment recording supports allocation to eligible invoices, positive amounts, remaining-balance checks and supported split payment methods. Bank/company/currency and accounting-period validation apply. Cheque details feed the cheque register. A payment record is distinct from an invoice, and allocating a receipt is distinct from changing the invoice's face amount.

### Recurring invoice schedules

Projects can configure, edit, pause/resume or delete eligible recurring schedules, run an occurrence immediately and skip an occurrence. Generated drafts can be approved/rejected. Scheduled generation is defined in `routes/console.php`; deployment must actually run the scheduler. Generating or marking an invoice sent does not prove an email was delivered.

See [project specification](04-project-module.md), [invoice reference](05-all-invoices-page.md), [milestones](16-payment_milestone.md) and [documents](17-payment_documents.md) for detailed field descriptions; use this guide's limits when older specifications propose additional automation.

## Budgets and project costs

Budgets organize allocated amounts into groups/items, show linked spending/income and remaining balances, and allow transactions to be attributed to budget items. A budget detail view and JSON representation support the workspace. Eligible budgets can be removed subject to their financial history.

Cost allocation records attribute employee, server or other costs to a project and period. Supported APIs provide paginated/filterable records and create/update/delete operations with period checks. Costing attribution is not a second cash payment or duplicate salary expense. Some pending cost allocations can be approved through the inbox; a complete configurable over-budget approval policy is not implied.

See [budgets](07-budget-module.md) and [cost allocation](19-cost_allocation.md).

## Loans and loan flow

### Borrowing records

Loan creation captures party/lender, principal/currency, purpose, claimed date, terms, interest settings, maturity date, bank and security information. Pending and activated facilities are distinct. Activation records the disbursement once; upfront interest can reduce the net amount received while preserving the principal and interest distinction.

Interest settings include fixed periodic interest, percentage interest and contracted-total/equal-installment options supported by the schedule generator. Principal records distinguish **additional draws** from **principal repayments**. Payment history, amounts and status supply balances; the system does not infer payments merely because a facility is marked settled.

### Two payable presentations

| Loan type | Displayed type-aware payable | Unpaid interest |
|---|---|---|
| Type 1 — interest paid separately | Outstanding principal | Shown separately |
| Type 2 — principal + interest | Outstanding principal + unpaid scheduled interest | Included in that payable |

New/legacy loans default to type 1 unless classified otherwise. Type 2's operational payable includes unpaid future scheduled periods as well as currently due periods. For a hypothetical principal of 100,000 and four monthly interest amounts of 10,000, after paying two interest periods, type 1 remains 100,000 with 20,000 separate interest; type 2 is 120,000. These are explanatory examples, not production balances.

**Full settlement still requires outstanding principal plus unpaid scheduled interest for both types.** Separate-interest display is not an interest waiver. Total paid separates principal repayments from interest, avoiding double counting a combined payment as two principal amounts. Auditor reporting separates due-by-cutoff interest and future commitments from the type-aware operational presentation.

### Single-loan workspace and corrections

The detail tabs are **Interest Schedule**, **Principal Records**, **Loan Flow**, **Loan Details**. Terms, description/security and multiple attachments live in Loan Details. Interest schedule entries support payment, optional payment note, eligible amount/date corrections and skipped-interest actions. Recorded interest payments expose edits for amount, paid date, bank and note, plus deletion/correction through their guarded ledger service. Notes remain visible as a table column.

Loan edit uses the shared party selector and permits eligible party/lender, original principal and principal maturity-date corrections. An activated-principal correction requires a reason and respects existing repayments, funding allocations, reconciliation and period locks. It does not invent a disbursement for an imported loan lacking one. Additional borrowing belongs in a draw rather than an unexplained principal correction.

### Relationships and attachments

Typing **/loan**, selecting a related loan in the description and saving supplies the relationship graph. Related loans remain independently outstanding until each has its own payment. Description mentions do not settle, activate or post money. The graph deduplicates relationships and ignores self-links, missing/external records and plain-text codes. Existing explicit repayment funding links remain separate financial evidence. Help for the flow is available through its help control.

Create/edit accepts multiple PDF, image and Word attachments, up to 10 MB per file for the allowed formats. Downloads use authorized attachment routes and private document storage for newly stored material.

Cross-loan schedules (`/loans/schedules`), settlements (`/loans/settlements`) and Party Loan Payables/facilities (`/loans/party-report`) provide aggregate working views. See [loan reference](08-loans-third-party.md) for the formulas and source-link rules.

## Employees, salaries and HR sync

### Directory and manual employees

The Employees list searches name, employee code, job position and role, with active/inactive filtering and pagination. Salary rollups summarize active employees. Mobile identity, salary values and actions remain visible. Manual employee creation captures identity/contact/job/status fields, checks employee-code uniqueness, generates a local code when absent and avoids fabricating an external HR identifier.

### Nexus integration

Administrators can configure and run the employee integration using its URL, method, bearer token and response-list path. Nexus synchronization fetches all pages and includes active and inactive employees, removing the active-only query restriction. External IDs identify updates. Repeated or incomplete pages/API failures stop the import before partial employee writes. Sync updates directory/status/contact/job fields while preserving finance salary configurations, and records safe success/failure metadata.

Employee sync is scheduled daily at 02:00 in application scheduler time; it still requires operational scheduler/queue execution. A directory record absent from one response is not automatically deleted. A manual employee is not automatically merged into an unrelated Nexus record merely because a name matches. Credentials belong in protected configuration, never in Markdown or source examples.

### Salary profiles and history

Profiles maintain basic salary, enabled components, fixed/percentage values, payment/bank details and EPF identifiers. Earnings, employee deductions and employer contributions remain distinct. Live preview shows gross, deduction, net and employer-cost effects without treating profile values as actual payments.

Salary history supports **Table**, **Flow** and **Chart** from immutable audit snapshots. Changes include old/new salary/package values, component changes, actor/time and optional reason. Table/Flow paginate; charts use the latest 100 changes and a currency/measure selection. History begins where changes were actually recorded; an unknown earlier effective date is not invented. Viewing a profile does not save default components.

### Employee parties

Employees have linked party records for payable reporting. New/synced employee handling maintains links; an unlinked employee exposes **Sync as party**. The administrative `employees:sync-parties` command repairs missing links and synchronizes linked identity/contact data without merging by name. Linking an employee does not itself create a salary payable.

See [compensation and salary history](30-salary_components_and_payroll.md) and [employee party rules](32-monthly-payroll.md#employee-parties).

## Monthly payroll

### Prepare and review

1. Create payroll for a company, expense department and month, with due dates and selected LKR employees.
2. Use the component table: sticky employee column, grouped income/employee-deduction/employer-contribution columns, and preview totals. Components configured **Display only** remain calculated labels; **Enter amount each payroll** components accept monthly currency amounts without modifying the recurring profile.
3. Review overtime, bonuses, allowances, unpaid leave, other deductions, statutory bases/tax, advances and notes. Draft changes recalculate before saving.
4. Mark employees reviewed individually or in bulk, then submit the complete run for approval. Bulk review supports selected rows, this page or all matches; selection survives search/page changes up to 1,000 employees. Version checks reject stale selections.
5. An administrator/manager approves. Approval locks snapshots, posts the month-end accrual and allocates valid advance recoveries. Return a submitted review run to draft when corrections are needed before approval.

Creation paginates employees and batches previews; JSON submissions avoid PHP's input-variable limit. Selecting all matching active employees is distinct from manually selecting an inactive employee for a final salary. More than 1,000 selected matches requires refining the search.

### Pay in batches

Salary batch entry lists only employees with unpaid salary, retaining partially paid employees and excluding fully paid or zero-net employees. The payroll detail page lists recorded batches with dates, bank/cash source, employee counts, amounts, status and allocation links. Reversals restore eligibility and remain visible in history. Source salary comparisons remain available for draft/review and are hidden after approval without deleting source data.

Salary releases may use their actual date within the payroll month (for example, 30 January for January payroll). Dates before the payroll month require salary advances; future dates and closed periods remain rejected. The accrual stays dated at month-end. The batch form retains entered amounts on validation failures, displays the error beside the save button, blocks duplicate clicks, and navigates to batch details only after a successful save. Bank options are filtered only by an actual company selector, not by the metadata on bank options.

Approved payroll supports multiple salary releases with explicit employee allocations. Each batch records actual date, bank/cash source, reference, optional private receipt and employee amounts. A search/paginated table retains off-page amounts; **Fill balances on this page** affects only visible rows. Server-side locks and remaining-balance checks reject overpayments, duplicate/invalid requests and foreign entries.

For example, a hypothetical 30,000 net salary can be recorded as 20,000 and later 10,000. The first batch leaves 10,000 payable; employees skipped by a batch retain their balances. Statutory payments do not reduce salary owed to employees.

### Processing versus payment

Processing states are **draft → review → approved → completed**, with a separate void/correction path. **Complete processing** is available after approval even when salaries or statutory amounts remain unpaid. Payment status remains **Unpaid / Partially paid / Paid**, calculated from actual allocations. Further batches remain possible after completion. Approved/completed salary balances appear in the employee portion of party payables; drafts do not create those liabilities.

Reversing a payment restores its allocated balances and retains the audit history. Voiding posted payroll requires reversing its recorded payments first, a valid date and reason, and a reversing accrual journal. Employee/month and run keys are then released for a corrected run. Payslips, employee payment history and CSV register exports retain the source run's identity and void state.

### Historical salary sheets

`/payroll/import-history` accepts a reviewed JSON manifest of confirmed employee/month mappings and source cents, workbook hash and original row references. It creates comparison drafts with a source-versus-system breakdown, preserves source tax/contribution values and rejects disagreements. Repeating a matching manifest does not duplicate runs. Optional profile initialization fills only eligible zero basic salaries; it does not overwrite existing pay packages or active status.

Missing net cells and net-only rows require an explicitly reviewed resolution before import, retained in source notes. Importing drafts alone creates no salary payments, bank movements or journals. Historical advances require the separate reconstruction described below; historical employee-loan deductions remain approval blockers until their opening balances are reconciled.

See [monthly payroll guide](32-monthly-payroll.md) for full calculations, role boundaries and corrections.

## Salary advances

The redesigned [salary advance register](32-monthly-payroll.md#salary-advance-register) shows employee/code/company, payment date/source/reference, paid/recovered/remaining amounts, deduction month and recovery status. It provides server-wide search, company/month/status filters, 25-row pagination, currency-separated totals and optional employee-balance detail. Phone cards retain labels; details dialogs expose complete references and permitted actions.

An ordinary advance records money already paid and a separately chosen recovery month. An advance paid in February may be scheduled for March. Eligible unrecovered amounts can carry forward, and recovery cannot exceed available net salary. Payroll approval records source-specific allocation; a draft preview does not consume the balance. Advance issuance debits the employee-advance asset and credits bank/cash rather than posting another salary expense.

An eligible unrecovered advance can change deduction month; an already recovered one cannot simply be reversed. Historical salary-sheet advances are uniquely linked to their source item/payment, dated on the source month's 10th under the confirmed import basis, and reserved for recovery in that same payroll. They are excluded from ordinary future proposals. Per-month atomic imports, source checks and idempotent retries protect against duplication. Their separate source Loan column is not converted into salary advances.

The register and import record bookkeeping for transfers already made; they do not connect to a bank to send money. See [8 November release](34-salary-advances-release-2026-11-08.md).

## Statutory obligations

EPF, ETF and APIT are tracked separately from employee salaries on approved/completed payroll. The statutory register filters company, month, obligation and work/payment status and shows due, actually paid, remaining, reviewed deadline, preparation and document-submission state.

| Stage | What is recorded | Financial effect |
|---|---|---|
| Preparation | Prepared date, snapshot amount, reviewed deadline, notes and optional private schedule | Does not clear the payable or create a payment |
| Document submission | External submission date, acknowledgement/reference and optional evidence | Does not prove money was remitted |
| Actual remittance | Dated, allocated bank/cash payment, including partial amounts | Reduces that statutory payable |
| Reopening documents | Manager/admin correction reason and new event | Preserves old evidence; does not undo payment |

Preparation and submission events are append-only. A legacy payment does not automatically infer a prepared/submitted document state. Payment reversal restores the payable while document history remains. Internal CSV review schedules and authorized evidence downloads support preparation, but do not file returns or make bank payments. Tax eligibility, statutory deadlines and contribution treatment still require finance review; stored defaults are not a compliance determination.

See [statutory workflow](32-monthly-payroll.md#statutory-preparation-actual-remittances-and-supporting-documents).

## Payables, treasury and assets

### Purchase orders and vendor bills

Purchase orders support list/create and approval. Vendor bills capture vendor/amount/date/company-attribution information and associated accounting. Actual vendor payments support partial settlement, remaining-balance checks, company/currency-compatible banks and payment journals. The implementation is not a complete procurement request/quotation/goods-receipt suite, nor a full AP credit-note/aging module.

### Party payable statements

`/reports/party-ledger` combines supported loan, vendor, commission and employee salary obligations and movements. Employee salary balances come from approved/completed payroll net amounts and actual batch allocations, with source drilldowns and payroll privacy checks. The former aggregate settlement writer is disabled: record payments through the originating loan, bill, commission or payroll workflow.

### Bank reconciliation

Import CSV, OFX or QFX statement lines against a chosen account. CSV requires date, amount and type; reference/description are optional. The register supports manual match/unmatch and conservative auto-match against bank, amount, movement direction, date window and supplied reference. Ambiguous candidates are left unmatched. Imported statements do not authorize new transfers. Reconciled source transactions must be unreconciled before a correction workflow that requires it.

### Assets

The fixed-asset register captures code/name, category, purchase date/cost, salvage value, useful life and depreciation method. Monthly posting supports straight-line and the implemented reducing-balance calculation, caps depreciation at the remaining base and rejects duplicate postings for a month. The current reducing-balance calculation uses a fixed annual rate in code; it is not a configurable policy engine. Fully depreciated assets are identified. Automated depreciation scheduling and asset disposal are separate pending work.

See [payables](24-accounts_payable.md), [reconciliation](25-bank_reconciliation.md) and [fixed assets](26-fixed_assets.md), treating proposed capabilities in those specifications as future work unless listed above.

## Reminders and approvals

The reminder workspace consolidates supported invoice due dates, loan interest/maturity, cheques, milestones and custom reminders. Custom reminders can be created, updated/completed/settled, snoozed and deleted. The scheduled minute task resumes expired snoozes. A visible reminder is not proof of an email, push or WhatsApp notification being sent.

The approval inbox gathers pending invoices, eligible pending cost allocations and loan-closure requests. Loan closure requires actual outstanding principal and interest to be cleared. Payroll review and approval remain in the dedicated payroll run, not this inbox. The cheque register supports recording, filtering/status updates and guarded deletion according to its lifecycle and linked records.

## Sharing and documents

Authorized users generate client/partner project or party share links with token, optional password, expiration and download setting. Links can be revoked; visits are recorded. Shared invoice/document routes enforce the link's scope and password/expiry rules. Private project material is excluded from client views. A notification preference is not a guarantee of outbound delivery infrastructure.

New loan/project attachments and payroll receipts/evidence use private document storage and authorized downloads. Production can store them durably in protected PostgreSQL document storage; local deployments may use private filesystem storage. Old public uploads and expired serverless temporary files are not automatically recovered or privatized by deploying new code. The reviewed migration command supports separately reconciling legacy public references.

## Reports and exports

| Report | Route | Scope |
|---|---|---|
| Profit & loss | `/reports/pnl` | Ledger-based revenue/expense and reporting-period results |
| Balance sheet | `/reports/balance-sheet` | Account balances at the selected position |
| Cash flow | `/reports/cash-flow` | Operating/financing cash movement distinctions |
| Expenses | `/reports/expenses` | Classified expense analysis |
| Expense trend | `/reports/expense-trend` | Expense amounts over time |
| Commissions | `/reports/commissions` | Commission entitlement/payment views |
| Cost allocation | `/reports/cost-allocations` | Employee/server/other project costing |
| Project status | `/reports/project-status` | Operational project position |
| Project profitability | `/reports/project-profitability` | Project income/cost comparisons |
| Client health | `/reports/client-health` | Client receivable/payment position |
| AR aging | `/reports/ar-aging` | Unpaid issued invoices in age buckets |
| Client statement | `/reports/client-statement` | Opening position, movements and closing balance |
| Bank reconciliation | `/reports/bank-reconciliation` | Statement/internal account matching position |
| Party ledger/payables | `/reports/party-ledger` | Supported party obligations and settlements |
| Auditor payables | `/reports/audit-payables` | Dated loan/payroll/statutory obligations and evidence |
| Loan facilities/payables | `/loans/party-report` | Party borrowing facilities with type-aware balances |

The first fifteen report keys support `/reports/export/{report}/{format}` with PDF or XLSX; the loan facilities screen uses its own workflow rather than implying that generic export route. Exports use authorized source queries and escape text to prevent it becoming a spreadsheet formula. The auditor export retains numeric amount cells, schedules, qualifications and unique loan IDs.

### Auditor payables

Choose an as-of date. The report includes borrowings and repayments through cutoff, settled loans for traceability, due interest separately from future scheduled commitments, approved salary entitlements/payments, statutory preparation/submission/remittance, draft payroll estimates outside recorded payables, coverage gaps and source exceptions. Loan codes include internal IDs to distinguish duplicates. Future payments/reversals and payroll voids are treated according to their effective dates where source evidence exists.

Currencies remain separate. Draft historical payroll is not automatically asserted to be an unpaid liability. An absent month is unknown rather than zero. The report is read-only and does not approve, reconcile or pay its source records. It does not assert that every supplier liability, daily interest accrual or statutory penalty is captured. See [auditor report basis and limits](../docs/auditor-payables.md).

## Accounting controls and operations

- Operational database writes are wrapped transactionally by the workspace middleware, with source-service validation and audit logging. File/object writes still need their separate cleanup/persistence rules.
- Account/company/currency checks, positive amounts, balanced journal validation, remaining-balance checks and record-parent validation apply in guarded financial workflows.
- Financial years contain fiscal periods with open/soft-closed/hard-closed states. Posting checks and manager/admin policies apply to corrections and period operations. Year-end close validates prerequisites, transfers balances using the closing workflow and prevents duplicate closing.
- Reporting converts using a saved exchange rate on or before the transaction/report date; missing required historical rates raise an error instead of fabricating a constant conversion.
- `finance:audit-ledger` diagnoses supported posting gaps. It is not a command to automatically invent historical journals.
- `finance:privatize-documents` previews legacy document migration; its explicit apply mode copies/verifies referenced files before removal. Review its result and backup separately.
- Defined scheduled work: recurring invoice generation at 01:00, employee integration dispatch at 02:00, and snoozed reminder resumption every minute, in the configured application timezone. A working scheduler and queue setup are deployment responsibilities.
- Vercel supports the PHP application function and built public assets. The frontend build does not require PHP. Private files must use persistent storage; temporary serverless disk is not durable evidence storage.
- Keep APP_KEY stable and valid, database credentials/token values secret, and production migrations under the reviewed deployment process. Browser migration endpoints have been removed.

See [deployment](29-deployment-and-serverless.md), [financial-year reference](22-financial_year.md) and dated release notes. This documentation does not claim that a scheduler, backup/PITR or a third-party integration is currently running merely because its code exists.

## Limits and pending work

| Area | Current boundary |
|---|---|
| Historical loan deductions in salary sheets | Need real employee-loan opening balances and reconciliation before affected payroll approval; not reconstructed from a deduction alone |
| Missing payroll months/source cells | Must be supplied or explicitly resolved; source manifests do not invent missing salaries |
| Payroll currencies | LKR only for monthly payroll workflows |
| Attendance and HR rules | No automatic attendance, leave entitlement, joiner/leaver proration, gratuity or tax-residency determination |
| Salary contracts | Profile changes take effect on save; audit history is not an effective-dated contract scheduler |
| Statutory filing/bank execution | Internal records/schedules only; no automatic authority filing or bank transfer submission |
| Outbound messaging | Complete email/WhatsApp/push delivery/reminder automation needs separate infrastructure/implementation and acceptance |
| Procurement/AP | No claim of a full requisition → quotation → goods-receipt flow, AP credit notes or complete payable-aging suite |
| Fixed assets | Disposal and automatic scheduled depreciation remain pending |
| Approvals | No full configurable policy engine for every budget exception; payroll uses its own reviewed lifecycle |
| Other roadmap reports | Specifications describe more than the concrete route/export catalogue above; a standalone tax-return/report suite is not implied |
| Historical reporting | Source edits, legacy missing dates, undated interest waivers and unresolved opening entries can limit as-of reconstruction; keep dated exports and evidence |
| Isolation/performance | Some legacy aggregate views need deeper company/department/date/currency review; paginated payroll checks do not certify every screen at arbitrary scale |
| Mobile/PWA | DOM checks cover simulated dimensions/themes; physical iOS standalone behavior still needs device acceptance |
| Legacy documents | Prior public URLs and lost temporary uploads require separate review; new private storage does not retroactively recover them |

## Source and verification references

| Concern | Maintained source |
|---|---|
| Endpoints and actions | [Routes](../routes/web.php), [complete route reference](36-route-reference.md) |
| Role/write boundaries | [Workspace authorization](../app/Http/Middleware/AuthorizeWorkspace.php), source controller guards |
| Loan calculations | [Loan balance service](../app/Services/LoanBalanceService.php), [loan guide](08-loans-third-party.md) |
| Payroll accounting/recovery | [Payroll service](../app/Services/PayrollService.php), [salary advance service](../app/Services/SalaryAdvanceService.php), [workflow guide](32-monthly-payroll.md) |
| Historical advances | [Source-link service](../app/Services/HistoricalSalaryAdvanceService.php), [release notes](34-salary-advances-release-2026-11-08.md) |
| Reports | [Report controller](../app/Http/Controllers/ReportController.php), [export controller](../app/Http/Controllers/ReportExportController.php), [auditor report basis](../docs/auditor-payables.md) |
| Data structures | [Data model reference](12-data-model.md), `database/migrations/` |
| Scheduled work | [Console schedule](../routes/console.php) |
| Interface | [Design system](29-ui-ux-design-system.md), `public/design-system.css`, page-specific styles/scripts |

At the documented baseline, the full backend suite passed **182 tests / 1,832 assertions**, the frontend suite passed **eight tests**, and the production asset build and CI passed. Salary-advance UI checks covered desktop/tablet/phone, both themes, search/filtering, empty states, labelled amounts and dialog focus without screenshots. These checks demonstrate the tested paths; they are not a claim that every older specification or external service has been fully accepted in production.

When implementing another feature, update this guide, its module workflow, the route reference if endpoints changed, and the relevant release note. Keep test evidence attached to the revision that actually ran it. Do not place private payroll rows, bank identifiers, authentication secrets or source workbooks in repository documentation.
