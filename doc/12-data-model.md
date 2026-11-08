# 12. Database Data Model

Sections 1–10 retain the original module catalogue; they are not an exhaustive count of the current schema. Section 11 documents the employee/payroll extensions. Migrations are the source of truth for table definitions.

---

### 1. Authentication & System Control
- `users` — System user accounts, credentials, and roles.
- `password_reset_tokens` — Password recovery tokens.
- `sessions` — Browser session persistence.
- `cache`, `cache_locks` — Application caching key-value store.
- `jobs`, `job_batches`, `failed_jobs` — Background job execution queue.
- `activity_logs` — Polymorphic audit trail tracking all create/update/delete actions.

### 2. Multi-Company & Organizational Master Data
- `companies` — Primary company and subsidiaries (e.g. Apptimus, Joboro, Placements).
- `departments` — Hierarchical tree organizational structure (parent/child relations).
- `categories` — Income and expense classification categories (nested).
- `invoice_types` — Types of billable services/goods mapping to income/expense categories.
- `document_templates` — Document styling, logo headers, footers, background templates, and terms.
- `bank_accounts` — Multi-currency company bank accounts and cash accounts.
- `parties` — Unified directory of Clients, Vendors, Contractors, Partners, and Lenders.
- `tags`, `taggables` — Polymorphic tagging system for transactions, invoices, and projects.
- `currencies`, `currency_exchange_rates` — Currency registry and historical FX rates.

### 3. Project Management & Milestones
- `projects` — Project registry with budget limits and department attribution.
- `project_party` — Pivot table linking clients, partners, and contractors with revenue shares.
- `payment_milestones` — Project deliverables and linked milestone invoicing schedules.
- `timesheets` — Hourly task tracking for projects.
- `project_commissions` — Commission arrangements (percentage or fixed) linked to projects.
- `commission_payments` — Disbursement logs for partner/employee commissions.
- `project_documents` — Uploaded project files, specs, and contracts.

### 4. Invoicing & Billing Schedules
- `invoices` — Primary invoices with tax calculation, status tracking, and signature snapshots.
- `invoice_items` — Itemized billing rows with quantities, unit prices, and tax rates.
- `invoice_schedules` — Recurring invoice automation engines.
- `invoice_schedule_items` — Line items for recurring invoice templates.

### 5. Payments & Cheque Management
- `payments` — Incoming client receipts and outgoing disbursements.
- `payment_allocations` — Split-payment linking payments to one or more invoices.
- `payment_modes` — Split payment method records (cash, card, bank transfer, cheque).
- `cheques` — Cheque lifecycle tracker (pending deposit, cleared, bounced).

### 6. Budgeting & Daily Transactions
- `budgets` — Company, Department, or Project budget caps.
- `budget_groups` — Category groupings within a budget.
- `budget_items` — Specific line item allowances.
- `transactions` — Daily cashbook transactions (income, expense, petty cash).
- `budget_transactions` — Pivot linking transactions directly to budget items and tracking utilization.

### 7. Accounts Payable & Fixed Assets
- `purchase_orders` — PO creation and approval workflows.
- `vendor_bills` — Inward supplier bills with aging tracking.
- `fixed_assets` — Asset register, purchase cost, salvage values, and depreciation schedules.

### 8. Banking & General Ledger
- `bank_statement_imports` — Uploaded CSV/OFX statement rows with reconciliation match status.
- `financial_years` — Fiscal year periods with opening/closed status locks.
- `accounts` — Structured General Ledger Chart of Accounts (Assets, Liabilities, Equity, Revenue, Expense).
- `journal_entries` — Double-entry accounting vouchers.
- `journal_entry_lines` — Debit and Credit allocations per GL account.

### 9. Cost Allocation & External Integrations
- `employees` — Employee directory synced from HR/SSO systems.
- `servers` — Cloud servers and hosting infrastructure registry.
- `api_integrations` — External service credentials and sync history.
- `cost_allocations` — Direct distribution of employee salaries and server costs to projects.

### 10. Third-Party Loans & Public Sharing
- `loans` — Third-party loan principal, interest terms, guarantors, and linked treasury bank account (`bank_account_id`).
- `loan_interest_schedule` — Monthly interest repayment schedules.
- `loan_principal_records` — Drawdown and principal repayment transactions.
- `transactions` — Double-entry revenue inflows and expense disbursements linked to categories, departments, and treasury bank accounts (`bank_account_id`).
- `reminders` — System-wide notification triggers (tax, loans, invoices, bills).
- `change_requests` — Project scope modifications and cost impacts.
- `notes`, `interactions` — Polymorphic communication logs (calls, meetings, emails).
- `attachments` — Polymorphic file uploads and documents.
- `tax_types` — Sri Lanka tax engines (VAT, WHT, APIT, CIT rules & rates).
- `share_links`, `share_link_visits` — Tokenized public sharing links with visitor logging.

### 11. Employee Compensation & Monthly Payroll

- `salary_components` — Earnings, employee deductions and employer contributions, including fixed-display versus monthly-entry settings.
- `employee_salary_components` — Employee-specific enabled components and rates/amounts.
- `payroll_runs` — Company/month/currency, processing status, due dates and approval/reversal journals.
- `payroll_items` — Employee salary snapshots, adjustments, calculations, review flags and integer-cent obligation totals. Imported snapshots retain their salary-sheet source.
- `payroll_payments` — Actual salary/statutory/advance payments, bank/cash transactions, journals, request keys, private receipt metadata and reversal history. Advances have an independently scheduled `recovery_month`.
- `payroll_payment_allocations` — Employee shares of salary/statutory payments, supporting multiple partial release batches.
- `payroll_advance_recoveries` — Amount recovered from a particular advance by a particular payroll item; only posted payroll consumes the balance.
- `payroll_historical_advance_links` — Unique item/payment links with source hash, sheet, row, cents and operator. Historical advances are reserved for that source payroll, excluded from ordinary proposals and cannot be rescheduled. PostgreSQL RLS is enabled without public client policies.
- `payroll_statutory_events` — Append-only preparation/submission history, reviewed due dates and private evidence, separate from remittance payments.

The salary advance register is a filtered read model over these tables; the redesign adds no table or migration. See [Monthly payroll](32-monthly-payroll.md) for lifecycle/accounting rules and [the 8 November release](34-salary-advances-release-2026-11-08.md) for migration and verification details.
