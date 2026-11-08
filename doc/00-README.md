# Company Finance Management System — Specification (Feature-wise)

**For:** Apptimus (with sub-companies: Joboro, Placements, etc.)
**Stack:** Laravel (backend + admin), Blade + TailwindCSS

This specification is organized on a per-module basis for direct reference during development and architectural review.

For the current implementation, read the **[full feature guide](35-implemented-features.md)** first. It covers all implemented application areas, payroll/loan behavior, access rules, reporting and known limits. The **[route reference](36-route-reference.md)** lists actual endpoints. The original specifications below also contain proposed work; dated release notes remain historical evidence rather than a statement of today's entire feature set.

## Table of Contents

| File | Feature / Module |
|---|---|
| [`01-cross-cutting-requirements.md`](01-cross-cutting-requirements.md) | Date filters, tags, multi-currency, attachments, audit trail, approvals, notifications, roles, export, search |
| [`02-master-data.md`](02-master-data.md) | Categories, invoice types, departments, templates, bank accounts, clients/partners |
| [`03-daily-income-expense.md`](03-daily-income-expense.md) | Petty cash & routine income/expense transactions |
| [`04-project-module.md`](04-project-module.md) | Project dashboard, clients/partners, payments, change requests, recurring invoices |
| [`05-all-invoices-page.md`](05-all-invoices-page.md) | Cross-project invoice list, filtering, and bulk operations |
| [`06-reminders-page.md`](06-reminders-page.md) | Unified reminders engine (loans, cheques, invoices, tax filings) |
| [`07-budget-module.md`](07-budget-module.md) | Budget allocation & real-time tracking across groups/items |
| [`08-loans-third-party.md`](08-loans-third-party.md) | Third-party loans, drawdowns, repayment schedules & interest tracking |
| [`09-client-partner-sharing.md`](09-client-partner-sharing.md) | Secure tokenized share links with password and expiry controls |
| [`10-reports-module.md`](10-reports-module.md) | P&L, Balance Sheet, Budget vs Actual, Tax Summaries, and Cash Flow |
| [`11-additional-modules.md`](11-additional-modules.md) | Dashboard widgets, payroll-lite, approval inbox, audit viewer |
| [`12-data-model.md`](12-data-model.md) | Original module catalogue and current employee/payroll table extensions |
| [`13-build-order.md`](13-build-order.md) | Laravel build sequence and dependency order |
| [`14-dashboard.md`](14-dashboard.md) | Executive Financial Overview dashboard metrics & chart widgets |
| [`15-invoice-template.md`](15-invoice-template.md) | Visual Document Template customizer and DomPDF engine integration |
| [`16-payment_milestone.md`](16-payment_milestone.md) | Project Payment Milestones, deliverables, and linked invoicing |
| [`17-payment_documents.md`](17-payment_documents.md) | Payment receipt attachments, vouchers, and proof uploads |
| [`18-reports.md`](18-reports.md) | Extended financial reports specifications and export formats |
| [`19-cost_allocation.md`](19-cost_allocation.md) | Employee salary, server infrastructure, and external API cost distribution |
| [`20-currencies_and_exchange_rates.md`](20-currencies_and_exchange_rates.md) | Currencies, base currency config & historical exchange rate sync |
| [`21-tax_config.md`](21-tax_config.md) | Sri Lanka Tax Module specification (VAT, WHT, APIT, CIT rules & rates) |
| [`22-financial_year.md`](22-financial_year.md) | Financial Year & Fiscal Period management, period locks, year-end close |
| [`23-chart_of_accounts.md`](23-chart_of_accounts.md) | Structured Chart of Accounts (CoA) & Automated GL double-entry posting |
| [`24-accounts_payable.md`](24-accounts_payable.md) | Accounts Payable (AP), Purchase Orders, Vendor Bills & AP Aging |
| [`25-bank_reconciliation.md`](25-bank_reconciliation.md) | Bank statement import (CSV/OFX), auto-matching & BRS reporting |
| [`26-fixed_assets.md`](26-fixed_assets.md) | Fixed Asset Register, Capitalization & Automated Depreciation |
| [`27-users-master.md`](27-users-master.md) | User Management Master (Add, Create, Edit, Delete, Status Change) |
| [`28-pwa-session-security.md`](28-pwa-session-security.md) | PWA, Long-lived Sessions, Password Controls & Reusable Components |
| [`29-deployment-and-serverless.md`](29-deployment-and-serverless.md) | Vercel Serverless Functions + Supabase PostgreSQL deployment guide |
| [`29-ui-ux-design-system.md`](29-ui-ux-design-system.md) | Maintained shared design tokens, accessibility and responsive salary advance register |
| [`30-salary_components_and_payroll.md`](30-salary_components_and_payroll.md) | Salary Components Master, EPF/ETF/APIT, Allowances & Employee Detailing |
| [`31-remediation-and-release.md`](31-remediation-and-release.md) | Initial remediation scope and release safeguards |
| [`32-monthly-payroll.md`](32-monthly-payroll.md) | Monthly payroll, salary releases, statutory tracking and salary advance workflows |
| [`33-production-release-2026-11-06.md`](33-production-release-2026-11-06.md) | 6–7 November production release and recovery history |
| [`34-salary-advances-release-2026-11-08.md`](34-salary-advances-release-2026-11-08.md) | 8 November historical advance reconciliation and register redesign |
| [`35-implemented-features.md`](35-implemented-features.md) | Full current feature guide, workflows, permissions and implementation limits |
| [`36-route-reference.md`](36-route-reference.md) | Complete application endpoint/action catalogue |
| [`../docs/auditor-payables.md`](../docs/auditor-payables.md) | Auditor payables schedules, recognition basis and exports |

---

Cross-references between files use section numbers (e.g. "§6") which match the numbering used across this documentation suite.
