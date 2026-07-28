# Accounts Payable (AP) & Purchase Order Module — Full Specification

*Specification for tracking Purchase Orders, Vendor Bills, Vendor Debit/Credit Notes, and Accounts Payable Aging.*

---

## 1. Overview

While Accounts Receivable (Invoices to Clients) tracks incoming revenue, **Accounts Payable (AP)** tracks liability to suppliers, software providers, and contractors. Direct cash expenses lack purchase order approval and payment scheduling.

---

## 2. Database Schema

```sql
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    po_number VARCHAR(100) NOT NULL UNIQUE,
    vendor_id INT NOT NULL,
    department_id INT NOT NULL,
    status VARCHAR(50) DEFAULT 'draft', -- 'draft', 'pending_approval', 'approved', 'billed', 'cancelled'
    total_amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    issue_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES parties(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

CREATE TABLE IF NOT EXISTS vendor_bills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bill_number VARCHAR(100) NOT NULL,
    vendor_id INT NOT NULL,
    po_id INT NULL,
    department_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    status VARCHAR(50) DEFAULT 'unpaid', -- 'unpaid', 'partially_paid', 'paid', 'overdue'
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES parties(id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id)
);
```

---

## 3. Key Workflows

1. **PO Creation & Approval**: Purchase orders above predefined threshold require manager approval.
2. **Bill Recording**: Vendor bill matched against approved PO (2-way / 3-way match).
3. **AP Aging Report**: Reports total payables grouped by aging brackets: Current, 1–30 Days, 31–60 Days, 61–90 Days, Over 90 Days.
