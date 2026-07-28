# 11. High-Level Data Model (entity list)

```
companies, departments
categories (income/expense, nested)
invoice_types
document_templates
bank_accounts
clients, partners
projects, project_client, project_partner
transactions (income/expense/petty cash — unified)
budgets, budget_transactions (pivot linking transaction -> budget)
invoices, invoice_items
payments, payment_modes (cash/card/cheque/bank_transfer per payment)
change_requests
notes, interactions (polymorphic to project or client)
loans, loan_draws, loan_principal_repayments, loan_interest_schedule
reminders (polymorphic source)
tags, taggables (polymorphic)
share_links (polymorphic), share_link_visits
attachments (polymorphic)
activity_logs (polymorphic)
exchange_rates
journal_entries, journal_entry_lines (optional — for accurate balance sheet)
users, roles, permissions
```
