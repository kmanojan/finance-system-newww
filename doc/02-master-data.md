# 1. Master Data Module

Every entity below is a standard **List → Create → Edit → Delete** screen set (soft-delete, not hard-delete — see note at bottom). List screens include search, date range filter (on `created_at`), and tag filter where applicable.

## 1.1 Companies
**List columns:** Logo, Name, Base Currency, Departments count, Status (Active/Inactive), Actions (Edit / Delete)
**Create / Edit fields:**
- Name *(required)*
- Logo (image upload)
- Base Currency *(dropdown, required)*
- Registration No.
- Tax ID / VAT No.
- Address
- Phone, Email
- Status (Active/Inactive)
**Delete:** soft-delete only if no departments/transactions linked; otherwise force "Deactivate" instead.

## 1.2 Departments
**List view:** Supports both standard Table view and hierarchical Tree view.
**List columns:** Name, Company, Code, Parent Department, Head/Manager, Status, Actions (Edit / Delete)
**Create / Edit fields:**
- Company *(dropdown, required)*
- Parent Department *(dropdown, optional - for hierarchical departments)*
- Name *(required)*
- Code (short code, e.g. `JOB`, `PLC`)
- Department Head (user dropdown)
- Status (Active/Inactive)
**Delete:** soft-delete; blocked if transactions/projects reference it (show warning, offer deactivate instead).

## 1.3 Income / Expense Categories
**List columns:** Name, Type (Income/Expense), Parent Category, Color/Icon, Status, Actions (Edit / Delete)
**Create / Edit fields:**
- Name *(required)*
- Type *(Income / Expense — required)*
- Parent Category *(dropdown, optional — for sub-categories)*
- Color (color picker, for charts)
- Icon (optional)
- Company scope *(dropdown: Global or specific company)*
- Status (Active/Inactive)
**Delete:** soft-delete only; historical transactions keep referencing the category name as it was.

## 1.4 Invoice Types
**List columns:** Name, Maps To (Income/Expense), Default Category, Default Template, Status, Actions (Edit / Delete)
**Create / Edit fields:**
- Name *(required, e.g. "Change Request Invoice", "Milestone Invoice", "Recurring Invoice")*
- Maps To *(Income / Expense — required)*
- Default Category *(dropdown from §1.3)*
- Default Document Template *(dropdown from §1.5)*
- Status (Active/Inactive)
**Delete:** soft-delete; blocked if invoices exist with this type.

## 1.5 Document Templates (Invoice / Receipt)
**List columns:** Name, Type (Invoice/Receipt), Company, Department, Language, Default (yes/no), Actions (Preview / Edit / Delete / Set as Default)
**Create / Edit fields:**
- Name *(required)*
- Type *(Invoice / Receipt — required)*
- Company *(dropdown, required)*
- Department *(dropdown, optional — blank = applies to all departments in company)*
- Language (e.g., English / Tamil)
- Header Image (upload)
- Footer Image (upload)
- Company Details block (name, address, phone, email, tax ID — can pull from Company but override-able)
- Bank Details block (bank name, account no, branch, SWIFT — for "pay to" section)
- Description / Terms & Conditions (rich text)
- Other Details (repeatable key-value pairs — flexible JSON field)
- Is Default (toggle — one default per company/department/type)
**Delete:** soft-delete; blocked if any invoice was generated using it (invoice keeps a snapshot copy, not a live reference — so old invoices render correctly even after template changes). See §1.5.1.

### 1.5.1 Template versioning note
When an invoice is generated, store a **frozen copy** of the template (header/footer/details) on the invoice record itself, not just a foreign key. This way editing/deleting a template later never changes how already-issued invoices look.

## 1.6 Bank Accounts
**List columns:** Bank Name, Account No. (masked), Company, Department, Currency, Current Balance, Status, Actions (Edit / Delete / View Ledger)
**Create / Edit fields:**
- Bank Name *(required)*
- Account No. *(required)*
- Account Holder Name
- Branch
- Company *(dropdown, required)*
- Department *(dropdown, optional)*
- Currency *(dropdown, required)*
- Opening Balance *(required)*
- Opening Balance Date
- Status (Active/Inactive)
**Delete:** soft-delete only; blocked if transactions reference it — must deactivate instead.
**Extra action:** "View Ledger" — opens the bank account's transaction history (from §2) filtered to this account, with running balance.

## 1.7 Parties (Clients, Vendors, Partners, Lenders)
Unified party master directory (`/master/parties`):
- **List columns:** Name, Type Badges (Client / Vendor / Partner / Lender / Employee), Contact Person, Phone, Email, Currency, Active Projects / Invoices / Facilities count, Status, Actions (View / Edit / Delete).
- **Search & Pagination:** Full text search by name, contact, email, or phone; paginated at 15 parties per page with custom footer navigation (`Showing X to Y of Z parties`).
- **Create / Edit fields:**
  - Name / Business Name *(required)*
  - Party Types *(multi-select: Client, Vendor, Partner, Lender, Employee)*
  - Contact Person, Email, Phone, Secondary Phone
  - Tax / VAT Number, Address
  - Default Currency
  - Opening Balance & Opening Balance Date
  - Partner Share % (if Partner type)
  - Notes & Custom Tags
  - Status (Active/Inactive)
- **Delete:** Soft-delete; blocked if active transactions, invoices, or loan facilities reference the party.
- **View (Detail & Facility Reports):** Comprehensive multi-tab statement tracking all linked projects, invoices, payments, loan facilities, and net exposure. Also accessible via `/loans/party-report` with lazy-loaded facility accordions.

## 1.9 Tags (Tag Manager)
**List columns:** Name, Color, Usage Count (how many records use it), Actions (Edit / Delete)
**Create / Edit fields:**
- Name *(required, unique)*
- Color (color picker)
**Delete:** removes the tag from all records it's attached to (with a confirmation showing usage count first).

---

### General master data rules
- **Edit** is always available except on records referenced by locked/finalized financial documents (e.g., a bank account used in a posted transaction can still be edited for contact info, but currency becomes locked once transactions exist).
- **Delete** is always **soft-delete** (`deleted_at` timestamp) so historical records stay intact. If a record is referenced elsewhere, the UI should block delete and suggest "Deactivate" instead, with a clear message listing what's blocking it.
- All list screens support **search box + date range filter + tag filter + status filter**, per the cross-cutting requirements (§0).
