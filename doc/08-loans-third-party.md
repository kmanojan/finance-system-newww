# 7. Third-Party Loans / Money Claims (Borrowing Module)

Full lifecycle: claim money → schedule interest → collect reminders every period → settle interest per period → settle principal → close loan. Built to handle your real case: you don't always know/want to calculate a rate — sometimes you just know "I pay 2,000 every 3rd" — so the module supports **both** an interest rate calculation **and** a direct fixed-amount entry, with the rate step skippable.

---

## 7.1 Loans — List screen
**Columns:** Lender, Principal, Currency, Interest Method (Rate / Fixed Amount), Outstanding Principal, Interest Paid to Date, Interest Outstanding, Next Due Date, Status (Active/Settled/Waived/Defaulted), Actions (View / Edit / Delete)
**Filters:** Date range (claimed date), Status, Lender, Tag
**Summary cards at top:** Total Borrowed (all active loans), Total Interest Paid (period), Total Outstanding Principal, Loans Due This Week

---

## 7.2 Create / Edit Loan — fields

**Basic details**
- Lender Name / Contact *(required — free text; optionally link to a master Client/Partner if the lender happens to be one)*
- Purpose / Reason *(required)*
- Principal Amount *(required)*
- Currency *(dropdown, required)*
- Claimed Date *(required)*
- Term *(e.g., "2 months" — or "Open-ended / until settled")*
- Tags
- Attachments (multi-file — loan agreement scan, ID copy, cheque photos)
- Guarantor / Witness (optional free text)
- Collateral (optional free text — if anything was pledged)

**Interest setup — this is the important part**

- **Interest Method** *(radio/dropdown: "Fixed Amount per Period" / "Percentage Rate" / "Equal Installments" / "Custom Schedule" / "No Interest")* — pick one:

  - **Fixed Amount per Period** *(your typical case — skip the rate entirely)*:
    - Interest Amount *(required, e.g. 2,000)*
    - Due Day *(e.g., "3rd of every month")*
    - Frequency *(Monthly / Quarterly / Custom interval in days)*
    - → System just repeats this fixed amount every period until the loan is settled. No calculation happens — what you enter is what's due.

  - **Percentage Rate** *(if you do want the system to calculate it for you)*:
    - Interest Rate % *(required, e.g. 2% per month)*
    - Rate Basis *(Flat on Original Principal / Reducing Balance)*
    - Due Day *(same as above)*
    - Frequency *(Monthly / Quarterly / Custom)*
    - → System auto-computes each period's interest amount from the outstanding principal at that time. Still shown to you before the reminder fires, so you can manually override any single period's computed amount if needed (e.g., a negotiated discount for one month).

  - **Equal Installments** (New in v1.1):
    - Total Interest *(required, e.g. 200 for a 20,000 loan)*
    - The system adds the Principal + Total Interest, and divides it equally by the number of term months. For example, a 20,000 loan with 200 interest over 2 months results in two scheduled payments of 10,100 each month.

  - **Custom Schedule** (New in v1.1):
    - A dynamic input table appears where you can manually define the exact **Due Date** and **Amount Due** for each period/installment. This schedule is saved directly to the database.

  - **No Interest**: skips interest scheduling entirely — only a principal repayment plan/reminder is tracked.

- Reminder Lead Time (days before due date) *(default 2–3, editable)*

**On Save:** system generates the `loan_interest_schedule` rows for the term (fixed amount, rate-calculated, equal installment, or custom schedule, per the method chosen above) and creates the first reminder.

**Delete:** soft-delete; blocked if any interest period is already Paid — must mark the loan Waived/Settled instead.

---

## 7.3 Loan Detail Page

### Interest Schedule table
Per period row: Due Date, Amount Due (fixed or calculated), Amount Paid, Status (Pending/Paid/Partially Paid/Skipped/Overdue), Paid Date, Actions:
- **Settle** — enter amount paid + date; if less than due, remainder stays visible as "Partially Paid — 1,200 outstanding" and the reminder keeps firing until fully cleared or explicitly written off.
- **No Longer Needed** — marks the period Skipped/Waived without recording payment; stops that period's reminder permanently.
- **Edit Amount** — override a single period's due amount (useful for rate-based loans where you negotiated a one-off change, or to correct a fixed amount typo).
- **Add Attachment** — receipt/cheque photo for that specific period's payment.

Reminder behavior for each period matches the unified engine (§5): appears N days before due date → if skipped today, reappears tomorrow → stops only on Settle or No Longer Needed.

### Principal section
- Outstanding Principal (live figure = Principal − all principal repayments)
- **Record Principal Repayment** button — fields: Amount, Date, Payment Mode (Cash/Card/Cheque/Bank Transfer — reuses the same multi-mode structure as project payments in §3.6), Attachments. Partial repayments allowed — reduces outstanding principal; if Interest Method = Percentage Rate + Reducing Balance, future scheduled interest recalculates automatically off the new outstanding principal.
- **Add Additional Draw** button — if you borrow more from the same lender under the same agreement later, fields: Amount, Date, Notes. Increases outstanding principal and (if Reducing Balance) affects the next period's interest calc.
- **Settle Principal Fully** button — quick action that repays the entire outstanding principal at once; loan Status moves to **Settled** once principal is 0 and every interest period is Paid or Skipped.

### API & Status lifecycle
The application provides explicit API routes to handle state transitions and dynamic loading:
- `GET /loans/schedules` and `GET /loans/settlements` to fetch relevant loan data.
- `POST /loans/{id}/activate` to transition a loan out of draft or pending state.
- `POST /loans/{id}/status` to explicitly update loan status.

Standard state flow:
`Active` → (all interest periods cleared + principal repaid) → `Settled`
`Active` → (lender waives remainder) → `Waived`
`Active` → (missed payments beyond a grace period, manually flagged) → `Defaulted` — stays visible with a red flag until resolved.

---

## 7.4 Extra features worth adding

- **Loan summary widget** on the main dashboard: total borrowed across active loans, total interest paid this month, outstanding principal, next due date, count of overdue periods.
- **Multiple loans per lender** — lender isn't forced to be unique; a "Lender" filter on the list screen rolls up all loans from the same person/entity so you can see your total exposure to one lender at a glance.
- **Interest calculation preview** — when using Percentage Rate, show a preview table of all computed period amounts before saving, so you can sanity-check the total cost of borrowing up front.
- **Cost of Borrowing summary** — total interest paid ÷ principal, shown per loan, so you can compare how expensive each borrowing source actually was over time.
- **Early settlement discount** — optional field if a lender agrees to waive remaining interest for early full repayment; recorded as a Skipped period with a note rather than silently disappearing.
- Feeds into the **Loan & Interest Summary report** (§9) and the **Reminders page** (§5) exactly like every other reminder-producing module.
