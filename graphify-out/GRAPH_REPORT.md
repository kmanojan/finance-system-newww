# Graph Report - .  (2026-07-30)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 718 nodes · 1039 edges · 158 communities (119 shown, 39 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 44 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `3f82a3c6`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\Http\Request
- composer.json
- SyncEmployeesJob
- TaxType
- scripts
- env
- LoanController
- TransactionController
- devDependencies
- Illuminate\Database\Seeder
- ActivityLogService
- PurchaseOrder
- CurrencyController
- FinancialYear
- ShareLinkController
- VendorBillController.php
- FixedAsset
- JournalEntry
- Account
- Illuminate\Database\Eloquent\Model
- Illuminate\Database\Eloquent\Relations\HasMany
- InvoiceController
- BudgetController
- ChequeController
- DocumentTemplateController
- Illuminate\Database\Eloquent\Relations\BelongsTo
- ReminderController
- BankAccountController
- web.php
- CategoryController
- Controller
- DepartmentController
- InvoiceTypeController
- JournalEntryController
- PartyController
- ProjectDocumentController
- ServerController
- TagController
- AppServiceProvider
- TestCase
- ActivityLogController
- ExampleTest
- activity-logs.blade.php
- approvals.blade.php
- cheques.blade.php
- bank_accounts.blade.php
- categories.blade.php
- currencies.blade.php
- departments.blade.php
- document_templates.blade.php
- invoice_types.blade.php
- parties.blade.php
- servers.blade.php
- tags.blade.php
- tax_types.blade.php
- reminders.blade.php

## God Nodes (most connected - your core abstractions)
1. `Controller` - 36 edges
2. `ProjectController` - 35 edges
3. `LoanController` - 22 edges
4. `ReportController` - 22 edges
5. `env` - 14 edges
6. `TaxType` - 12 edges
7. `ShareLinkController` - 11 edges
8. `CostAllocation` - 11 edges
9. `SyncEmployeesJob` - 10 edges
10. `CurrencyController` - 9 edges

## Surprising Connections (you probably didn't know these)
- `AccountController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/AccountController.php → app/Http/Controllers/Controller.php
- `ActivityLogController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/ActivityLogController.php → app/Http/Controllers/Controller.php
- `ApiIntegrationController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/ApiIntegrationController.php → app/Http/Controllers/Controller.php
- `ApprovalController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/ApprovalController.php → app/Http/Controllers/Controller.php
- `AuthController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/AuthController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (158 total, 39 thin omitted)

### Community 0 - "Illuminate\Http\Request"
Cohesion: 0.08
Nodes (4): ApprovalController, ProjectController, ReportController, Illuminate\Http\Request

### Community 1 - "composer.json"
Cohesion: 0.05
Nodes (43): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+35 more)

### Community 2 - "SyncEmployeesJob"
Cohesion: 0.09
Nodes (17): ApiIntegrationController, EmployeeController, SyncEmployeesJob, ApiIntegration, Employee, User, UserFactory, Illuminate\Bus\Queueable (+9 more)

### Community 3 - "TaxType"
Cohesion: 0.11
Nodes (6): CostAllocationController, TaxTypeController, CostAllocation, TaxType, TaxCalculator, Illuminate\Database\Eloquent\SoftDeletes

### Community 4 - "scripts"
Cohesion: 0.08
Nodes (26): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+18 more)

### Community 5 - "env"
Cohesion: 0.08
Nodes (24): sin1, runtime, buildCommand, env, APP_CONFIG_CACHE, APP_DEBUG, APP_ENV, APP_EVENTS_CACHE (+16 more)

### Community 7 - "TransactionController"
Cohesion: 0.13
Nodes (5): BankReconciliationController, TransactionController, BankStatementImport, Transaction, BankReconciliationService

### Community 8 - "devDependencies"
Cohesion: 0.11
Nodes (17): concurrently, laravel-vite-plugin, devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite (+9 more)

### Community 9 - "Illuminate\Database\Seeder"
Cohesion: 0.19
Nodes (7): Company, AccountSeeder, DatabaseSeeder, FinancialYearSeeder, TaxTypeSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 11 - "PurchaseOrder"
Cohesion: 0.21
Nodes (3): PurchaseOrderController, PurchaseOrder, PeriodLockService

### Community 13 - "FinancialYear"
Cohesion: 0.21
Nodes (3): FinancialYearController, FinancialYear, FiscalPeriod

### Community 15 - "VendorBillController.php"
Cohesion: 0.24
Nodes (3): VendorBillController, Department, VendorBill

### Community 16 - "FixedAsset"
Cohesion: 0.33
Nodes (3): FixedAssetController, FixedAsset, DepreciationService

### Community 17 - "JournalEntry"
Cohesion: 0.24
Nodes (3): JournalEntry, JournalEntryLine, LedgerPostingService

### Community 19 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.33
Nodes (5): CurrencyExchangeRate, Project, ProjectDocument, Server, Illuminate\Database\Eloquent\Model

### Community 20 - "Illuminate\Database\Eloquent\Relations\HasMany"
Cohesion: 0.28
Nodes (3): Loan, Party, Illuminate\Database\Eloquent\Relations\HasMany

### Community 39 - "TestCase"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Testing\TestCase, ExampleTest, TestCase

## Knowledge Gaps
- **91 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+86 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **39 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Controller` connect `Controller` to `Illuminate\Http\Request`, `SyncEmployeesJob`, `TaxType`, `LoanController`, `TransactionController`, `ActivityLogService`, `PurchaseOrder`, `CurrencyController`, `FinancialYear`, `ShareLinkController`, `VendorBillController.php`, `FixedAsset`, `Account`, `InvoiceController`, `BudgetController`, `ChequeController`, `DocumentTemplateController`, `ReminderController`, `BankAccountController`, `web.php`, `CategoryController`, `DepartmentController`, `InvoiceTypeController`, `JournalEntryController`, `PartyController`, `ProjectDocumentController`, `ServerController`, `TagController`, `ActivityLogController`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._
- **Why does `TaxType` connect `TaxType` to `Illuminate\Database\Seeder`, `Illuminate\Database\Eloquent\Model`?**
  _High betweenness centrality (0.012) - this node is a cross-community bridge._
- **Why does `CostAllocation` connect `TaxType` to `SyncEmployeesJob`, `Illuminate\Database\Eloquent\Model`?**
  _High betweenness centrality (0.011) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _91 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.07680491551459294 - nodes in this community are weakly interconnected._
- **Should `composer.json` be split into smaller, more focused modules?**
  _Cohesion score 0.045454545454545456 - nodes in this community are weakly interconnected._
- **Should `SyncEmployeesJob` be split into smaller, more focused modules?**
  _Cohesion score 0.09243697478991597 - nodes in this community are weakly interconnected._