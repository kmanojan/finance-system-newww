# 7. Third-Party Loans / Money Claims (Borrowing Module)

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
- **Settle** — enter amount paid + date (pre-fills with that installment's `due_date`); if less than due, remainder stays visible as "Partially Paid" and the reminder keeps firing until fully cleared.
- **No Longer Needed** — marks the period Skipped/Waived without recording payment; stops that period's reminder permanently.
- **Edit Amount** — override a single period's due amount (useful for rate-based loans where you negotiated a one-off change, or to correct a fixed amount typo).
- **Add Attachment** — receipt/cheque photo for that specific period's payment.

Reminder behavior for each period matches the unified engine (§5): appears N days before due date → if skipped today, reappears tomorrow → stops only on Settle or No Longer Needed.

### Principal section
- Outstanding Principal (live figure = Principal + Draws − all principal repayments)
- **Record Principal Repayment** button — fields: Amount, Date, Payment Mode (Cash/Card/Cheque/Bank Transfer), Attachments. Partial repayments allowed.
- **Add Additional Draw** button — increases outstanding principal and affects future interest calculations.
- **Settle Principal Fully** button — modal prompting for payment date and payment mode; settles entire outstanding principal at once. Once principal is 0 and interest is satisfied, status moves to **Settled**.

### Contracted Interest Reconciliation on Settlement
When calculating Total Paid and Interest Paid across settled loans:
- If a loan is marked Settled without manual per-period schedule recording (e.g. upfront interest loans, one-shot lump sum settlements, or early principal payouts), the system automatically reconciles the contracted interest based on method (`fixed_amount`, `percentage_rate`, `upfront_interest`, or `total_interest`) so financial KPIs accurately reflect total borrowing cost paid.

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
