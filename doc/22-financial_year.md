# Financial Year & Fiscal Period Management — Full Specification

*Specification for managing company Fiscal Years, monthly/quarterly Period Locking, and Year-End Closing journal entries.*

---

## 1. Overview & Business Rationale

A financial system cannot function reliably without time boundary controls. Transactions posted to closed accounting periods corrupt tax filings, financial audits, and historical balance sheets.

This module introduces:
1. **Financial Years Master**: Defining start/end dates for fiscal years (e.g. Apr 1 to Mar 31 or Jan 1 to Dec 31).
2. **Fiscal Period Locking**: Locking monthly or quarterly periods (`open`, `soft_closed`, `hard_closed`) to prevent retro-active posting or editing.
3. **Year-End Closing Engine**: Automated closing procedure that transfers income and expense balances into Retained Earnings.

---

## 2. Where it lives

- **Master Data / System Settings** → `Financial Years` tab (`/settings/financial-years`).
- **Middleware / Validation** → Strict check on `TransactionController`, `InvoiceController`, `JournalEntryController`, and `PaymentController`.

---

## 3. Database Schema

```sql
CREATE TABLE IF NOT EXISTS financial_years (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INT NOT NULL,
    title VARCHAR(100) NOT NULL, -- e.g., "FY 2025/2026"
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_closed BOOLEAN DEFAULT 0,
    closed_at DATETIME NULL,
    closed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE IF NOT EXISTS fiscal_periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    financial_year_id INT NOT NULL,
    period_name VARCHAR(50) NOT NULL, -- e.g., "April 2025", "Q1 2025"
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'open', -- 'open', 'soft_closed', 'hard_closed'
    closed_at DATETIME NULL,
    closed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE
);
```

---

## 4. Key Rules & Workflows

### 4.1 Period Lock Enforcement
When any financial entity (Invoice, Expense Transaction, Payment, Journal Entry) is created, updated, or deleted, the system evaluates:
```php
$period = FiscalPeriod::where('start_date', '<=', $date)
    ->where('end_date', '>=', $date)
    ->first();

if ($period && $period->status === 'hard_closed') {
    throw new Exception("Period '{$period->period_name}' is hard closed. Transactions cannot be posted or altered.");
}
```

### 4.2 Year-End Close Wizard
1. Verifies all 12 monthly periods in the financial year are closed.
2. Sums total Revenue and total Expense account balances for the year.
3. Generates a Year-End Closing Journal Entry:
   - Debit Revenue accounts (clearing balance to 0).
   - Credit Expense accounts (clearing balance to 0).
   - Post Net Difference (Net Profit/Loss) to **Retained Earnings (Equity)**.
4. Sets `financial_years.is_closed = 1`.
