# 9. Reports Module

A dedicated reporting layer sitting on top of transactions, invoices, budgets, and loans. Every report below should support the standard **date range filter**, company/department scope, currency (native vs base-currency rollup), and **PDF/Excel export**.

## Monthly Profit & Loss (Income Statement)
- Revenue section: grouped by income category (and drill down to invoice/project level).
- Expense section: grouped by expense category.
- Net profit/loss = total income − total expense, for the selected period.
- Month-over-month and year-over-year comparison columns (this month vs last month, this year vs last year).
- Filterable by company/department so you can see Apptimus vs Joboro vs Placements separately, or consolidated.

## Balance Sheet
- **Assets**: bank account balances (from §1 bank accounts), petty cash balances, accounts receivable (unpaid/partially paid invoices), any loan **given out** if that ever applies.
- **Liabilities**: accounts payable (unpaid expenses), outstanding loan principal + accrued unpaid interest (from §7), any deposits held.
- **Equity**: retained earnings (accumulated P&L) + capital introduced.
- Snapshot as of a chosen date (not a range) — a balance sheet is point-in-time, unlike P&L which is period-based.
- Note: this is the one report that needs real double-entry bookkeeping discipline to stay accurate (see implementation note below).

## Budget vs Actual Report
- Already scoped in §6 — this is where it surfaces as a formal report: allocated vs actual spend per budget, variance (amount + %), status flag (under/near-limit/over), grouped by department, server, or custom tag bucket.

## Additional reports worth adding

| Report | What it shows |
|---|---|
| **Cash Flow Statement** | Cash in vs cash out by period, split into operating/financing-style buckets (loan draws & repayments count as financing) — different from P&L because it's cash-basis, not accrual. |
| **Accounts Receivable Aging** | Outstanding invoices bucketed 0–30 / 31–60 / 61–90 / 90+ days overdue, per client. |
| **Project Profitability** | Per project: total invoiced, total collected, total cost/expense allocated, margin %. Ranks projects best → worst. |
| **Client Statement** | All invoices + payments for one client over a period — essentially what you'd hand a client on request (pairs well with §8 sharing). |
| **Department/Company P&L Comparison** | Side-by-side P&L columns for Apptimus vs Joboro vs Placements for the same period. |
| **Loan & Interest Summary** | Per lender: principal, interest paid to date, interest outstanding, next due date, total cost of borrowing. |
| **Tax/VAT Summary** | Given your VAT research — taxable income/expense totals for a period, useful for filing prep. |
| **Bank Reconciliation Report** | System balance vs last reconciled balance per bank account, with unreconciled transaction list. |
| **Expense by Category (Trend)** | Category-wise spend trend over the last N months — bar/line chart, good for spotting cost creep. |
| **Custom Tag Report** | Since everything is taggable, a generic "all transactions/invoices under tag X, for date range Y" report — flexible catch-all. |

## Implementation note
P&L and Budget vs Actual can be computed directly from the `transactions`/`invoices` tables you already have. A true **Balance Sheet** is the one report that benefits from an underlying **ledger/journal-entry pattern** (each transaction posts a debit + credit) rather than just summing flat transaction rows — worth considering a lightweight double-entry layer (e.g., `journal_entries` + `journal_entry_lines`) even if the rest of the UI still feels like simple income/expense entry to the user. This keeps the balance sheet mathematically guaranteed to balance instead of being reconciled by hand.
