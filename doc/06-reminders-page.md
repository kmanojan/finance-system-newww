# 5. All Reminders Page (Unified)

A single reminders engine aggregating: cheque deposit dates, recurring invoice generation, loan interest due dates (§7), loan principal maturity dues, invoice due dates, payment milestones, budget threshold alerts, and custom manual reminders.

## List & Calendar Screens
- **List View Columns:** Type (Cheque/Invoice/Loan Interest/Milestone/Budget Alert/Custom), Title, Due Date, Days Left/Overdue, Linked Record (click-through to source in new tab), Status (Pending/Snoozed/Settled/Dismissed), Actions (Settle / No Longer Needed / Snooze / View Source).
- **Filters:** Month navigation, View toggle (List vs Calendar), Search.
- **Calendar View:** All scheduled events plotted on an equal-height month grid.
  - **Interactive Date Click & Modal:** Clicking any calendar day opens the **Date Activities Modal** displaying all scheduled items for that date with type badges, amounts, due dates, statuses (*Pending*, *Settled*, *Overdue*), and direct *"View Source"* links.
  - **1-Click Quick Add:** If no events exist for a clicked date, the modal provides a *"Create Reminder for this Date"* action that automatically pre-fills the clicked date in the creation form.

## Create / Edit Reminder (manual/custom type only — most reminders auto-generate)
- Title *(required)*
- Type *(defaults to "Custom" for manually created ones)*
- Due Date *(required)*
- Notify Before (days) *(required, default 2 or 3)*
- Linked Record *(optional — polymorphic link to project/loan/invoice/etc.)*
- Notes
**Delete:** allowed for Custom type; system-generated reminders (cheque/invoice/loan) are dismissed via their source record instead, to keep the link intact.

## Global Sidebar Notification Indicator (Red Dot)
The application dynamically evaluates active items and illuminates a red notification dot on the primary sidebar (**Operations**) and subsidebar (**Reminders & Alerts**):
- **Date-Aware Window**: To avoid alerting prematurely, reminders only trigger the red indicator when `today >= (due_date - notify_before_days)` or when due/overdue.
- **Loan Schedules, Cheques, Invoices & Milestones**: Trigger the indicator only when due today or overdue (`due_date <= today`).
- **Settlement Clearing**: Once items are marked Paid, Settled, or Not Needed, the red dot disappears automatically.

## Behavior rules
- **Skip/snooze logic**: user can dismiss for today; reminder **reappears the next day** automatically until explicitly marked **Settled** or **No Longer Needed**.
- **Settled**: marks the underlying action as done (e.g., cheque deposited, invoice paid, loan interest paid) — stops the reminder permanently.
- **No Longer Needed**: dismisses the reminder without marking the underlying record complete (e.g., loan waived, cheque cancelled) — also stops it permanently.
- Notification channel: in-app bell + sidebar indicator + email.
