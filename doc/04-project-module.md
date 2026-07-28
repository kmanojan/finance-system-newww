# 3. Project Module

## 3.1 Projects — List screen
**Columns:** Name, Client, Company, Department, Status, Budget (base currency), Invoiced, Collected, Health flag, Actions (View / Edit / Delete)
**Filters:** Date range (start date), Company, Department, Client, Partner, Status, Tag

## 3.2 Create / Edit Project — fields
- Name *(required)*
- Company *(dropdown, required)*
- Department *(dropdown, required)*
- Client *(dropdown, required — from §1.7)*
- Partners *(multi-select, optional — from §1.8, each with an override share % for this project)*
- Start Date, End Date (expected)
- Status *(Active / On-Hold / Completed / Cancelled)*
- Currency *(dropdown — project's primary currency)*
- Budget Amount (overall project budget — separate from cost-head budgets in §6, this is the client-facing contract value)
- Description
- Tags (multi-select)
**Delete:** soft-delete; blocked if invoices/payments exist — must set Status = Cancelled instead.

## 3.3 Project Detail Page (single view / dashboard)
Tabs or sections, each with its own mini list + create action:
- **Overview**: client, partners, budget vs invoiced vs collected, status, key dates
- **Budget**: linked budget line items (see §6) scoped to this project
- **Invoices**: list + "Create Invoice" button (see §3.5)
- **Payments**: list + "Record Payment" button (see §3.6)
- **Change Requests**: list + "New Change Request" button (see §3.7)
- **Notes**: list + "Add Note" button (see §3.8)
- **Interactions**: list + "Log Interaction" button (see §3.8)

## 3.4 Recurring Invoice Schedule — Create / Edit fields
(Set once per project, optional)
- Enable Auto-Invoicing *(toggle)*
- Frequency *(Monthly / Quarterly / Yearly)*
- Generate On Day *(e.g., "5th of the month")*
- Invoice Type *(dropdown from §1.4)*
- Default Line Items (template — reused each cycle, editable before sending)
- Next Generation Date *(auto-calculated, editable)*
- Require Admin Approval Before Send *(toggle, default ON)*
**Behavior:** On the scheduled day, system auto-creates a **Draft Invoice** using the template. It appears in the Approval Inbox (§10). Admin can edit line items, then click **Confirm & Send** — only then does it move to "Sent" status and appear on the All Invoices page (§4).

## 3.5 Create / Edit Invoice — fields
- Project *(pre-filled from context, required)*
- Client *(pre-filled from project, required)*
- Invoice Type *(dropdown from §1.4, required — determines Income/Expense classification)*
- Invoice No. *(auto-generated, editable)*
- Invoice Date *(required, defaults to today)*
- Due Date *(required)*
- Currency *(dropdown, required)*
- Document Template *(dropdown from §1.5, defaults to department default)*
- **Line Items** *(repeatable rows, at least 1 required)*:
  - Description *(required)*
  - Quantity *(required, default 1)*
  - Unit Price *(required)*
  - Tax % (optional)
  - Line Total *(auto-calculated)*
- Subtotal, Tax Total, Grand Total *(auto-calculated)*
- Notes / Terms (rich text, pre-filled from template but editable)
- Status *(Draft / Pending Approval / Sent / Partially Paid / Paid / Overdue / Cancelled)*
- Linked Change Request *(dropdown, optional — if this invoice originates from an approved CR)*
- Tags
**Actions:** Save Draft, Send for Approval, Confirm & Send (skips approval if auto-invoicing approval is off), Export PDF, Duplicate, Cancel
**Delete:** only allowed while status = Draft. Sent/Paid invoices can only be Cancelled (keeps audit trail), never deleted.

## 3.6 Record Payment — fields
- Project / Invoice *(pre-filled from context, required — a payment can be linked to one or more invoices)*
- Payment Date *(required)*
- Total Amount *(required, must equal sum of payment mode rows below)*
- **Payment Modes** *(repeatable rows, at least 1 required)*:
  - Mode *(Cash / Card / Cheque / Bank Transfer — required)*
  - Amount *(required)*
  - Bank Account *(dropdown, required if mode = Bank Transfer/Cheque/Card)*
  - Cheque No. *(shown only if mode = Cheque)*
  - Cheque Date *(shown only if mode = Cheque — if future-dated, auto-creates a reminder per §5)*
  - Reference No. (transaction ID, card auth code, etc.)
- Notes
- Attachments (deposit slip, cheque photo)
**Actions:** Save, Mark Cheque as Deposited / Bounced (status update on the cheque line)
**Delete:** soft-delete; reverses the invoice's paid amount and cancels any linked cheque reminder.

## 3.7 Create / Edit Change Request — fields
- Project *(pre-filled, required)*
- Title / Description *(required)*
- Amount *(required)*
- Currency *(dropdown)*
- Requested Date *(defaults to today)*
- Status *(Pending / Approved / Rejected / Invoiced)*
- **File Attachments** *(multi-file upload stored in attachments table — spec documents, client approvals, PDF diagrams)*
- **External Documentation Links** *(stored in external_links JSON column — Figma designs, Loom videos, Google Docs, specs with Title & URL)*
- Notes
**Actions:** View (opens detail modal with attachments & external links manager), Approve, Reject, **Convert to Invoice** (pre-fills a new Invoice form per §3.5 using this CR's amount/description as a line item, sets Invoice Type to one flagged as Income)
**Delete:** allowed only while Pending; Approved/Invoiced ones are kept for audit trail.

## 3.8 Notes & Interactions — Create fields
**Note:**
- Content *(required, rich text)*
- Pinned *(toggle — pinned notes show at top of project dashboard)*
**Interaction:**
- Type *(Call / Meeting / Email / Other)*
- Date & Time *(required)*
- Summary *(required)*
- Attendees / Contact
- Follow-up needed *(toggle — if on, can auto-create a reminder)*
**Delete:** both are soft-deletable by the creator or an admin.

## Extra suggestions
- Project status (active/on-hold/completed/cancelled) + health indicator (over-budget flag, auto-calculated from §6).
- Project timeline view combining invoices, payments, change requests, notes as one chronological activity feed.
- Client portal (optional later phase) — full login-based upgrade path from the read-only share link in §8.
