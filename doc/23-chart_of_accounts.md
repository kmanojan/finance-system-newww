# Chart of Accounts (CoA) & Automated GL Posting — Full Specification

*Specification for structured double-entry Chart of Accounts (CoA) master and automated sub-ledger General Ledger (GL) posting engine.*

---

## 1. Overview

Currently, the double-entry journal system (`journal_entry_lines`) uses free-text account names (`account_name`). This specification replaces text fields with a structured **Chart of Accounts (CoA)** hierarchy and connects operational events (Invoices, Payments, Vendor Bills) to automated GL debit and credit postings.

---

## 2. Standard Account Structure

Accounts follow standard accounting numbering:
- **1000 – 1999**: Assets (Cash, Bank accounts, Accounts Receivable, Inventory, Prepaid Expenses)
- **2000 – 2999**: Liabilities (Accounts Payable, Credit Cards, Accruals, Loans Payable)
- **3000 – 3999**: Equity (Share Capital, Retained Earnings, Owner Draw)
- **4000 – 4999**: Revenue (Service Revenue, Software Sales, Export Revenue, Interest Income)
- **5000 – 5999**: Expenses (Salaries, Rent, Hosting, Office Expenses, Bank Fees, Depreciation)

---

## 3. Database Schema

```sql
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INT NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'asset', 'liability', 'equity', 'revenue', 'expense'
    parent_id INT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (parent_id) REFERENCES accounts(id)
);
```

---

## 4. Automated Posting Mapping

| Operational Trigger | Debit Account | Credit Account |
|---|---|---|
| **Sales Invoice Issued** | Accounts Receivable (`1200`) | Sales Revenue (`4100`) |
| **Client Payment Received** | Bank / Cash (`1010`) | Accounts Receivable (`1200`) |
| **Vendor Bill Approved** | Expense / Asset (`5000`) | Accounts Payable (`2100`) |
| **Vendor Bill Paid** | Accounts Payable (`2100`) | Bank / Cash (`1010`) |
| **Loan Disbursed** | Bank Account (`1010`) | Loan Liability (`2200`) |
