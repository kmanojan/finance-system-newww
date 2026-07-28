# Bank Reconciliation & Statement Import — Full Specification

*Specification for bank statement parser (CSV/OFX), automated transaction matching, and Bank Reconciliation Statement (BRS) reporting.*

---

## 1. Overview

Ensures the Cash/Bank balances in the internal General Ledger match actual cleared balances reported by the bank.

---

## 2. Key Features

1. **Statement Parser**: Supports CSV and OFX/QFX uploads from major local and international banks.
2. **Auto-Match Rules Engine**: Automatically matches statement lines against system `transactions` or `payments` by matching exact amount, reference code, and date within a tolerance window (±3 days).
3. **Reconciliation Summary (BRS)**:
   - Balance per Company Ledger
   - **+** Unpresented Cheques / Outstanding Payments
   - **-** Undeposited Receipts / Outstanding Deposits
   - **=** Reconciled Balance per Bank Statement
