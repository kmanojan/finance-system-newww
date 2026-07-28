# 0. Cross-Cutting Requirements

These aren't a separate module — they're capabilities every table/list screen should inherit.

- **Date range filter** — every list/report screen accepts `from` / `to` (plus quick presets: Today, This Week, This Month, This Quarter, This Year, Custom).
- **Tag management** — polymorphic tagging (`taggables` pivot table) so any model (transactions, projects, invoices, clients, loans) can carry multiple free-form tags. Tags have their own CRUD + color, and you can filter any list by tag.
- **Multi-currency** — since projects mention "budget with currency," every money field should store `amount + currency_code`, with a `exchange_rates` table (rate vs base currency, effective date) so reports can roll up to one base currency (e.g., LKR).
- **Attachments** — polymorphic `attachments` table (file, name, uploaded_by, model_type, model_id) reusable across transactions, loans, invoices, projects, change requests.
- **Audit trail** — polymorphic `activity_logs` (who changed what, old value → new value) — important for financial data.
- **Approval / confirmation workflow** — a generic status field (`draft → pending_approval → approved/rejected`) reusable for auto-generated invoices, budget allocations, expense claims.
- **Multi-company / department scoping** — every record belongs to a `company_id` (Apptimus, Joboro, Placements...) and optionally a `department_id`. Almost every query should be scoped by company first.
- **Notifications** — in-app + email (and optionally WhatsApp, given your other project) for reminders, approvals, invoice due dates.
- **Roles & permissions** — Spatie `laravel-permission` package. E.g., Admin, Finance Manager, Department Head, Viewer.
- **Export** — PDF (invoice-style layouts) + Excel (data dumps) on every report screen, not just petty cash.
- **Global search** — quick search across clients, projects, invoices, transactions by name/reference number.
- **Theme Support** — Application-wide toggle for Light/Dark mode, persisting user preference and updating UI elements (including charts) dynamically.
