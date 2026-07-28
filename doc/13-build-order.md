# 12. Suggested Laravel Build Order

1. **Foundation**: auth, roles/permissions (Spatie), companies/departments middleware scoping, tags & attachments polymorphic packages, activity log (spatie/laravel-activitylog).
2. **Master Data**: categories, invoice types, bank accounts, document templates, clients/partners.
3. **Transactions**: daily income/expense + petty cash, with date filters, tags, exports.
4. **Budgets**: allocation + linking to transactions, budget vs actual report.
5. **Projects core**: project CRUD, dashboard shell, notes/interactions.
6. **Invoicing**: invoice items, PDF templates, all-invoices page, recurring invoice scheduler + approval queue.
7. **Payments**: multi-mode payments, cheque handling + reminder trigger.
8. **Change requests**: linkage to invoices.
9. **Unified Reminders engine**: cheque, invoice due, budget alerts.
10. **Loans/claims module**: schedule generation, reminder integration, attachments.
11. **Client/partner sharing**: share-link generation, public read-only view, visit logging, revoke/expiry.
12. **Reports module**: P&L, budget vs actual, AR aging, project profitability, then balance sheet + cash flow (once journal entries are in place).
13. **Main dashboard**: cross-company cash position, alerts, upcoming reminders summary.
14. **Polish**: notifications (email/WhatsApp), full client portal (optional upgrade path), audit viewer.

---

## Notes on Naming
Given you're calling this a personal/company finance system, consider a project name like **Ledger**, **Finlytics**, or **Apptimus Finance** — happy to help scaffold migrations/models once you pick a direction.
