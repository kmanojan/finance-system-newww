# 5. All Reminders Page (Unified)

A single reminders engine used by: cheque deposit dates, recurring invoice generation, loan interest due dates (§7), invoice due dates, budget threshold alerts, follow-up interactions (§3.8).

## List screen
**Columns:** Type (Cheque/Invoice/Loan Interest/Budget Alert/Custom), Title, Due Date, Days Left/Overdue, Linked Record (click-through to source), Status (Pending/Snoozed/Settled/Dismissed), Actions (Settle / No Longer Needed / Snooze / View Source)
**Filters:** Date range, Type, Status
**Calendar view (toggle):** all upcoming reminders plotted on a monthly calendar

## Create / Edit Reminder (manual/custom type only — most reminders auto-generate)
- Title *(required)*
- Type *(defaults to "Custom" for manually created ones)*
- Due Date *(required)*
- Notify Before (days) *(required, e.g. 2 or 3)*
- Linked Record *(optional — polymorphic link to project/loan/invoice/etc.)*
- Notes
**Delete:** allowed for Custom type; system-generated reminders (cheque/invoice/loan) are dismissed via their source record instead, to keep the link intact.

## Behavior rules
- **Skip/snooze logic**: user can dismiss for today; reminder **reappears the next day** automatically until explicitly marked **Settled** or **No Longer Needed**.
- **Settled**: marks the underlying action as done (e.g., cheque deposited, invoice paid, loan interest paid) — stops the reminder permanently.
- **No Longer Needed**: dismisses the reminder without marking the underlying record complete (e.g., loan waived, cheque cancelled) — also stops it permanently.
- Notification channel: in-app bell + email (and optionally WhatsApp).
