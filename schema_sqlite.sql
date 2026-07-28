


-- 1. Master Data Module
CREATE TABLE IF NOT EXISTS exchange_rates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    currency_code VARCHAR(10) NOT NULL,
    rate_to_base DECIMAL(15, 6) NOT NULL,
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    logo_url VARCHAR(255),
    base_currency VARCHAR(10) DEFAULT 'LKR',
    registration_details TEXT,
    tax_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (parent_id) REFERENCES departments(id)
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    company_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id),
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE IF NOT EXISTS invoice_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    maps_to VARCHAR(255) NOT NULL,
    default_category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (default_category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS document_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    header_image_url VARCHAR(255),
    footer_image_url VARCHAR(255),
    background_image_url VARCHAR(255),
    description TEXT,
    company_details TEXT,
    bank_details TEXT,
    terms_conditions TEXT,
    other_details JSON,
    is_default BOOLEAN DEFAULT FALSE,
    language VARCHAR(50) DEFAULT 'English',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    bank_name VARCHAR(255) NOT NULL,
    account_no VARCHAR(255) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    opening_balance DECIMAL(15, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

CREATE TABLE IF NOT EXISTS parties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    types VARCHAR(255) NOT NULL, -- comma-separated: 'client', 'partner', 'vendor'
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    tax_id VARCHAR(100),
    default_commission_type VARCHAR(50), -- 'percentage', 'fixed'
    default_commission_value DECIMAL(15, 2),
    notes TEXT,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL
);

-- Cross-cutting Tags
CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(50) DEFAULT '#5243E8',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS taggables (
    tag_id INT NOT NULL,
    taggable_id INT NOT NULL,
    taggable_type VARCHAR(255) NOT NULL,
    PRIMARY KEY (tag_id, taggable_id, taggable_type),
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Project Module
CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(255) DEFAULT 'active',
    over_budget_flag BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE IF NOT EXISTS project_party (
    project_id INT NOT NULL,
    party_id INT NOT NULL,
    role VARCHAR(50) NOT NULL, -- 'client', 'partner', etc.
    share_percentage DECIMAL(5, 2) DEFAULT NULL,
    PRIMARY KEY (project_id, party_id, role),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payment_milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_commissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INT NOT NULL,
    party_id INT NOT NULL,
    commission_type VARCHAR(50) NOT NULL, -- 'percentage', 'fixed'
    percentage_value DECIMAL(5, 2) DEFAULT NULL,
    calculation_basis VARCHAR(100) DEFAULT NULL, -- 'invoiced', 'collected', 'budget'
    fixed_amount DECIMAL(15, 2) DEFAULT NULL,
    currency VARCHAR(10) DEFAULT NULL,
    trigger_type VARCHAR(100) DEFAULT NULL, -- 'start', 'invoice', 'milestone', 'manual'
    effective_from DATE NOT NULL,
    effective_to DATE DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'active', -- 'active', 'paused', 'ended'
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS commission_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_commission_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_mode VARCHAR(50) NOT NULL, -- 'cash', 'card', 'cheque', 'bank_transfer'
    bank_account_id INT DEFAULT NULL,
    reference_no VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (project_commission_id) REFERENCES project_commissions(id) ON DELETE CASCADE,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
);

-- Daily Transactions & Budgets
CREATE TABLE IF NOT EXISTS budgets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    scope_type VARCHAR(255), -- 'department', 'project', 'tag'
    scope_id INT,
    allocated_amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    period VARCHAR(255) NOT NULL,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
);

CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    department_id INT NOT NULL,
    bank_account_id INT DEFAULT NULL, -- NULL means petty cash
    payment_method VARCHAR(50) DEFAULT 'Normal',
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT,
    reference_no VARCHAR(255),
    reconciled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
);

CREATE TABLE IF NOT EXISTS budget_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    budget_id INT NOT NULL,
    budget_item_id INT DEFAULT NULL,
    transaction_id INT NOT NULL,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

-- Invoices & Payments
CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no VARCHAR(255) NOT NULL UNIQUE,
    client_id INT NOT NULL,
    project_id INT DEFAULT NULL,
    department_id INT NOT NULL,
    template_id INT DEFAULT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    subtotal DECIMAL(15, 2) DEFAULT 0,
    advance_paid DECIMAL(15, 2) DEFAULT 0,
    grand_total DECIMAL(15, 2) DEFAULT 0,
    currency VARCHAR(10) NOT NULL,
    status VARCHAR(255) DEFAULT 'draft',
    signee_name VARCHAR(255) DEFAULT NULL,
    signee_title VARCHAR(255) DEFAULT NULL,
    signature_image VARCHAR(255) DEFAULT NULL,
    template_snapshot JSON DEFAULT NULL,
    due_date DATE,
    issue_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (client_id) REFERENCES parties(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (template_id) REFERENCES document_templates(id)
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INT NOT NULL,
    invoice_type_id INT NOT NULL,
    description TEXT NOT NULL,
    qty DECIMAL(10, 2) DEFAULT 1,
    unit_price DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    tax_percentage DECIMAL(5, 2) DEFAULT 0,
    total DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_type_id) REFERENCES invoice_types(id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INT DEFAULT NULL, -- Kept for backwards compatibility / legacy
    project_id INT DEFAULT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS payment_allocations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INT NOT NULL,
    invoice_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

CREATE TABLE IF NOT EXISTS payment_modes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INT NOT NULL,
    mode VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    bank_name VARCHAR(255),
    cheque_no VARCHAR(255),
    cheque_date DATE,
    cheque_status VARCHAR(255) DEFAULT NULL,
    reference_no VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS change_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INT NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(15, 2),
    currency VARCHAR(10),
    status VARCHAR(255) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    notable_id INT NOT NULL,
    notable_type VARCHAR(255) NOT NULL, -- e.g. 'project', 'client'
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS interactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    interactionable_id INT NOT NULL,
    interactionable_type VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    interaction_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Loans
CREATE TABLE IF NOT EXISTS loans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lender_name VARCHAR(255) NOT NULL,
    principal_amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    purpose TEXT,
    claimed_date DATE,
    term_months INT,
    interest_method VARCHAR(50),
    interest_amount DECIMAL(15, 2),
    interest_rate DECIMAL(5, 2),
    rate_basis VARCHAR(50),
    total_interest DECIMAL(15, 2),
    due_day INT,
    frequency VARCHAR(50),
    guarantor VARCHAR(255),
    collateral TEXT,
    status VARCHAR(255) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
);

CREATE TABLE IF NOT EXISTS loan_interest_schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    loan_id INT NOT NULL,
    due_date DATE NOT NULL,
    interest_amount DECIMAL(15, 2) NOT NULL,
    paid_amount DECIMAL(15, 2) DEFAULT 0.00,
    paid_date DATE,
    status VARCHAR(255) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS loan_principal_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    loan_id INT NOT NULL,
    record_type VARCHAR(50) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    record_date DATE,
    payment_mode VARCHAR(50),
    reference_no VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

-- Unified Reminders
CREATE TABLE IF NOT EXISTS reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type VARCHAR(50) NOT NULL, -- 'cheque', 'invoice', 'loan_interest', 'custom', 'budget_alert'
    reference_id INT NOT NULL,
    reference_type VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    notify_before_days INT DEFAULT 0,
    status VARCHAR(255) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
);

-- Share Links
CREATE TABLE IF NOT EXISTS share_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token VARCHAR(64) NOT NULL UNIQUE,
    shareable_id INT NOT NULL,
    shareable_type VARCHAR(255) NOT NULL, -- 'project', 'partner'
    audience VARCHAR(255) NOT NULL,
    expires_at DATETIME DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    allow_downloads TINYINT(1) DEFAULT 1,
    notify_on_view TINYINT(1) DEFAULT 0,
    revoked_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS share_link_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    share_link_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (share_link_id) REFERENCES share_links(id) ON DELETE CASCADE
);

-- Polymorphic Attachments & Audit Logs
CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    model_id INT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    model_id INT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    action VARCHAR(255) NOT NULL,
    old_value JSON,
    new_value JSON,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Double Entry Ledger
CREATE TABLE IF NOT EXISTS journal_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference_id INT DEFAULT NULL,
    reference_type VARCHAR(255) DEFAULT NULL,
    entry_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS journal_entry_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journal_entry_id INT NOT NULL,
    account_name VARCHAR(255) NOT NULL, -- e.g., 'Cash', 'Accounts Receivable'
    account_type VARCHAR(255) NOT NULL,
    debit DECIMAL(15, 2) DEFAULT 0,
    credit DECIMAL(15, 2) DEFAULT 0,
    currency VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE
);

-- Users (Simple)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'viewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Master Currencies
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

-- Currency Exchange Rates History
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

-- Unified Reminders Table
CREATE TABLE IF NOT EXISTS reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT 'custom',
    due_date DATE NOT NULL,
    notify_before_days INT DEFAULT 2,
    linked_type VARCHAR(100) DEFAULT NULL,
    linked_id INT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cheques Table
CREATE TABLE IF NOT EXISTS cheques (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INT DEFAULT NULL,
    cheque_number VARCHAR(100) NOT NULL,
    cheque_date DATE NOT NULL,
    bank_name VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'LKR',
    status VARCHAR(50) DEFAULT 'pending_deposit',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


