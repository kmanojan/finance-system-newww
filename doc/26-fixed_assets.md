# Fixed Asset Register & Depreciation Management — Full Specification

*Specification for fixed asset capitalization, tracking, valuation, and automated monthly/yearly depreciation schedule generation.*

---

## 1. Overview

Tracks non-current capital assets (Computers, Hardware, Office Furniture, Vehicles, Servers) and generates automated depreciation postings to the General Ledger.

---

## 2. Database Schema

```sql
CREATE TABLE IF NOT EXISTS fixed_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_name VARCHAR(255) NOT NULL,
    asset_code VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL, -- 'computers', 'furniture', 'vehicles', 'machinery'
    purchase_date DATE NOT NULL,
    purchase_cost DECIMAL(15, 2) NOT NULL,
    salvage_value DECIMAL(15, 2) DEFAULT 0.00,
    lifespan_years INT NOT NULL,
    depreciation_method VARCHAR(50) DEFAULT 'straight_line', -- 'straight_line', 'reducing_balance'
    accumulated_depreciation DECIMAL(15, 2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'active', -- 'active', 'fully_depreciated', 'disposed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 3. Depreciation Schedules

Generates a monthly journal entry:
- **Debit**: Depreciation Expense (`5800`)
- **Credit**: Accumulated Depreciation — Asset Class (`1800`)
