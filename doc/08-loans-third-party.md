# 7. Third-Party Loans / Money Claims (Borrowing Module)

For the current cross-system implementation and limits, see the [full feature guide](35-implemented-features.md#loans-and-loan-flow). Earlier design suggestions in this document are not automatically implemented features.

Full lifecycle: claim money → schedule interest → collect reminders every period → settle interest per period → settle principal → close loan. Built to handle your real case: you don't always know/want to calculate a rate — sometimes you just know "I pay 2,000 every 3rd" — so the module supports **both** an interest rate calculation **and** a direct fixed-amount entry, with the rate step skippable.

---

## 7.1 Loans — List screen
**Columns:** Loan Reference Code (`LN-XXXX`), Lender, Principal, Currency, Interest Method (Rate / Fixed Amount), Outstanding Principal, Interest Paid to Date, Interest Outstanding, Next Due Date, Status (Active/Settled/Waived/Defaulted), Actions (View / Edit / Delete)
**Filters:** Date range (claimed date), Status, Lender / Loan Code / Purpose search, Tag
**Summary cards at top:** Total Borrowed (all active loans), Total Interest Paid (period), Total Outstanding Principal, Loans Due This Week

---

## 7.2 Create / Edit Loan — fields

**Basic details**
- **Loan Reference Code** (`loan_code`, e.g. `LN-0016` — auto-generated sequential sequence or custom reference)
- Lender Name / Contact *(required — free text; optionally link to a master Client/Partner if the lender happens to be one)*
- Purpose / Reason & Terms *(rich HTML editor `<x-rich-editor />` with `/loan`, `/party`, and `/employee` slash commands and new-tab links)*
- Principal Amount *(required)*
- Currency *(dropdown, required)*
- Claimed Date *(required — determines base date for periodic schedules)*
- Full Principal Due Date *(optional — if unspecified/open-ended, remains empty/null)*
- Term *(e.g., "2 months" — or "Open-ended / until settled")*
- **Disbursement / Settlement Bank Account** (`bank_account_id` via `<x-bank-account-selector />`):
  - Links the loan to a treasury bank account.
  - Upon loan activation / disbursement, credits the bank account balance with an inflow Income transaction (`LOAN-ACT-{id}`).
  - When recording interest payments (`LOAN-INT-{id}-{scheduleId}`), principal repayments (`LOAN-PRIN-{id}`), or full settlements (`LOAN-SETTLE-{id}`), the respective Expense transaction debits the selected bank account in real time.
- Tags
- Attachments (multi-file — loan agreement scan, ID copy, cheque photos)
- Guarantor / Witness (optional free text)
- Collateral (optional free text — if anything was pledged)

**Interest setup — dynamic schedule generation**

- **Interest Method** *(radio/dropdown: "Fixed Amount per Period" / "Percentage Rate" / "Equal Installments" / "Custom Schedule" / "No Interest")* — pick one:

  - **Fixed Amount per Period** *(your typical case — skip the rate entirely)*:
    - Interest Amount *(required, e.g. 2,000)*
    - Due Day *(defaults dynamically to the Day of Claimed Date, e.g. 18th)*
    - Frequency *(Monthly / Quarterly / Custom interval in days)*
    - → System generates periodic schedule dates dynamically starting on `Claimed Date + 1 month`, `Claimed Date + 2 months`, etc.

  - **Upfront Interest (Deducted at Disbursement)**:
    - Checked via `is_upfront_interest` with optional custom `upfront_interest_amount`.
    - **Real-World Case**: E.g. Borrowing 45,000, 2,500 interest is deducted upfront on Day 1, receiving 42,500 in hand. In 1 month (or term end), full principal 45,000 is repaid.
    - **Interest Schedule**: Period 1 is generated with `due_date = claimed_date`, `paid_amount = upfront_interest_amount`, and `status = 'paid'`. For a 1-month loan, only the principal remains due at maturity; for multi-month loans, subsequent periods are scheduled as pending.
    - **Single Net Ledger Income Transaction**: Upon loan activation, the system posts **one** Income Transaction (`LOAN-ACT-{id}`) for the exact net cash received (`42,500.00`) with a detailed description: `"Loan Disbursement from {Lender} (Net received: 42,500.00, deducted 2,500.00 for upfront interest on 45,000.00 principal)"`.

  - **Percentage Rate** *(if you do want the system to calculate it for you)*:
    - Interest Rate % *(required, e.g. 2% per month)*
    - Rate Basis *(Flat on Original Principal / Reducing Balance)*
    - Due Day *(defaults to Day of Claimed Date)*
    - Frequency *(Monthly / Quarterly / Custom)*
    - → System auto-computes each period's interest amount from the outstanding principal at that time.

  - **Equal Installments**:
    - Total Interest *(required, e.g. 200 for a 20,000 loan)*
    - The system divides Principal + Total Interest equally across term months.

  - **Custom Schedule**:
    - Dynamic input table where you manually define the exact **Due Date** and **Amount Due** for each installment.

  - **No Interest**: skips interest scheduling entirely — principal repayment is tracked with optional maturity due date.

- **Loan Due Date / Maturity Date (`maturity_date`)**:
  - Full principal repayment due date with quick selector buttons (`+1 Mo`, `+2 Mo`, `+3 Mo`, `+6 Mo`, `+1 Yr`).
  - **Reminder Lead Time**: Configurable lead days (`reminder_days`: 1, 2, 3, 5, 7 days before due date).
  - Automatically registers in the `reminders` table and integrates into the unified Reminders list and calendar.
- **Zero-Interest Suppression**: If periodic interest is `0` (or "No Interest" selected), the system skips creating unnecessary empty schedule rows.

**On Save:** system generates the `loan_interest_schedule` rows dynamically based on the claimed date and creates the first reminder.

**Delete:** soft-delete; blocked if any interest period is already Paid — must mark the loan Waived/Settled instead.

---

## 7.3 Loan Detail Page

### Interest Schedule table
Per period row: Due Date, Amount Due (fixed or calculated), Amount Paid, Status (Pending/Paid/Partially Paid/Skipped/Overdue), Paid Date, Actions:
- **Settle** — enter amount, paid date, bank/payment details and an optional note; partial payments leave the remaining scheduled amount outstanding.
- **No Longer Needed** — marks the period Skipped/Waived without recording payment; stops that period's reminder permanently.
- **Edit Amount** — override a single period's due amount (useful for rate-based loans where you negotiated a one-off change, or to correct a fixed amount typo).
- **Recorded payment corrections** — edit amount, paid date, bank account and nullable note, or delete an eligible recorded interest payment through the guarded correction service. Notes appear as a column. Reconciled, linked cheque/budget or mismatched legacy payments require their links/history to be resolved first. The service checks accounting periods and the schedule's remaining amount, reverses/reposts existing journals as required and recalculates paid amount/date/status.
- **Documents** — multiple attachments are supported on the loan itself in Loan Details. A separate per-interest-payment receipt upload is not claimed by the current settlement form.

Reminder behavior for each period matches the unified engine (§5): appears N days before due date → if skipped today, reappears tomorrow → stops only on Settle or No Longer Needed.

### Principal section
- Outstanding Principal (live figure = Principal + Draws − all principal repayments)
- **Record Principal Repayment** button — amount, date, supported payment-mode/bank details and notes. Partial repayments are allowed; loan-level documents remain available in Loan Details.
- **Add Additional Draw** button — increases outstanding principal and affects future interest calculations.
- **Settle Principal Fully** button — modal prompting for payment date and payment mode; settles entire outstanding principal at once. Once principal is 0 and interest is satisfied, status moves to **Settled**.

### Paid amounts and full settlement

Total paid is recorded principal repayments plus recorded schedule paid amounts. A settled status alone does not fabricate interest payments. Contracted/unpaid interest is calculated separately from payment history. Full settlement must cover outstanding principal and unpaid scheduled interest for either payable type; source-supported upfront withholding is distinguished from additional cash payment in auditor reporting. Missing or inconsistent legacy payment evidence requires reconciliation rather than inferred settlement.

### Editing an existing loan

Loan edit uses the shared party selector and allows eligible party/lender, principal and optional full-principal maturity-date corrections. An activated-principal change requires a correction reason, respects already recorded repayments/funding and reconciled/closed-period constraints, and corrects a linked disbursement through the accounting service where one exists. Imported loans without an original disbursement do not receive invented cash movements. Additional borrowing should be an additional draw. Changing metadata or payable type does not itself rewrite the interest payment history.

---

## 7.4 Loan Sub-Modules & Reports

### 1. Loan Schedules (`/loans/schedules`)
- List of all periodic interest installments across all loans.
- **Filters:** Party selector, Start Date, End Date, Status (Pending, Paid, Partial, Overdue, Skipped).
- **Pagination:** Paginated at 15 schedules per page with active query string preservation.

### 2. Loan Settlements (`/loans/settlements`)
- Comprehensive settlement ledger merging principal repayments, additional draws, and interest payments with lender names, payment modes, and notes.
- **Filters:** Date range (Start Date, End Date).
- **Pagination:** Paginated at 15 records per page.

### 3. Party Loan Facilities Report (`/loans/party-report`)
- Dedicated facilities overview grouping loans under their respective parties/lenders.
- **Accordion UI & Lazy Loading:** Expands to fetch and display loan facilities on demand via `GET /loans/party-report/loans`, optimizing page load speed.
- **Financial Badges:** Displays Total Borrowed, Principal Repaid, Interest Paid, Total Paid, Net Payable, and Active/Settled counts per party.
- **Direct Navigation:** Facility links open the single loan view in a new tab (`target="_blank"`).
- **Pagination:** Paginated at 15 parties per page with search filtering.

---

## 7.5 API & Status lifecycle
The application provides explicit API routes to handle state transitions and dynamic loading:
- `GET /loans` (paginated loans list)
- `GET /loans/schedules` (paginated repayment schedules)
- `GET /loans/settlements` (paginated settlements ledger)
- `GET /loans/party-report` (paginated party report)
- `GET /loans/party-report/loans` (lazy-load loans for accordion)
- `POST /loans/{id}/activate` to transition a loan out of draft or pending state.
- `POST /loans/{id}/status` to explicitly update loan status.

Standard state flow:
`Active` → (all interest periods cleared + principal repaid) → `Settled`
`Active` → (lender waives remainder) → `Waived`
`Active` → (missed payments beyond a grace period, manually flagged) → `Defaulted` — stays visible with a red flag until resolved.

---

## 7.6 Extra features worth adding

- **Loan summary widget** on the main dashboard: total borrowed across active loans, total interest paid this month, outstanding principal, next due date, count of overdue periods.
- **Multiple loans per lender** — lender isn't forced to be unique; a "Lender" filter on the list screen rolls up all loans from the same person/entity so you can see your total exposure to one lender at a glance.
- **Interest calculation preview** — when using Percentage Rate, show a preview table of all computed period amounts before saving, so you can sanity-check the total cost of borrowing up front.
- **Cost of Borrowing summary** — total interest paid ÷ principal, shown per loan, so you can compare how expensive each borrowing source actually was over time.
- **Early settlement discount** — optional field if a lender agrees to waive remaining interest for early full repayment; recorded as a Skipped period with a note rather than silently disappearing.
- Feeds into the **Loan & Interest Summary report** (§9) and the **Reminders page** (§5) exactly like every other reminder-producing module.


## Loan types and payable balances

`loans.loan_type` distinguishes two payable presentations. Existing loans and new loans default to `interest_separate` (type 1). Select the type when creating or editing a loan. Changing this classification does not rewrite principal, interest rates, payment history or accounting entries.

| Type | Payable calculation | Example: 100,000 principal, four monthly interest payments of 10,000, two interest payments made |
|---|---|---|
| Type 1 — Interest paid separately (`interest_separate`) | Remaining principal only | Payable 100,000; unpaid scheduled interest 20,000 shown separately |
| Type 2 — Principal + interest (`principal_and_interest`) | Remaining principal + unpaid scheduled interest | Original obligation 140,000; payable 120,000 |

Remaining principal is initial principal plus additional draws minus recorded principal repayments. Paying interest never reduces principal. All unpaid scheduled periods count for type 2, including future periods. No-interest loans have the same payable under both types.

`LoanBalanceService` supplies the loan list, detail page, party totals and expanded party facilities. A party's total sums each facility's own type-aware payable; it must not add all interest to every principal balance. Repayment progress uses principal repayments only for type 1 and principal plus interest payments for type 2. Total cash paid remains visible separately. Reported payments come from recorded payments, never inferred from a settled status.

Full settlement still pays both outstanding principal and unpaid scheduled interest for either type. Its dialog shows this complete amount explicitly. Type 1's separate interest presentation does not waive or erase interest.

The additive migration `2026_11_06_000002_add_loan_type_to_loans_table.php` sets the database default to type 1 and assigns that value to existing rows without modifying payment history.

## Loan flow from description selections

The loan detail flow reads the existing loan links selected with **/loan** in the description / purpose editor. Edit the description, select a related loan, and save using the normal loan form. No separate linking action is needed. Existing saved descriptions work immediately without a backfill. Removing a selected loan from the description removes that relationship on the next page load, provided another description does not still link the same pair.

These connections are relationships only. Borrowing 10,000 and then another 10,000 leaves both loans outstanding for a combined principal of 20,000. Paying one loan changes only that loan's balance. Selecting or removing a loan in a description never records a payment, posts a journal, activates a loan, or changes a status. This applies to both payable types; each node uses LoanBalanceService for its own balance and separate interest information.

The flow shows the complete connected group from either loan. Dashed arrows show description relationships, arranged by borrowing date (ID breaks ties); connection rows link back to the description that supplies the relationship. Duplicate and reciprocal mentions collapse into a single relationship. Self-links, missing loans, external-site URLs, non-loan links, and plain-text loan numbers do not produce connections. CKEditor may strip mention classes, so the parser uses the saved local loan anchor URL. It performs no network requests.

Explicit principal repayments funded by another loan remain separate financial records. If recorded with the optional funding-loan field on a payment form, they appear as solid arrows and reference the actual repayment. Description references never imply repayment funding. Both edge types can be shown between the same loans without overlapping their labels.

The flow follows the shared light/dark design system and supports horizontal keyboard scrolling on small screens. It has no extra **Link another borrowing** or **Link a past repayment** buttons. Descriptions remain the source of truth for related loans. No separate relationship table or backfill is needed for description selections.

### Loan detail navigation

Loan Flow is now the tab immediately after Principal Records. The page has one main payable summary, supporting balance figures, and four full-width tabs: Interest Schedule, Principal Records, Loan Flow, Loan Details. Loan Details contains the terms, description, bank account, security information, and documents. Existing flow and description deep links automatically open the appropriate tab. Payment calculations and stored financial records are unchanged by this redesign.

### Multiple loan attachments

Create and edit forms accept multiple PDF, JPG/JPEG, PNG, WebP and Word documents. Editing appends files without replacing existing documents, including activated loans whose financial terms remain locked. Downloads are available in Loan Details → Documents through the authenticated download route. The application validates every file (10 MB maximum); the form also displays lower PHP per-file and total-request limits when configured. On the current local server these are 2 MB per file and below 8 MB total. A rejected file rejects the batch. Individual attachment deletion is not currently offered; deleting an eligible pending loan removes its private and legacy public attachment files.

### Searching loans from descriptions

The `/loan` picker matches loan code (including generated codes), linked party name, lender name, visible description text, principal amount, currency and status. Multiple search terms can match across fields, for example `Abi 100,000 equipment`. Amounts with grouping commas and decimals are accepted. Rich-text formatting and HTML entities do not prevent description matches. Search runs across all loans before returning the newest 10 matches; older matches remain discoverable. Results show the party, amount and description preview, and selection still inserts the same loan link used by Loan Flow.

## Settlement notes and flow references

Full settlement, principal repayment, interest payment and interest-payment editing use the shared rich editor for optional notes (up to 20,000 characters of HTML). Use `/loan` or `/transaction` to explain how a payment was settled; multiple references can appear in the same paragraph. Formatting and safe links are preserved, while executable markup and unsafe attributes are removed. Principal notes render in the principal records and settlement register; interest notes remain in the interest schedule's Note column.

Loan flow derives dashed settlement-reference connections from saved repayment and interest notes. Loan cards remain ordered by borrowing date; selected transactions appear as separate reference cards showing their recorded ID, date, type, amount and reference. Transaction links open `/transactions?transaction_id=<id>` to show exactly that record. Duplicate references within a note are deduplicated; missing records, external lookalikes and self-loan links do not create connections. The tab count includes connected loans and referenced transactions. Editing or deleting an interest payment recalculates its note references automatically.

These references describe the settlement and do not allocate proceeds, reuse a selected transaction as the settlement, post additional money, or change another loan's payable. The settlement action still records its normal principal/interest payment. Explicit funding allocations remain solid lines. Rich notes are retained on full-settlement interest transactions even when no principal remains. Existing plain notes remain readable. No database migration is required.

### Payment and project selections

Settlement notes also accept `/payment` and `/project` (or `/payments` and `/projects`). Payments refer to existing client/project/invoice receipts, not salary or supplier releases. Selected payments open their exact read-only receipt page; projects open their detail page. Both appear as dashed reference cards in Loan flow and contribute to its tab count. They describe context only and never assign funding, allocate a receipt, or change a balance. Missing/external links are ignored.
