# Application route reference

Generated from `php artisan route:list --json --except-vendor` on **8 November 2026**.

This is the declared endpoint catalogue, not proof that an operation is permitted in every record state. Read the [current feature guide](35-implemented-features.md) for workflows and limitations. Middleware and controllers enforce authentication, roles, record relationships and financial validation. Supporting `/api/*` endpoints in `routes/web.php` use the web authentication/CSRF context; their name does not mean public API access.

**253 declared application routes**, grouped below. No credentials or production record values are included.

## Employee integrations

| Method | Path | Action |
|---|---|---|
| `POST` | `/employee-integrations` | `ApiIntegrationController@store` |
| `POST` | `/employee-integrations/{apiIntegration}/sync` | `ApiIntegrationController@sync` |

## Employees

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/employees` | `EmployeeController@webIndex` |
| `POST` | `/employees` | `EmployeeController@store` |
| `GET\|HEAD` | `/employees/create` | `EmployeeController@create` |
| `GET\|HEAD` | `/employees/{id}` | `EmployeeController@show` |
| `POST` | `/employees/{id}/salary` | `EmployeeController@updateSalary` |
| `GET\|HEAD` | `/employees/{id}/salary-preview` | `EmployeeController@salaryPreview` |
| `POST` | `/employees/{id}/sync-party` | `EmployeeController@syncParty` |

## Financial years and settings

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/settings/financial-years` | `FinancialYearController@index` |
| `POST` | `/settings/financial-years` | `FinancialYearController@store` |
| `POST` | `/settings/financial-years/{id}/close` | `FinancialYearController@close` |
| `PUT` | `/settings/fiscal-periods/{id}/status` | `FinancialYearController@updatePeriodStatus` |

## Fixed assets

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/assets/fixed-assets` | `FixedAssetController@index` |
| `POST` | `/assets/fixed-assets` | `FixedAssetController@store` |
| `POST` | `/assets/fixed-assets/{id}/depreciate` | `FixedAssetController@runDepreciation` |

## Invoices

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/invoices` | `InvoiceController@index` |
| `POST` | `/invoices` | `InvoiceController@store` |
| `DELETE` | `/invoices/{id}` | `InvoiceController@destroy` |
| `PUT` | `/invoices/{id}` | `InvoiceController@store` |
| `POST` | `/invoices/{id}/duplicate` | `InvoiceController@duplicate` |
| `GET\|HEAD` | `/invoices/{id}/edit` | `InvoiceController@edit` |
| `GET\|HEAD` | `/invoices/{id}/pdf` | `InvoiceController@downloadPdf` |
| `PATCH` | `/invoices/{id}/status` | `InvoiceController@updateStatus` |
| `GET\|HEAD` | `/invoices/{id}/view` | `InvoiceController@viewPdf` |

## Loans

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/loans` | `LoanController@index` |
| `POST` | `/loans` | `LoanController@store` |
| `GET\|HEAD` | `/loans/party-report` | `LoanController@partyReport` |
| `GET\|HEAD` | `/loans/party-report/loans` | `LoanController@partyFacilities` |
| `GET\|HEAD` | `/loans/schedules` | `LoanController@schedules` |
| `GET\|HEAD` | `/loans/settlements` | `LoanController@settlements` |
| `DELETE` | `/loans/{id}` | `LoanController@destroy` |
| `GET\|HEAD` | `/loans/{id}` | `LoanController@show` |
| `PUT` | `/loans/{id}` | `LoanController@update` |
| `POST` | `/loans/{id}/activate` | `LoanController@activate` |
| `POST` | `/loans/{id}/draw` | `LoanController@addPrincipalDraw` |
| `POST` | `/loans/{id}/funding-links` | `LoanFlowController@store` |
| `DELETE` | `/loans/{id}/funding-links/{linkId}` | `LoanFlowController@destroy` |
| `POST` | `/loans/{id}/repayment` | `LoanController@recordPrincipalRepayment` |
| `POST` | `/loans/{id}/schedule` | `LoanController@addInterestSchedule` |
| `POST` | `/loans/{id}/schedule/{scheduleId}/edit` | `LoanController@editInterestAmount` |
| `DELETE` | `/loans/{id}/schedule/{scheduleId}/payments/{paymentId}` | `LoanController@deleteInterestPayment` |
| `PUT` | `/loans/{id}/schedule/{scheduleId}/payments/{paymentId}` | `LoanController@updateInterestPayment` |
| `POST` | `/loans/{id}/schedule/{scheduleId}/settle` | `LoanController@settleInterestPeriod` |
| `POST` | `/loans/{id}/schedule/{scheduleId}/skip` | `LoanController@markInterestNotNeeded` |
| `POST` | `/loans/{id}/settle-fully` | `LoanController@settleFully` |
| `POST` | `/loans/{id}/status` | `LoanController@updateStatus` |

## Master data

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/master` | `Closure` |
| `GET\|HEAD` | `/master/accounts` | `AccountController@index` |
| `POST` | `/master/accounts` | `AccountController@store` |
| `PUT` | `/master/accounts/{id}` | `AccountController@update` |
| `GET\|HEAD` | `/master/bank-accounts` | `BankAccountController@index` |
| `POST` | `/master/bank-accounts` | `BankAccountController@store` |
| `DELETE` | `/master/bank-accounts/{id}` | `BankAccountController@destroy` |
| `PUT` | `/master/bank-accounts/{id}` | `BankAccountController@update` |
| `GET\|HEAD` | `/master/categories` | `CategoryController@index` |
| `POST` | `/master/categories` | `CategoryController@store` |
| `DELETE` | `/master/categories/{id}` | `CategoryController@destroy` |
| `PUT` | `/master/categories/{id}` | `CategoryController@update` |
| `GET\|HEAD` | `/master/currencies` | `CurrencyController@index` |
| `POST` | `/master/currencies` | `CurrencyController@store` |
| `POST` | `/master/currencies/sync-rates` | `CurrencyController@syncRates` |
| `GET\|HEAD` | `/master/currencies/{code}/history` | `CurrencyController@history` |
| `DELETE` | `/master/currencies/{id}` | `CurrencyController@destroy` |
| `PUT` | `/master/currencies/{id}` | `CurrencyController@update` |
| `GET\|HEAD` | `/master/departments` | `DepartmentController@index` |
| `POST` | `/master/departments` | `DepartmentController@store` |
| `DELETE` | `/master/departments/{id}` | `DepartmentController@destroy` |
| `PUT` | `/master/departments/{id}` | `DepartmentController@update` |
| `GET\|HEAD` | `/master/document-templates` | `DocumentTemplateController@index` |
| `POST` | `/master/document-templates` | `DocumentTemplateController@store` |
| `DELETE` | `/master/document-templates/{id}` | `DocumentTemplateController@destroy` |
| `PUT` | `/master/document-templates/{id}` | `DocumentTemplateController@update` |
| `GET\|HEAD` | `/master/invoice-types` | `InvoiceTypeController@index` |
| `POST` | `/master/invoice-types` | `InvoiceTypeController@store` |
| `DELETE` | `/master/invoice-types/{id}` | `InvoiceTypeController@destroy` |
| `PUT` | `/master/invoice-types/{id}` | `InvoiceTypeController@update` |
| `GET\|HEAD` | `/master/parties` | `PartyController@index` |
| `POST` | `/master/parties` | `PartyController@store` |
| `DELETE` | `/master/parties/{id}` | `PartyController@destroy` |
| `PUT` | `/master/parties/{id}` | `PartyController@update` |
| `GET\|HEAD` | `/master/salary-components` | `SalaryComponentController@index` |
| `POST` | `/master/salary-components` | `SalaryComponentController@store` |
| `DELETE` | `/master/salary-components/{id}` | `SalaryComponentController@destroy` |
| `PUT` | `/master/salary-components/{id}` | `SalaryComponentController@update` |
| `GET\|HEAD` | `/master/servers` | `ServerController@index` |
| `POST` | `/master/servers` | `ServerController@store` |
| `DELETE` | `/master/servers/{id}` | `ServerController@destroy` |
| `PUT` | `/master/servers/{id}` | `ServerController@update` |
| `GET\|HEAD` | `/master/tags` | `TagController@index` |
| `POST` | `/master/tags` | `TagController@store` |
| `DELETE` | `/master/tags/{id}` | `TagController@destroy` |
| `PUT` | `/master/tags/{id}` | `TagController@update` |
| `GET\|HEAD` | `/master/tax-types` | `TaxTypeController@index` |
| `POST` | `/master/tax-types` | `TaxTypeController@store` |
| `POST` | `/master/tax-types/calculate` | `TaxTypeController@calculate` |
| `DELETE` | `/master/tax-types/{id}` | `TaxTypeController@destroy` |
| `PUT` | `/master/tax-types/{id}` | `TaxTypeController@update` |
| `GET\|HEAD` | `/master/users` | `UserController@index` |
| `POST` | `/master/users` | `UserController@store` |
| `DELETE` | `/master/users/{id}` | `UserController@destroy` |
| `PUT` | `/master/users/{id}` | `UserController@update` |
| `PATCH` | `/master/users/{id}/toggle-status` | `UserController@toggleStatus` |

## Payroll and salary advances

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/payroll` | `PayrollController@index` |
| `POST` | `/payroll` | `PayrollController@store` |
| `GET\|HEAD` | `/payroll/advances` | `PayrollController@advances` |
| `POST` | `/payroll/advances` | `PayrollController@storeAdvance` |
| `GET\|HEAD` | `/payroll/advances/import-history` | `PayrollController@historicalAdvances` |
| `POST` | `/payroll/advances/{payment}/schedule` | `PayrollController@scheduleAdvance` |
| `GET\|HEAD` | `/payroll/create` | `PayrollController@create` |
| `GET\|HEAD` | `/payroll/employees` | `PayrollController@employees` |
| `GET\|HEAD` | `/payroll/import-history` | `PayrollController@importHistoryForm` |
| `POST` | `/payroll/import-history` | `PayrollController@importHistory` |
| `GET\|HEAD` | `/payroll/payments/{payment}/receipt` | `PayrollController@downloadReceipt` |
| `POST` | `/payroll/payments/{payment}/reverse` | `PayrollController@reverse` |
| `POST` | `/payroll/preview` | `PayrollController@preview` |
| `GET\|HEAD` | `/payroll/statutory` | `PayrollStatutoryController@index` |
| `GET\|HEAD` | `/payroll/statutory/events/{event}/evidence` | `PayrollStatutoryController@evidence` |
| `GET\|HEAD` | `/payroll/{id}` | `PayrollController@show` |
| `POST` | `/payroll/{id}/bulk-review` | `PayrollController@bulkReview` |
| `GET\|HEAD` | `/payroll/{id}/employees/{item}` | `PayrollController@employee` |
| `POST` | `/payroll/{id}/employees/{item}` | `PayrollController@update` |
| `GET\|HEAD` | `/payroll/{id}/employees/{item}/payslip` | `PayrollController@payslip` |
| `GET\|HEAD` | `/payroll/{id}/export` | `PayrollController@export` |
| `POST` | `/payroll/{id}/historical-advances` | `PayrollController@importHistoricalAdvances` |
| `POST` | `/payroll/{id}/payments` | `PayrollController@payment` |
| `POST` | `/payroll/{id}/salary-batches` | `PayrollController@salaryBatch` |
| `GET\|HEAD` | `/payroll/{id}/salary-batches/create` | `PayrollController@salaryBatchForm` |
| `GET\|HEAD` | `/payroll/{id}/salary-batches/{payment}` | `PayrollController@salaryBatchDetail` |
| `GET\|HEAD` | `/payroll/{id}/statutory/{kind}` | `PayrollStatutoryController@show` |
| `POST` | `/payroll/{id}/statutory/{kind}/payment` | `PayrollStatutoryController@payment` |
| `GET\|HEAD` | `/payroll/{id}/statutory/{kind}/schedule` | `PayrollStatutoryController@schedule` |
| `POST` | `/payroll/{id}/statutory/{kind}/{action}` | `PayrollStatutoryController@record` |
| `POST` | `/payroll/{id}/{action}` | `PayrollController@transition` |

## Profile

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/profile` | `ProfileController@index` |
| `POST` | `/profile/config` | `ProfileController@updateConfig` |
| `PUT` | `/profile/general` | `ProfileController@updateGeneral` |
| `PUT` | `/profile/password` | `ProfileController@updatePassword` |

## Projects

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/projects` | `ProjectController@index` |
| `POST` | `/projects` | `ProjectController@store` |
| `DELETE` | `/projects/{id}` | `ProjectController@destroy` |
| `GET\|HEAD` | `/projects/{id}` | `ProjectController@show` |
| `PUT` | `/projects/{id}` | `ProjectController@update` |
| `POST` | `/projects/{id}/change-requests` | `ProjectController@storeChangeRequest` |
| `POST` | `/projects/{id}/change-requests/{crId}/approve` | `ProjectController@approveChangeRequest` |
| `POST` | `/projects/{id}/change-requests/{crId}/update` | `ProjectController@updateChangeRequest` |
| `POST` | `/projects/{id}/commissions` | `ProjectController@storeCommission` |
| `DELETE` | `/projects/{id}/commissions/{commId}` | `ProjectController@destroyCommission` |
| `PUT` | `/projects/{id}/commissions/{commId}` | `ProjectController@updateCommission` |
| `POST` | `/projects/{id}/commissions/{commId}/payments` | `ProjectController@storeCommissionPayment` |
| `POST` | `/projects/{id}/documents` | `ProjectDocumentController@store` |
| `POST` | `/projects/{id}/interactions` | `ProjectController@storeInteraction` |
| `POST` | `/projects/{id}/invoices/{invoiceId}/approve` | `ProjectController@approveDraftInvoice` |
| `POST` | `/projects/{id}/invoices/{invoiceId}/reject` | `ProjectController@rejectDraftInvoice` |
| `POST` | `/projects/{id}/milestones` | `ProjectController@storeMilestone` |
| `DELETE` | `/projects/{id}/milestones/{milestoneId}` | `ProjectController@destroyMilestone` |
| `PUT` | `/projects/{id}/milestones/{milestoneId}` | `ProjectController@updateMilestone` |
| `POST` | `/projects/{id}/milestones/{milestoneId}/invoice` | `ProjectController@invoiceMilestone` |
| `POST` | `/projects/{id}/milestones/{milestoneId}/skip` | `ProjectController@skipMilestone` |
| `POST` | `/projects/{id}/notes` | `ProjectController@storeNote` |
| `POST` | `/projects/{id}/payments` | `ProjectController@storePayment` |
| `POST` | `/projects/{id}/schedules` | `ProjectController@storeSchedule` |
| `DELETE` | `/projects/{id}/schedules/{scheduleId}` | `ProjectController@destroySchedule` |
| `PUT` | `/projects/{id}/schedules/{scheduleId}` | `ProjectController@updateSchedule` |
| `POST` | `/projects/{id}/schedules/{scheduleId}/pause` | `ProjectController@pauseSchedule` |
| `POST` | `/projects/{id}/schedules/{scheduleId}/resume` | `ProjectController@resumeSchedule` |
| `POST` | `/projects/{id}/schedules/{scheduleId}/run` | `ProjectController@runScheduleImmediately` |
| `POST` | `/projects/{id}/schedules/{scheduleId}/skip` | `ProjectController@skipOccurrence` |
| `POST` | `/projects/{id}/timesheets` | `ProjectController@storeTimesheet` |

## Public share views

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/share/{token}` | `ShareLinkController@showPublic` |
| `GET\|HEAD` | `/share/{token}/documents/{id}` | `ShareLinkController@document` |
| `GET\|HEAD` | `/share/{token}/invoices/{id}/{mode}` | `ShareLinkController@invoice` |
| `POST` | `/share/{token}/password` | `ShareLinkController@verifyPassword` |

## Purchase orders and vendor bills

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/payables/purchase-orders` | `PurchaseOrderController@index` |
| `POST` | `/payables/purchase-orders` | `PurchaseOrderController@store` |
| `POST` | `/payables/purchase-orders/{id}/approve` | `PurchaseOrderController@approve` |
| `GET\|HEAD` | `/payables/vendor-bills` | `VendorBillController@index` |
| `POST` | `/payables/vendor-bills` | `VendorBillController@store` |
| `POST` | `/payables/vendor-bills/{id}/payments` | `VendorBillController@recordPayment` |

## Reports

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/reports` | `ReportController@index` |
| `GET\|HEAD` | `/reports/ar-aging` | `ReportController@arAging` |
| `GET\|HEAD` | `/reports/audit-payables` | `ReportController@auditPayables` |
| `GET\|HEAD` | `/reports/balance-sheet` | `ReportController@balanceSheet` |
| `GET\|HEAD` | `/reports/bank-reconciliation` | `ReportController@bankReconciliation` |
| `GET\|HEAD` | `/reports/cash-flow` | `ReportController@cashFlow` |
| `GET\|HEAD` | `/reports/client-health` | `ReportController@clientHealth` |
| `GET\|HEAD` | `/reports/client-statement` | `ReportController@clientStatement` |
| `GET\|HEAD` | `/reports/commissions` | `ReportController@commissions` |
| `GET\|HEAD` | `/reports/cost-allocations` | `ReportController@costAllocations` |
| `GET\|HEAD` | `/reports/expense-trend` | `ReportController@expenseTrend` |
| `GET\|HEAD` | `/reports/expenses` | `ReportController@expenses` |
| `GET\|HEAD` | `/reports/export/{report}/{format}` | `ReportExportController@export` |
| `GET\|HEAD` | `/reports/party-ledger` | `ReportController@partyLedger` |
| `POST` | `/reports/party-ledger/settlement` | `ReportController@recordPartySettlement` |
| `GET\|HEAD` | `/reports/pnl` | `ReportController@pnl` |
| `GET\|HEAD` | `/reports/project-profitability` | `ReportController@projectProfitability` |
| `GET\|HEAD` | `/reports/project-status` | `ReportController@projectStatus` |

## Rich-editor mentions

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/rich-editor/mentions` | `CKeditorController@mentions` |

## Share administration

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/share-links` | `ShareLinkController@index` |
| `POST` | `/share-links` | `ShareLinkController@store` |
| `POST` | `/share-links/{id}/regenerate` | `ShareLinkController@regenerate` |
| `POST` | `/share-links/{id}/revoke` | `ShareLinkController@revoke` |

## Supporting authenticated JSON endpoints

| Method | Path | Action |
|---|---|---|
| `POST` | `/api/api-integrations` | `ApiIntegrationController@store` |
| `POST` | `/api/api-integrations/{apiIntegration}/sync` | `ApiIntegrationController@sync` |
| `GET\|HEAD` | `/api/cost-allocations` | `CostAllocationController@index` |
| `POST` | `/api/cost-allocations` | `CostAllocationController@store` |
| `DELETE` | `/api/cost-allocations/{costAllocation}` | `CostAllocationController@destroy` |
| `PUT` | `/api/cost-allocations/{costAllocation}` | `CostAllocationController@update` |
| `GET\|HEAD` | `/api/employees` | `EmployeeController@index` |
| `GET\|HEAD` | `/api/rich-editor/mentions` | `CKeditorController@mentions` |

## Treasury

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/treasury/bank-reconciliation` | `BankReconciliationController@index` |
| `POST` | `/treasury/bank-reconciliation/auto-match` | `BankReconciliationController@autoMatch` |
| `POST` | `/treasury/bank-reconciliation/import` | `BankReconciliationController@import` |
| `DELETE` | `/treasury/bank-reconciliation/{id}/match` | `BankReconciliationController@unmatch` |
| `POST` | `/treasury/bank-reconciliation/{id}/match` | `BankReconciliationController@match` |

## Workspace and operations

| Method | Path | Action |
|---|---|---|
| `GET\|HEAD` | `/` | `Closure` |
| `GET\|HEAD` | `/activity-logs` | `ActivityLogController@index` |
| `GET\|HEAD` | `/approvals` | `ApprovalController@index` |
| `POST` | `/approvals/{type}/{id}/approve` | `ApprovalController@approve` |
| `POST` | `/approvals/{type}/{id}/reject` | `ApprovalController@reject` |
| `GET\|HEAD` | `/attachments/{id}/download` | `AttachmentController@download` |
| `GET\|HEAD` | `/budgets` | `BudgetController@index` |
| `POST` | `/budgets` | `BudgetController@store` |
| `DELETE` | `/budgets/{id}` | `BudgetController@destroy` |
| `GET\|HEAD` | `/budgets/{id}` | `BudgetController@show` |
| `GET\|HEAD` | `/budgets/{id}/json` | `BudgetController@getJson` |
| `POST` | `/budgets/{id}/transactions` | `BudgetController@storeTransaction` |
| `GET\|HEAD` | `/cheques` | `ChequeController@index` |
| `POST` | `/cheques` | `ChequeController@store` |
| `DELETE` | `/cheques/{id}` | `ChequeController@destroy` |
| `POST` | `/cheques/{id}/status` | `ChequeController@updateStatus` |
| `GET\|HEAD` | `/dashboard` | `DashboardController@index` |
| `GET\|HEAD` | `/design-system` | `Closure` |
| `DELETE` | `/documents/{id}` | `ProjectDocumentController@destroy` |
| `PUT` | `/documents/{id}` | `ProjectDocumentController@update` |
| `GET\|HEAD` | `/documents/{id}/download` | `ProjectDocumentController@download` |
| `GET\|HEAD` | `/journal-entries` | `JournalEntryController@index` |
| `POST` | `/journal-entries` | `JournalEntryController@store` |
| `GET\|HEAD` | `/login` | `AuthController@showLogin` |
| `POST` | `/login` | `AuthController@login` |
| `POST` | `/logout` | `AuthController@logout` |
| `GET\|HEAD` | `/manifest.json` | `Closure` |
| `GET\|HEAD` | `/reminders` | `ReminderController@index` |
| `POST` | `/reminders` | `ReminderController@store` |
| `DELETE` | `/reminders/{id}` | `ReminderController@destroy` |
| `POST` | `/reminders/{id}/status` | `ReminderController@updateStatus` |
| `GET\|HEAD` | `/search` | `SearchController@index` |
| `GET\|HEAD` | `/sw.js` | `Closure` |
| `GET\|HEAD` | `/transactions` | `TransactionController@index` |
| `POST` | `/transactions` | `TransactionController@store` |
| `DELETE` | `/transactions/{id}` | `TransactionController@destroy` |
| `PUT` | `/transactions/{id}` | `TransactionController@update` |
| `GET\|HEAD` | `/uploads/{path}` | `Closure` |

## Scheduled work and maintenance

HTTP routes do not describe queue/scheduler execution. See [`routes/console.php`](../routes/console.php) for recurring invoices, employee sync and reminder resumption, and the [feature guide](35-implemented-features.md#accounting-controls-and-operations) for maintenance commands and deployment responsibilities.

Regenerate this catalogue when route declarations change. Do not turn a state-changing route into an unauthenticated shortcut or invoke financial actions merely to validate this reference.
