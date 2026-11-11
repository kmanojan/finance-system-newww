# 5. All Reminders Page (Unified)

A single reminders engine aggregating: cheque deposit dates, recurring invoice generation, loan interest due dates (§7), loan principal maturity dues, invoice due dates, payment milestones, budget threshold alerts, and custom manual reminders.

## List & Calendar Screens
- **List view:** Pending reminders are grouped as Overdue, Due today, or Upcoming. The urgency counts filter the list to overdue reminders, reminders due today, or reminders due in the next seven days. The list shows the reminder type, due date, amount, status, and available actions.
- **Filters:** Search by title, notes, date, or amount and narrow by type and status. List and Calendar share the same filters. The calendar provides previous month, next month, and Today controls.
- **Calendar view:** Each day shows its reminder count. Select a date to view its agenda with full reminder titles, amounts, due dates, statuses, and source links. Arrow keys move focus between days; Enter selects a day. The agenda sits next to the calendar on wide screens and below it on smaller screens.
- **Quick add:** Add a custom reminder from a day's agenda to prefill its due date. Custom reminders can be marked complete, snoozed for 24 hours, reopened, or deleted. Manage system reminders from their source records.

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
