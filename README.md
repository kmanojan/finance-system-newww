# 💼 Apptimus Finance Management System

A robust, multi-entity financial management system designed for **Apptimus** and its sub-entities. Built with **Laravel 12**, **PostgreSQL / Supabase**, and modern **Blade + Alpine.js** UI with progressive web app (PWA) capabilities.

Start with the [current full feature guide](doc/35-implemented-features.md) for implemented modules, operating workflows, permissions and known limits. The [route reference](doc/36-route-reference.md) lists declared endpoints. Older module specifications include roadmap ideas and are not an implementation checklist.

---

## 🚀 Key Modules & Capabilities

- **📊 Executive Dashboard**: Real-time KPI summary, revenue vs expense trends, cash balance breakdown, and milestone alerts.
- **💼 Projects & Milestone Billing**: Track deliverables, client payment milestones, budget limits, and profitability.
- **📄 Invoicing & Payments**: Client invoices, PDF generation, partial payments, and overdue tracking.
- **💳 Double-Entry Ledger**: Unified transactions (Income, Expense, Petty Cash, Transfers) with tags and department allocation.
- **🏦 Third-Party Loans (Borrowing)**:
  - Supports **Fixed Amount**, **Percentage Rate**, **Equal Installments**, and **Upfront Interest Deduction**.
  - Automatically posts single net income transactions on disbursement.
  - Principal maturity due dates (`maturity_date`) with automatic lead-time reminders.
  - Per-schedule date and amount editing with thousand-separator inputs.
- **📊 Budgets & Cost Allocation**: Period-based allocations with real-time actual vs budget tracking.
- **🔔 Unified Reminders**: Centralized reminder engine for loan interest, milestones, invoices, and cheques.
- **📈 Financial Reports**: Profit & Loss, Balance Sheet, Cash Flow, aging, project/party statements, and dated auditor payables with PDF/Excel exports.
- **📱 PWA & Mobile Ergonomics**: Native-like bottom navigation bar, safe-area support, dark/light theme, and offline service worker.
- **Employees & Monthly Payroll**: Salary components, monthly review/approval, partial salary releases, statutory preparation/remittance tracking, and a searchable salary advance register with scheduled recovery and protected historical source links.

---

## 🛠️ Tech Stack & Architecture

- **Backend**: Laravel 12.x (PHP 8.2+)
- **Database**: PostgreSQL (Supabase) / MySQL / SQLite
- **Frontend**: Laravel Blade, Vanilla CSS Tokens, Alpine.js, Chart.js, Ionicons
- **Deployment**: Vercel Serverless & Docker support

---

## ⚙️ Quick Start & Setup

### 1. Clone & Install Dependencies
```bash
git clone <repository-url>
cd "Finance System"
composer install
npm install && npm run build
```

### 2. Environment Configuration
Copy the `.env.example` file and configure your database credentials (e.g. Supabase PostgreSQL):
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Migration
Run database migrations and seeders:
```bash
php artisan migrate
```

> **For Supabase / Vercel Production**:
> Apply migrations through the authorized deployment process or the reviewed SQL in `database/releases/`. Browser migration endpoints have been removed. Follow the [deployment guide](doc/29-deployment-and-serverless.md) and release-specific instructions.

### 4. Run Development Server
```bash
php artisan serve
```
Visit `http://localhost:8000` in your browser.

---

## 🎨 UI/UX & Design System Highlights

- **Theme Adaptive Badges**: Semantic alpha badges (`.badge-success`, `.badge-danger`, `.badge-warning`, `.badge-info`, `.badge-primary`) looking clean in both Light and Dark modes.
- **Tabular Numerals**: Numeric tabular formatting (`.tabular-nums`, `.amount-cell`) for aligned currency numbers.
- **Micro-interactions**:
  - Floating Toast notification system (`window.showToast()`).
  - Automated double-submission protection with button loading spinners.
  - Themed `<x-confirm-modal>` replacing browser `confirm()` popups.
  - `<x-empty-state>` cards for empty tables.
  - `<x-amount-input>` live thousand-separator formatting.

---

## 📚 Detailed Documentation

Comprehensive specifications for each module are located in the [`doc/`](doc/00-README.md) directory:

- [`01-cross-cutting-requirements.md`](doc/01-cross-cutting-requirements.md) — Cross-cutting features
- [`02-master-data.md`](doc/02-master-data.md) — Categories, Departments, Bank Accounts
- [`03-daily-income-expense.md`](doc/03-daily-income-expense.md) — Routine Transactions & Petty Cash
- [`04-project-module.md`](doc/04-project-module.md) — Projects, Milestones & Timesheets
- [`05-all-invoices-page.md`](doc/05-all-invoices-page.md) — Invoices & Receivables
- [`06-reminders-page.md`](doc/06-reminders-page.md) — Reminders Engine
- [`07-budget-module.md`](doc/07-budget-module.md) — Budgets & Allocation
- [`08-loans-third-party.md`](doc/08-loans-third-party.md) — Loans & Upfront Interest
- [`09-client-partner-sharing.md`](doc/09-client-partner-sharing.md) — Secure Share Links
- [`10-reports-module.md`](doc/10-reports-module.md) — Financial Reports
- [`28-pwa-session-security.md`](doc/28-pwa-session-security.md) — PWA & Session Architecture
- [`29-ui-ux-design-system.md`](doc/29-ui-ux-design-system.md) — UI/UX Tokens & Blade Components
- [`30-salary_components_and_payroll.md`](doc/30-salary_components_and_payroll.md) — Employee Compensation & Salary Components
- [`32-monthly-payroll.md`](doc/32-monthly-payroll.md) — Payroll Approval, Payments & Salary Advance Register
- [`34-salary-advances-release-2026-11-08.md`](doc/34-salary-advances-release-2026-11-08.md) — Historical Advance Reconciliation & Register Redesign Release
- [`35-implemented-features.md`](doc/35-implemented-features.md) — Full Current Feature Guide, Permissions & Known Limits
- [`36-route-reference.md`](doc/36-route-reference.md) — Complete Declared Application Route Catalogue
- [`auditor-payables.md`](docs/auditor-payables.md) — Auditor Payables Report & Recognition Basis

---

## 📄 License
Proprietary software for Apptimus. All rights reserved.
