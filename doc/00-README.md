# Company Finance Management System — Specification (Feature-wise)

**For:** Apptimus (with sub-companies: Joboro, Placements, etc.)
**Stack:** Laravel (backend + admin), suggested Livewire/Vue for UI

This spec is split into one file per feature/module for easier reading and reference during development.

## Contents

| File | Feature |
|---|---|
| `01-cross-cutting-requirements.md` | Date filters, tags, multi-currency, attachments, audit trail, approvals, notifications, roles, export, search |
| `02-master-data.md` | Categories, invoice types, departments, templates, bank accounts, clients/partners |
| `03-daily-income-expense.md` | Petty cash & routine transactions |
| `04-project-module.md` | Project dashboard, clients/partners, payments, change requests, recurring invoices |
| `05-all-invoices-page.md` | Cross-project invoice list |
| `06-reminders-page.md` | Unified reminders engine |
| `07-budget-module.md` | Budget allocation & tracking |
| `08-loans-third-party.md` | Third-party loans / money claims |
| `09-client-partner-sharing.md` | Secure share links for clients & partners |
| `10-reports-module.md` | P&L, Balance Sheet, Budget vs Actual, and more |
| `11-additional-modules.md` | Dashboard, payroll-lite, approval inbox, audit viewer |
| `12-data-model.md` | High-level entity list |
| `13-build-order.md` | Suggested Laravel build sequence |

| `20-currencies_and_exchange_rates.md` | Currencies, base currency config & exchange rate history sync |
| `21-tax_config.md` | Sri Lanka Tax Module specification (VAT, WHT, CIT, tax types, rates) |
| `22-financial_year.md` | Financial Year & Fiscal Period management, period locks, year-end close |
| `23-chart_of_accounts.md` | Structured Chart of Accounts (CoA) & Automated GL double-entry posting |
| `24-accounts_payable.md` | Accounts Payable (AP), Purchase Orders, Vendor Bills & AP Aging |
| `25-bank_reconciliation.md` | Bank statement import (CSV/OFX), auto-matching & BRS reporting |
| `26-fixed_assets.md` | Fixed Asset Register, Capitalization & Automated Depreciation |

Cross-references between files use section numbers (e.g. "§6") which match the numbering used across this whole set.

Each module file now includes explicit **Create / Edit / Delete** field lists and list-screen columns (not just a high-level description) — e.g. the exact fields on the "Create Invoice" or "Record Payment" forms.

