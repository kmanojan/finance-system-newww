# Currencies & Exchange Rate History

Lets you manage system **Master Currencies**, configure the **Base System Currency** (default `LKR`), track day-by-day **Exchange Rate History** via a free currency conversion API, and select currencies across the system using a unified `<x-currency-selector>` component.

---

## 1. Where it lives

- **Master Data** → a dedicated "Currencies" tab (`/master/currencies`), listing all active currencies, exchange rates relative to the base currency, and an instant **"Sync Exchange Rates Now"** action.
- **Settings / Configuration** → System Default Base Currency selection (`/profile?tab=config`), which sets the main reference currency for financial consolidation.
- **Global Selector Component** → `<x-currency-selector>` used across Projects, Invoices, Transactions, Bank Accounts, Loans, and Budgets.

---

## 2. Master Currencies Screen (`/master/currencies`)

**Columns:** Code (e.g. `LKR`, `USD`, `EUR`), Name (e.g. `Sri Lankan Rupee`), Symbol (e.g. `Rs`, `$`, `€`), Current Exchange Rate (relative to Base Currency), Rate Date / Last Synced, Status (Active/Inactive), Base Currency Badge, Actions (View History / Edit / Sync).

**Default Seed Currencies (10 Main Currencies):**
1. **LKR** — Sri Lankan Rupee (`Rs`) [Base Currency]
2. **USD** — US Dollar (`$`)
3. **EUR** — Euro (`€`)
4. **AUD** — Australian Dollar (`A$`)
5. **AED** — UAE Dirham (`AED`)
6. **INR** — Indian Rupee (`₹`)
7. **GBP** — British Pound (`£`)
8. **CAD** — Canadian Dollar (`C$`)
9. **SGD** — Singapore Dollar (`S$`)
10. **JPY** — Japanese Yen (`¥`)

---

## 3. Daily Exchange Rate History & Free API Integration

The system automatically syncs exchange rates relative to the base currency using free open exchange rate endpoints (e.g., `https://open.er-api.com/v6/latest/{base_currency}` or `https://api.exchangerate-api.com/v4/latest/{base_currency}`).

### 3.1 Exchange Rate Sync Logic
- Fetches real-time conversion rates relative to the active Base Currency (e.g. `1 LKR = X USD` or `1 USD = Y LKR`).
- Records each day's conversion rate into the `currency_exchange_rates` history table.
- Stores historical snapshots (`base_currency`, `target_currency`, `rate`, `rate_date`, `source`).

---

## 4. Data Model & Database Schema

```sql
CREATE TABLE IF NOT EXISTS currencies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(3) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(10) DEFAULT '$',
    is_active BOOLEAN DEFAULT 1,
    is_base BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS currency_exchange_rates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    base_currency VARCHAR(3) NOT NULL,
    target_currency VARCHAR(3) NOT NULL,
    rate DECIMAL(18, 6) NOT NULL,
    rate_date DATE NOT NULL,
    source VARCHAR(50) DEFAULT 'api',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 5. Currency Selector Component Specification (`<x-currency-selector>`)

**Component Name:** `<x-currency-selector>`
**Props:**
- `name` *(required, default 'currency')*
- `selected` *(optional, defaults to system base currency `LKR`)*
- `id` *(optional)*
- `class` *(optional)*
- `onchange` *(optional)*

**UI & Theme Features:**
- Seamless Light & Dark mode support using system design tokens (`var(--bg-card)`, `var(--text-heading)`, `var(--primary)`, etc.).
- Modal / dropdown with instant real-time search across Code (`USD`), Name (`US Dollar`), and Symbol (`$`).
- Displays currency badge and symbol on the trigger button.
- Active item highlight with checkmark indicator.

---

## 6. System Integration Points

1. **Projects:** Create & Edit Project forms use `<x-currency-selector>`.
2. **Invoices:** Invoice Creation modal uses `<x-currency-selector>`.
3. **Transactions:** Daily Income & Expense Transaction modal uses `<x-currency-selector>`.
4. **Bank Accounts:** Create & Edit Bank Account modal uses `<x-currency-selector>`.
5. **Loans:** Third-Party Loans creation modal uses `<x-currency-selector>`.
6. **Budgets:** Master Budget creation modal uses `<x-currency-selector>`.
