# Project Invoice Auto-Generation Schedule

A per-project setup screen (lives inside the Project Single View, alongside Commission Setup) that lets you define a **date range** during which invoices should be auto-generated on a schedule, with a fixed set of invoice items — instead of creating each recurring invoice by hand.

---

## 1. Where it lives

On the **Project Detail Page**, a new tab/section — **"Invoice Schedule"** — sits next to Invoices, Payments, Change Requests, and Commission Setup. A project can have **more than one schedule** active over its lifetime (e.g., a ₨50,000/month retainer for the first 6 months, then a different item set for the next 6) — so this is a list, not a single config.

---

## 2. Invoice Schedule — List (within a project)
**Columns:** Schedule Name, From Date, To Date, Frequency, Next Generation Date, Status (Active/Paused/Completed/Cancelled), Last Generated Invoice, Actions (Edit / Pause / Resume / End / Delete)

---

## 3. Create / Edit Schedule — fields

**Validity period**
- Schedule Name *(required, e.g. "Monthly Retainer — Phase 1")*
- From Date *(required — schedule starts generating invoices from this date)*
- To Date *(required — schedule automatically stops after this date; leave open-ended by setting a far future date or toggling "No End Date")*

**Recurrence**
- Frequency *(Monthly / Quarterly / Yearly / Custom Interval in Days — required)*
- Generate On Day *(e.g., "5th of every month" — for Monthly/Quarterly/Yearly; for Custom Interval, a starting date + interval in days)*
- Next Generation Date *(auto-calculated from From Date + Frequency, editable if you need to shift the very first run)*

**Invoice template (what gets generated each cycle)**
- Invoice Type *(dropdown, required — determines Income/Expense classification)*
- Currency *(dropdown, required)*
- Document Template *(dropdown, defaults to department default)*
- **Invoice Items** *(repeatable rows, at least 1 required — this is the template reused every cycle)*:
  - Description *(required)*
  - Quantity *(required, default 1)*
  - Unit Price *(required)*
  - Tax % (optional)
- Notes / Terms (rich text, pre-filled from template but editable per generated invoice)

**Generation behavior**
- Require Admin Approval Before Send *(toggle, default ON — generated invoice sits as Draft/Pending Approval until confirmed)*
- Auto-adjust for weekends/holidays *(toggle — if the generation day falls on a non-working day, shift to next working day)*
- Notify me when generated *(toggle — sends a notification/reminder when a new draft is created, so it doesn't sit unnoticed)*

**Status**
- Active / Paused / Completed / Cancelled

**Delete:** allowed only if no invoice has ever been generated from this schedule yet; otherwise use **End** (stops future generation, keeps history intact) instead of deleting.

---

## 4. How generation works

- A background job checks all Active schedules daily.
- When today matches a schedule's **Next Generation Date**, and today falls within **From Date–To Date**, the system creates a new **Draft Invoice** using the schedule's Invoice Items template, invoice date = today, due date = today + department's default payment terms.
- The draft appears in the **Approval Inbox** (and on the project's Invoices tab, tagged with the schedule it came from).
- Admin reviews, can edit line items/amounts for that specific cycle only (doesn't change the template), then clicks **Confirm & Send** — invoice moves to "Sent" and appears on the All Invoices page.
- **Next Generation Date** auto-advances to the next cycle based on Frequency.
- If **To Date** has passed, the schedule's Status auto-updates to **Completed** and generation stops — no further drafts are created.
- If **Paused**, generation is skipped until **Resumed**; the Next Generation Date doesn't advance while paused.

---

## 5. Generated Invoices History (within the schedule detail)

Each schedule's detail view shows every invoice it has ever produced:
**Columns:** Invoice No., Generated Date, Status (Draft/Pending Approval/Sent/Paid/Cancelled), Amount, Actions (View)

This gives a quick audit trail of "what has this recurring setup actually billed so far," separate from editing the template itself.

---

## 6. Draft Invoice Sidebar (Approval View)

Since generated invoices sit as **Draft/Pending Approval** until confirmed, they need a clear, dedicated place to review and act on — not buried inside each schedule's history.

- A **sidebar panel** (accessible from the Project Detail Page's Invoices tab, and also globally from the main Approval Inbox) lists all invoices currently in **Draft** or **Pending Approval** status for that project.
- **Sidebar row shows:** Invoice No. (or "Pending" if not yet numbered), Source (which schedule generated it, or "Manual" if created by hand), Amount, Generated Date, Due Date, quick Actions: **Approve**, **Edit**, **Reject/Cancel**.
- Clicking a row opens the full invoice (same Create/Edit Invoice screen from the Project module) so line items/amounts can be adjusted for that specific cycle before approving.
- **Approve** action: moves status Draft/Pending Approval → **Sent**, locks the invoice number, applies the due date, and it now appears on the All Invoices page and starts counting toward Accounts Receivable.
- **Reject/Cancel** action: moves status → **Cancelled**, keeps it in history for audit but excludes it from receivables and from the schedule's active billing count.
- A **badge/count** on the Invoices tab (and on the project card in the project list) shows how many drafts are awaiting approval, so it's visible at a glance without opening the sidebar.
- Same sidebar pattern also picks up manually created draft invoices (§3.5 in the Project module) — not just schedule-generated ones — so there's one consistent place to see "everything waiting on me" per project.

## 7. Extra suggestions

- **Escalating/step amounts**: optional support for items that change value after N cycles (e.g., a retainer that increases after month 6) — modeled as a second schedule with a later From Date rather than complicating one schedule's template.
- **Skip next occurrence**: a one-off "Skip Next" action if you know a particular cycle shouldn't be billed (e.g., agreed pause with the client) — advances Next Generation Date without producing a draft that month.
- **Conflict warning**: if two Active schedules on the same project would generate on the same date, show a warning (not a hard block — you may genuinely want two separate line items billed same-day).
- Ties into the existing **Reminders engine** — a schedule nearing its **To Date** can optionally trigger a reminder ("Retainer for Project X ends in 14 days — renew?").

---

## 8. Data model additions

```
invoice_schedules (project_id, name, from_date, to_date, frequency, generate_day, next_generation_date, require_approval, auto_adjust_holidays, status)
invoice_schedule_items (schedule_id, description, quantity, unit_price, tax_percent)
invoices.schedule_id (nullable FK — tags which schedule generated this invoice, if any)
```
