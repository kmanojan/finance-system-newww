# Finance Dashboard (Main Overview Screen)

## Dashboard redesign (9 November 2026)

The financial overview uses the shared Apptimus type, colour, action, balance and radius tokens, with scoped `dashboard.css` and `dashboard.js` assets versioned by content hash. Taste direction: achromatic-ledger composition, seed 733794221, retaining the existing palette, typography and compact navigation. The primary question is “What is our cash position, and what needs attention?”

- The summary leads with cash at bank as of the selected end date, outstanding receivables at period end, and net cash movement for the selected period. Net cash movement is not labelled profit.
- Cash received and spent are shown beside a grouped bar chart, with Compare / Received / Spent controls and an exact-value data table. Reduced-motion preferences are respected. If Chart.js is unavailable, the same figures remain available in the automatically opened table. Zero activity has an explicit empty state.
- Collections list up to five unpaid invoices with client, reference, due date, outstanding amount and due-status text. Each row searches its invoice reference. Due status uses the earlier of today and the reporting end date; undated invoices say “No due date.”
- Today's work retains approvals, reminders, milestones and over-budget counts, plus direct links to monthly payroll and EPF/ETF/tax payments. Current commitments remain below the period-based panels and explicitly state their all-company scope.
- Period presets, custom dates, company and department filters remain available. Custom dates are enabled only when selected; switching company clears an incompatible department without losing other companies' options. Filters start collapsed on phones unless a filter is already applied.
- Cash-flow, operating-expense and bank-reconciliation report links retain supported date/company/department parameters. Operating expenses exclude financing; the dashboard spending list includes all cash outflows. The receivables summary opens the invoice register; individual rows search by reference.
- On phones, the cash balance spans the width, secondary figures sit below, work links move above the chart, and the exact-value table scrolls within its panel. Long client names and large amounts wrap without page overflow. Light/dark styles use existing theme tokens and focus indicators.

No schema migration or financial posting changes. Validation: 29 finance tests / 165 assertions, production asset build, Taste source lint, browser controls and populated/empty states, company/date switching, light/dark themes, and 320/390/768/1024/1440-width geometry checks. No screenshots were generated.


## Original product specification (planned scope)

The sections below retain the original roadmap. They do not describe every currently implemented control: tag/currency controls, extra charts, sticky/reorderable tiles, embedded reminder/transaction tables and dashboard export remain planned. The current UI and actual filter boundaries are described above.

A single landing screen giving a cross-company snapshot of cash position, income/expense trends, budgets, invoices, payments, and reminders — combining **tiles (KPIs)**, **charts**, and **tables** so nothing important requires digging into a module to notice.

Supports the standard cross-cutting filters: **Date Range**, **Company**, **Department**, and **Tag** — every tile/chart/table on this page reacts to the same filter bar at the top.

---

## 1. Top Filter Bar
- Date Range *(presets: Today, This Week, This Month, This Quarter, This Year, Custom)*
- Company *(dropdown — All / Apptimus / Joboro / Placements...)*
- Department *(dropdown, filtered by selected company)*
- Tag *(optional multi-select)*
- Currency display toggle *(Native / Base Currency rollup)*

---

## 2. KPI Tiles (top row)

| Tile | Shows |
|---|---|
| **Total Cash Position** | Sum of all bank account balances + petty cash, as of today |
| **Total Income (period)** | Sum of income transactions + collected invoice payments in the selected range |
| **Total Expense (period)** | Sum of expense transactions in the selected range |
| **Net Profit/Loss (period)** | Income − Expense, with a green/red indicator vs previous period |
| **Outstanding Receivables** | Total unpaid + partially paid invoice amounts, across all clients |
| **Outstanding Payables** | Total unpaid vendor/commission amounts + loan interest due |
| **Budget Utilization** | % of total allocated budget spent so far (aggregate across active budgets), with over-budget count badge |
| **Active Loans Outstanding** | Total outstanding principal across all active third-party loans |
| **Pending Approvals** | Count of draft/pending-approval invoices, change requests, and expense claims awaiting confirmation |
| **Reminders Due** | Count of reminders due today + overdue, with a red badge if any are overdue |

Each tile is clickable — drills straight into the relevant module/report pre-filtered to match.

---

## 3. Charts (second row)

- **Income vs Expense Trend** — line/bar chart, monthly buckets across the selected range, two series (income, expense), with net profit as a third overlay line.
- **Cash Flow by Bank Account** — stacked bar chart showing balance movement per bank account over time (helps spot which account is draining fastest).
- **Expense by Category (Pie/Donut)** — breakdown of expense categories for the period, clickable slice → drills into filtered transaction list.
- **Budget vs Actual (Bar)** — one bar pair (allocated vs actual) per active budget, sorted by highest overspend % first, color-coded (green under, amber near-limit, red over).
- **Department/Company Comparison (Grouped Bar)** — side-by-side income/expense/net for Apptimus vs Joboro vs Placements for the same period.
- **Receivables Aging (Bar)** — 0–30 / 31–60 / 61–90 / 90+ day buckets of outstanding invoice amounts.

---

## 4. Tables (third row)

### 4.1 Upcoming & Overdue Payments (table)
**Columns:** Invoice/Payment Ref, Client, Project, Due Date, Amount, Status (Due Soon/Overdue), Days Overdue, Action (Send Reminder / Record Payment)
- Sorted overdue-first, then soonest due date.
- Filter chip toggle: "Show only overdue."

### 4.2 Reminders Table
**Columns:** Type (Cheque/Invoice/Loan Interest/Budget Alert/Custom), Title, Due Date, Days Left/Overdue, Linked Record, Status, Actions (Settle / No Longer Needed / Snooze)
- Same data source as the unified Reminders page — this is a dashboard-embedded, top-N view (e.g., next 10), with a "View All Reminders" link through to the full page.

### 4.3 Recent Transactions (table)
**Columns:** Date, Type, Category, Department, Bank/Petty Cash, Amount, Tags
- Last N entries (e.g., 15), most recent first, with a "View All" link to the full transaction list.

### 4.4 Commission Payable Summary (table)
**Columns:** Party, Type (Partner/Vendor), Total Payable (across all projects), Next Reminder/Alert, Action (View Detail)
- Rolls up from the Partner-wise Commission report — top payable balances first.

### 4.5 Loan Summary (table)
**Columns:** Lender, Outstanding Principal, Next Interest Due Date, Interest Amount Due, Status, Action (View Loan)

---

## 5. Layout notes
- Tiles row is always visible (sticky) at the top since it's the fastest-scanning content.
- Charts and tables below can be reordered/hidden per user preference (drag-to-reorder or a "customize dashboard" toggle) — not required for v1, but a good phase-2 addition.
- Every chart and table respects the top filter bar; changing Company/Department/Date Range re-queries everything on the page at once rather than requiring separate filters per widget.
- Mobile/narrow view: tiles collapse to a 2-column grid, charts stack vertically, tables become horizontally scrollable.

---

## 6. Data sources (no new tables — this is a read/aggregation layer)
Pulls from existing entities only:
```
transactions, bank_accounts        -> cash position, income/expense trend, category breakdown
invoices, payments                 -> receivables, aging, upcoming/overdue payments
budgets, budget_transactions       -> budget vs actual
reminders                          -> reminders table
loans, loan_interest_schedule      -> loan summary
project_commissions, commission_payments -> commission payable summary
```

## 7. Extra suggestions
- **Role-based dashboard views**: an Admin sees everything cross-company; a Department Head's dashboard auto-filters to their department only.
- **Export dashboard as PDF** — a one-click "Export Snapshot" for sharing a point-in-time summary (e.g., for a partner meeting) without exposing the live admin system.
- **Alerts strip**: a slim banner above the tiles for anything urgent (e.g., "3 invoices overdue 90+ days", "Server budget over by 15%") so critical issues surface before you even scroll.
