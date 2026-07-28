# Payment Milestones

Lets a project define fixed billing checkpoints (e.g., "Kick-off", "Midpoint", "Completion") instead of — or alongside — a recurring invoice schedule. Each milestone has its own due date; when a milestone becomes due, it needs action (create the invoice, or skip it) and stays flagged with a red badge until that action is taken.

---

## 1. Where it lives

- **Project Detail Page** — a "Payment Milestones" tab, next to Invoices, Payments, Commission Setup, Invoice Schedule.
- **Global Sidebar** — a "Payment Milestones" panel (same place as the Draft Invoice sidebar) showing every milestone due today or overdue, across all projects, with a **red badge** showing the count.

---

## 2. Payment Milestones — List (within a project)
**Columns:** Milestone Name, Due Date, Amount (or % of project budget), Status (Upcoming / Due Today / Overdue / Invoiced / Skipped), Actions (Edit / Delete)

---

## 3. Create / Edit Milestone — fields
- Milestone Name *(required, e.g. "Development Kick-off")*
- Due Date *(required)*
- Amount Type *(radio: Fixed Amount / Percentage of Project Budget)*
  - Fixed Amount *(required if selected)*
  - Percentage *(required if selected — computed against the project's Budget Amount)*
- Currency *(dropdown, defaults to project currency)*
- Invoice Type *(dropdown — used if this milestone is converted to an invoice)*
- Description (pre-fills the invoice line item description, editable at conversion time)
- Status *(Upcoming / Due Today / Overdue / Invoiced / Skipped — system-managed, not manually set except via actions below)*

**Delete:** allowed only while Upcoming; once Due Today/Overdue/Invoiced/Skipped, use the actions below instead so history stays intact.

---

## 4. Sidebar behavior (red badge)

- A milestone enters the sidebar list the moment its Due Date arrives (Due Today), and stays listed every day after while unresolved (Overdue).
- **Red badge** on the sidebar icon/nav item shows the total count of milestones currently in **Due Today** or **Overdue** status, across all projects.
- Each sidebar row shows: Project Name, Milestone Name, Due Date, Days Overdue (if any), Amount, two actions:
  - **Create Invoice** — opens the Create Invoice screen pre-filled with this milestone's amount, currency, invoice type, and description. On save, the milestone's Status becomes **Invoiced**, it's linked to the resulting invoice, and it drops off the sidebar/badge immediately.
  - **Skip** — marks the milestone Status as **Skipped** (with an optional reason note), removes it from the sidebar/badge immediately, and does **not** generate an invoice. Kept in the project's milestone list for history, clearly marked Skipped.
- Once either action is taken, that milestone can never reappear in the badge — the badge only ever reflects milestones still sitting in Due Today/Overdue.
- Clicking a sidebar row (rather than the action buttons) opens the full milestone/project view without resolving it.

---

## 5. Extra suggestions
- **Overdue escalation**: optionally recolor/reorder overdue milestones above merely-due-today ones in the sidebar list, so the oldest unresolved item surfaces first.
- **Notification on due date**: in-app + email notification the day a milestone becomes due, in addition to the badge, so it isn't only visible to whoever happens to open the sidebar.
- **Bulk view**: a dedicated "All Payment Milestones" page (mirroring the All Invoices / All Reminders pattern) filterable by status/project/date range, for a full cross-project view beyond just what's currently due.
- **Linked invoice reference**: once Invoiced, the milestone row shows a link to the generated invoice so the connection is traceable both ways.

---

## 6. Data model
```
payment_milestones (project_id, name, due_date, amount_type, amount, percentage, currency_id,
                     invoice_type_id, description, status, invoice_id (nullable, set once Invoiced),
                     skipped_reason (nullable), resolved_at (nullable))
```