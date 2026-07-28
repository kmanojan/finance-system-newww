# Finance System — Changed Features (Latest Update)

This file covers only what changed in this update:
1. Clients & Partners merged into a single **Parties** table
2. New **Project Commission Setup** module
3. New **Partner-wise Commission Paid/Payable** report (accordion)
4. Supporting updates to data model & build order

---

## 1. Parties (Clients, Partners & Vendors — merged)

Clients, Partners, and Vendors are all stored in **one `parties` table** with a `type` column, since the same person/company can hold more than one role (e.g., a partner who is also occasionally a vendor). This also makes the commission module and reporting simpler — one table to look up "who do we owe/who owes us."

**List columns:** Name, Type(s) (badges: Client / Partner / Vendor — a party can carry multiple), Contact Person, Phone, Email, Tags, Linked Projects count, Status, Actions (View / Edit / Delete)

**Create / Edit fields:**
- Name / Company Name *(required)*
- Type *(multi-select: Client / Partner / Vendor — required, at least one)*
- Contact Person
- Email, Phone
- Address
- Tax ID
- Default Commission Type *(shown only if Type includes Partner or Vendor — Percentage / Fixed Amount, used as a pre-fill default when adding this party to a project's commission setup)*
- Default Commission Value *(percentage or fixed amount, matching the type above — pre-fill only, always overridable per project)*
- Tags (multi-select)
- Notes (free text)
- Status (Active/Inactive)

**Delete:** soft-delete; blocked if linked to any project (as client, revenue partner, or commission recipient) — must deactivate instead.

**View (detail page):** shows all linked projects (in whichever role), invoices/payments if acting as Client, and commission earned/paid/payable if acting as Partner or Vendor — this is the same data exposed via the share link and the Partner-wise Commission report below.

### Where this plugs into the Project screen
On the Project Create/Edit form:
- **Client** *(dropdown, required — from Parties, filtered to type = Client)*
- **Partners** *(multi-select, optional — from Parties, filtered to type = Partner, each with an override share % for this project)*

---

## 2. Project Commission Setup (new module)

Lets you assign commission to one or more **Parties** (Partners or Vendors) on a per-project basis, using either a **percentage** or a **fixed amount**, then tracks what's been paid vs what's still payable for each of them.

### Where it lives
On each **Project Detail Page**, a new "Commission Setup" tab shows every party earning commission on that project, side by side with what's owed. This is in addition to — and separate from — the revenue-share **Partners** field on the project itself, since commission here can go to a vendor who isn't a revenue-sharing partner at all (e.g., a freelancer who referred the client, or a subcontractor paid a cut of each milestone).

### Commission Setup — List (within a project)
**Columns:** Party Name, Type (Partner/Vendor), Commission Type (Percentage/Fixed), Value, Calculation Basis, Total Commission (computed), Paid, Payable (outstanding), Status (Active/Paused/Ended), Actions (Edit / Remove)

### Add / Edit Commission — fields
- Party *(dropdown, required — from Parties, filtered to type = Partner or Vendor)*
- Commission Type *(radio: Percentage / Fixed Amount — required)*
  - **Percentage**:
    - Percentage Value *(required, e.g. 10%)*
    - Calculation Basis *(dropdown: % of Invoiced Amount / % of Collected (Paid) Amount / % of Project Budget — required)*
  - **Fixed Amount**:
    - Fixed Amount *(required)*
    - Currency *(dropdown, defaults to project currency)*
    - Trigger *(dropdown: One-time on Project Start / Per Invoice Raised / Per Milestone / Manual)*
- Effective From *(date, defaults to today)*
- Effective To (optional — if the commission arrangement ends before the project does)
- Status *(Active / Paused / Ended)*
- Notes

**Delete/Remove:** soft-delete; blocked if any commission payment has already been recorded against this setup — set Status to Ended instead.

### How the commission amount is computed
- **% of Invoiced Amount** — recalculates automatically every time a new invoice is confirmed & sent on the project; total commission = sum of (invoice total × %) across all invoices.
- **% of Collected Amount** — recalculates every time a payment is recorded; total commission = sum of (amount actually collected × %). This is the safer default if you don't want to owe commission on money you haven't received yet.
- **% of Project Budget** — a single computed figure off the project's overall Budget Amount, doesn't change as invoices/payments happen.
- **Fixed Amount** — doesn't recalculate; it's either a flat one-time figure, or repeats each time its Trigger event occurs (e.g., "500 per invoice raised" auto-adds another 500 to the payable total every time a new invoice is confirmed).

### Recording a Commission Payment
From the Commission Setup row, an action **"Record Payment"** opens:
- Amount *(required, must not exceed current Payable balance)*
- Payment Date *(required)*
- Payment Mode *(Cash / Card / Cheque / Bank Transfer — same multi-mode structure as project payments)*
- Bank Account (if applicable)
- Reference No.
- Attachments (receipt, transfer slip)
- Notes

**Effect:** reduces this party's Payable balance for this project and logs it against the party for the cross-project report below. Partial payments are allowed — remainder stays Payable.

### Paid / Payable status per row
- **Total Commission** = computed per above rules, as of now
- **Paid** = sum of all recorded commission payments for this setup
- **Payable** = Total Commission − Paid (the outstanding amount still owed to this party on this project)

### Extra suggestions
- **Commission alerts**: optional reminder when a party's Payable balance crosses a threshold you set, so large amounts don't sit unpaid too long.
- **Multiple commission recipients per project** are fully supported — e.g., a referral partner at 5% of collected + a delivery vendor at a flat 500/invoice, both tracked independently on the same project.
- **Commission vs Expense**: when a commission payment is recorded, it should also post as an Expense transaction under a dedicated "Commission Payable" category, so it flows correctly into P&L and cash flow reports — not just tracked in isolation.

---

## 3. Partner-wise Commission Report (new — accordion view)

A dedicated report — **Partner-wise Paid/Payable**:

- **Top level (collapsed):** one row per Party — Name, Type, Total Commission across all their projects, Total Paid, Total Payable, Status (has outstanding? yes/no).
- **Expand (accordion):** reveals one row per project that party earns commission on — Project Name, Client, Commission Type & Value, Total Commission (that project), Paid (that project), Payable (that project), Last Payment Date.
- Filters: Date range, Party, Project, Company/Department, Status (Has Payable Only toggle).
- Export: PDF/Excel, both the summary level and the fully expanded detail level.
- This is the screen you'd use to answer "how much do I owe this partner across everything right now" in one glance, then drill into exactly which project it's coming from.

---

## 4. Supporting Data Model Changes

```
parties (clients/partners/vendors merged, with type column)
projects, project_party (pivot — client, revenue-share partner, etc. with role)
project_commissions (party + type: percentage/fixed + basis + status, per project)
commission_payments (paid amounts against a project_commission)
```

## 5. Supporting Build Order Change

Insert as its own phase, after Change Requests and before the Unified Reminders engine:

> **Commission setup**: percentage/fixed commission per project, paid/payable tracking, commission payments posting to expense transactions.

And add to the Reports phase:

> Reports module: P&L, budget vs actual, AR aging, project profitability, **partner-wise commission (accordion)**, then balance sheet + cash flow.
