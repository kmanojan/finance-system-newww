# 12. Complete Database Data Model (All 52 Entities)

The system consists of **52 database tables** organized across 10 functional modules:

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
- `loans` — Third-party loan principal, interest terms, and guarantors.
- `loan_interest_schedule` — Monthly interest repayment schedules.
- `loan_principal_records` — Drawdown and principal repayment transactions.
- `reminders` — System-wide notification triggers (tax, loans, invoices, bills).
- `change_requests` — Project scope modifications and cost impacts.
- `notes`, `interactions` — Polymorphic communication logs (calls, meetings, emails).
- `attachments` — Polymorphic file uploads and documents.
- `tax_types` — Sri Lanka tax engines (VAT, WHT, APIT, CIT rules & rates).
- `share_links`, `share_link_visits` — Tokenized public sharing links with visitor logging.
