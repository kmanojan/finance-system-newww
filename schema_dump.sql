CREATE TABLE IF NOT EXISTS "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);
CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE IF NOT EXISTS "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime);
CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));
CREATE TABLE IF NOT EXISTS "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));
CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");
CREATE TABLE IF NOT EXISTS "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));
CREATE INDEX "cache_expiration_index" on "cache" ("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));
CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");
CREATE TABLE IF NOT EXISTS "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);
CREATE INDEX "jobs_queue_index" on "jobs" ("queue");
CREATE TABLE IF NOT EXISTS "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));
CREATE TABLE IF NOT EXISTS "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" varchar not null, "queue" varchar not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs" ("connection", "queue", "failed_at");
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");
CREATE TABLE exchange_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    currency_code VARCHAR(10) NOT NULL,
    rate_to_base DECIMAL(15, 6) NOT NULL,
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(50) DEFAULT '#5243E8',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE taggables (
    tag_id INT NOT NULL,
    taggable_id INT NOT NULL,
    taggable_type VARCHAR(255) NOT NULL,
    PRIMARY KEY (tag_id, taggable_id, taggable_type),
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);
CREATE TABLE project_party (
    project_id INT NOT NULL,
    party_id INT NOT NULL,
    role VARCHAR(50) NOT NULL, -- 'client', 'partner', etc.
    share_percentage DECIMAL(5, 2) DEFAULT NULL,
    PRIMARY KEY (project_id, party_id, role),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE CASCADE
);
CREATE TABLE budget_transactions (
    budget_id INT NOT NULL,
    transaction_id INT NOT NULL,
    PRIMARY KEY (budget_id, transaction_id),
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT DEFAULT NULL, -- Kept for backwards compatibility / legacy
    project_id INT DEFAULT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (project_id) REFERENCES projects(id)
);
CREATE TABLE payment_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    invoice_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notable_id INT NOT NULL,
    notable_type VARCHAR(255) NOT NULL, -- e.g. 'project', 'client'
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE share_link_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    share_link_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (share_link_id) REFERENCES share_links(id) ON DELETE CASCADE
);
CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_id INT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_id INT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    action VARCHAR(255) NOT NULL,
    old_value JSON,
    new_value JSON,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_id INT DEFAULT NULL,
    reference_type VARCHAR(255) DEFAULT NULL,
    entry_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE companies (
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
CREATE TABLE departments (
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
CREATE TABLE categories (
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
CREATE TABLE invoice_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    maps_to VARCHAR(255) NOT NULL,
    default_category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (default_category_id) REFERENCES categories(id)
);
CREATE TABLE document_templates (
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
CREATE TABLE bank_accounts (
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
CREATE TABLE parties (
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
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(255) DEFAULT 'active',
    over_budget_flag BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP , department_id INT, currency VARCHAR(10) DEFAULT 'LKR', start_date DATE, end_date DATE, budget_limit DECIMAL(15,2) DEFAULT 0.00,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);
CREATE TABLE project_commissions (
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
CREATE TABLE commission_payments (
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
CREATE TABLE budgets (
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
CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    department_id INT NOT NULL,
    bank_account_id INT DEFAULT NULL, -- NULL means petty cash
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
CREATE TABLE invoices (
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP , schedule_id INTEGER DEFAULT NULL,
    FOREIGN KEY (client_id) REFERENCES parties(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (template_id) REFERENCES document_templates(id)
);
CREATE TABLE payment_modes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INT NOT NULL,
    mode VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    cheque_no VARCHAR(255),
    cheque_date DATE,
    cheque_status VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);
CREATE TABLE change_requests (
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
CREATE TABLE interactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    interactionable_id INT NOT NULL,
    interactionable_type VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    interaction_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE loans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lender_name VARCHAR(255) NOT NULL,
    principal_amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    purpose TEXT,
    claimed_date DATE,
    term_months INT,
    status VARCHAR(255) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
);
CREATE TABLE loan_interest_schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    loan_id INT NOT NULL,
    due_date DATE NOT NULL,
    interest_amount DECIMAL(15, 2) NOT NULL,
    status VARCHAR(255) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);
CREATE TABLE reminders (
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
CREATE TABLE share_links (
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
CREATE TABLE journal_entry_lines (
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
CREATE TABLE timesheets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    task_description TEXT NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    logged_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
CREATE TABLE invoice_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NULL,
    frequency VARCHAR(50) DEFAULT 'monthly',
    custom_interval_days INTEGER NULL,
    generate_day INTEGER NULL,
    next_generation_date DATE NULL,
    invoice_type_id INTEGER NULL,
    currency VARCHAR(10) DEFAULT 'LKR',
    template_id INTEGER NULL,
    notes TEXT NULL,
    require_approval BOOLEAN DEFAULT 0,
    auto_adjust_holidays BOOLEAN DEFAULT 0,
    notify_on_generation BOOLEAN DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
CREATE TABLE invoice_schedule_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    schedule_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(15,2) DEFAULT 0.00,
    tax_percentage DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES invoice_schedules(id) ON DELETE CASCADE
);
