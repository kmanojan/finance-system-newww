# 2. Daily Income & Expense (Petty Cash / Routine Transactions)

## List screen
**Columns:** Date, Type (Income/Expense), Category, Department, Bank Account (or "Petty Cash"), Amount, Currency, Tags, Reconciled (yes/no), Actions (View / Edit / Delete)
**Filters:** Date range, Type, Department, Category, Bank Account, Tag, Reconciled status, Search term
**KPI Cards (Top):** Total Income, Total Expenses, and Net Balance computed dynamically across the entire filtered dataset.
**Pagination:** Paginated at 20 transactions per page with custom footer pagination controls (`Showing X to Y of Z transactions`), retaining all active filter query parameters across page switches.
**Bulk actions:** Export PDF, Export Excel (applies current filters)

## Create / Edit Transaction — fields
- Type *(Income / Expense — required)*
- Date *(required, defaults to today)*
- Department *(dropdown, required)*
- Category *(dropdown, filtered by Type — required)*
- Payment Source *(dropdown: specific Bank Account, or "Petty Cash" — required)*
- Amount *(required)*
- Currency *(dropdown, required — defaults to source account's currency)*
- Description *(required)*
- Reference No. (optional — cheque no., receipt no., invoice no.)
- Tags (multi-select)
- Attachments (multi-file upload — receipt photo, bill scan)
- Budget *(dropdown, optional — link to a budget from §6, only shown for Expense type)*
- Reconciled (toggle, default off — ticked once matched against bank statement)

**Delete:** soft-delete; if the transaction is reconciled or linked to a closed period, require confirmation with a warning (affects historical reports).

## Quick Add Transaction Sidebar Widget
A globally accessible quick-entry widget is pinned to the primary sidebar bottom (`<x-sidebar.quick-add-transaction />`):
- **Instant Inflow / Outflow Toggle:** Fast switch between `Expense (Outflow)` and `Income (Inflow)`.
- **Component-Driven Form Controls:**
  - **Amount:** Formatted with thousand separators via `<x-amount-input />`.
  - **Category:** Searchable, type-filtered dropdown via `<x-category-selector />`.
  - **Payment Mode:** Selection (`Normal`, `Bank Transfer`, `Petty Cash`, `Credit Card`, `Cash`) via `<x-payment-mode-selector />`.
  - **Department:** Organizational hierarchy selector via `<x-department-selector />`.
- **AJAX Submission:** Posts to `POST /transactions` with JSON response handling, live validation, and toast confirmation without full page reload.

## Extra suggestions
- Recurring transaction templates (e.g., monthly rent, salaries) — a separate "Recurring Transaction" setup (Create/Edit/Delete) with fields: Template Name, all the fields above, Frequency (monthly/quarterly/yearly), Next Run Date, Auto-post or Hold for Approval. Auto-generates real transactions on schedule.
- Petty cash **replenishment requests**: Create form with Department, Amount Requested, Reason, Status (Pending/Approved/Rejected) — approving it creates an actual income-side "Cash Top-up" transaction.
- Split transaction: allow one entry to be divided into multiple category/amount rows summing to the total.
