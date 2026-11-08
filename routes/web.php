<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChequeController;
use App\Http\Controllers\CKeditorController;
use App\Http\Controllers\CostAllocationController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinancialYearController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceTypeController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SalaryComponentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaxTypeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorBillController;
use App\Http\Middleware\AtomicWorkspaceWrite;
use App\Http\Middleware\AuthorizeWorkspace;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Public Share Links (No Auth Required)
Route::get('/share/{token}', [ShareLinkController::class, 'showPublic']);
Route::post('/share/{token}/password', [ShareLinkController::class, 'verifyPassword'])->middleware('throttle:5,1');

Route::get('/share/{token}/invoices/{id}/{mode}', [ShareLinkController::class, 'invoice']);
Route::get('/share/{token}/documents/{id}', [ShareLinkController::class, 'document']);

// PWA Manifest and Service Worker routes with explicit MIME and Service-Worker headers
Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});

// Rich Editor Mentions API (Loans, Parties & Employees)
Route::get('/rich-editor/mentions', [CKeditorController::class, 'mentions'])->middleware(['auth', AuthorizeWorkspace::class]);
Route::get('/api/rich-editor/mentions', [CKeditorController::class, 'mentions'])->middleware(['auth', AuthorizeWorkspace::class]);

// Dynamic Upload File Serving (Vercel Serverless Compatible)
Route::get('/uploads/{path}', function ($path) {
    abort_if(str_contains($path, '..') || str_contains($path, chr(0)), 404);
    $tmpFile = '/tmp/uploads/'.$path;
    $publicFile = public_path('uploads/'.$path);

    $filePath = file_exists($tmpFile) ? $tmpFile : (file_exists($publicFile) ? $publicFile : null);

    if (! $filePath) {
        abort(404);
    }

    return response()->file($filePath);
})->where('path', '.*');

// Protected Routes
Route::middleware(['auth', AuthorizeWorkspace::class, AtomicWorkspaceWrite::class])->group(function () {
    Route::prefix('payroll')->controller(\App\Http\Controllers\PayrollController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/create', 'create');
        Route::get('/import-history', 'importHistoryForm');
        Route::post('/import-history', 'importHistory');
        Route::get('/statutory', [\App\Http\Controllers\PayrollStatutoryController::class, 'index']);
        Route::get('/statutory/events/{event}/evidence', [\App\Http\Controllers\PayrollStatutoryController::class, 'evidence'])->whereNumber('event');
        Route::get('/{id}/statutory/{kind}', [\App\Http\Controllers\PayrollStatutoryController::class, 'show'])->whereNumber('id')->where('kind', 'epf|etf|apit');
        Route::get('/{id}/statutory/{kind}/schedule', [\App\Http\Controllers\PayrollStatutoryController::class, 'schedule'])->whereNumber('id')->where('kind', 'epf|etf|apit');
        Route::post('/{id}/statutory/{kind}/payment', [\App\Http\Controllers\PayrollStatutoryController::class, 'payment'])->whereNumber('id')->where('kind', 'epf|etf|apit');
        Route::post('/{id}/statutory/{kind}/{action}', [\App\Http\Controllers\PayrollStatutoryController::class, 'record'])->whereNumber('id')->where('kind', 'epf|etf|apit')->where('action', 'prepared|submitted|reopened');
        Route::get('/employees', 'employees');
        Route::post('/', 'store');
        Route::post('/preview', 'preview');
        Route::get('/advances', 'advances');
        Route::post('/advances', 'storeAdvance');
        Route::post('/advances/{payment}/schedule', 'scheduleAdvance')->whereNumber('payment');
        Route::post('/payments/{payment}/reverse', 'reverse')->whereNumber('payment');
        Route::get('/payments/{payment}/receipt', 'downloadReceipt')->whereNumber('payment');
        Route::get('/{id}', 'show')->whereNumber('id');
        Route::get('/{id}/export', 'export')->whereNumber('id');
        Route::get('/{id}/employees/{item}', 'employee')->whereNumber(['id','item']);
        Route::post('/{id}/employees/{item}', 'update')->whereNumber(['id','item']);
        Route::post('/{id}/bulk-review', 'bulkReview')->whereNumber('id');
        Route::get('/{id}/employees/{item}/payslip', 'payslip')->whereNumber(['id','item']);
        Route::post('/{id}/payments', 'payment')->whereNumber('id');
        Route::get('/{id}/salary-batches/create', 'salaryBatchForm')->whereNumber('id');
        Route::post('/{id}/salary-batches', 'salaryBatch')->whereNumber('id');
        Route::get('/{id}/salary-batches/{payment}', 'salaryBatchDetail')->whereNumber(['id', 'payment']);
        Route::post('/{id}/{action}', 'transition')->whereNumber('id')->where('action', 'submit|return|approve|complete|void');
    });

    Route::get('/', function () {
        return redirect('/dashboard');
    });

    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download']);
    Route::get('/design-system', fn () => view('design-system'));
    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/reports/export/{report}/{format}', [ReportExportController::class, 'export']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/master', function () {
        return redirect('/master/departments');
    });

    Route::prefix('master')->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index'])->name('masters.departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('masters.departments.store');
        Route::put('/departments/{id}', [DepartmentController::class, 'update'])->name('masters.departments.update');
        Route::delete('/departments/{id}', [DepartmentController::class, 'destroy'])->name('masters.departments.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])->name('masters.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('masters.categories.store');
        Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('masters.categories.update');
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('masters.categories.destroy');

        Route::get('/invoice-types', [InvoiceTypeController::class, 'index'])->name('masters.invoice_types.index');
        Route::post('/invoice-types', [InvoiceTypeController::class, 'store'])->name('masters.invoice_types.store');
        Route::put('/invoice-types/{id}', [InvoiceTypeController::class, 'update'])->name('masters.invoice_types.update');
        Route::delete('/invoice-types/{id}', [InvoiceTypeController::class, 'destroy'])->name('masters.invoice_types.destroy');

        Route::get('/parties', [PartyController::class, 'index'])->name('masters.parties.index');
        Route::post('/parties', [PartyController::class, 'store'])->name('masters.parties.store');
        Route::put('/parties/{id}', [PartyController::class, 'update'])->name('masters.parties.update');
        Route::delete('/parties/{id}', [PartyController::class, 'destroy'])->name('masters.parties.destroy');

        Route::get('/tags', [TagController::class, 'index'])->name('masters.tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('masters.tags.store');
        Route::put('/tags/{id}', [TagController::class, 'update'])->name('masters.tags.update');
        Route::delete('/tags/{id}', [TagController::class, 'destroy'])->name('masters.tags.destroy');

        Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('masters.bank_accounts.index');
        Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('masters.bank_accounts.store');
        Route::put('/bank-accounts/{id}', [BankAccountController::class, 'update'])->name('masters.bank_accounts.update');
        Route::delete('/bank-accounts/{id}', [BankAccountController::class, 'destroy'])->name('masters.bank_accounts.destroy');

        Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('masters.document_templates.index');
        Route::post('/document-templates', [DocumentTemplateController::class, 'store'])->name('masters.document_templates.store');
        Route::put('/document-templates/{id}', [DocumentTemplateController::class, 'update'])->name('masters.document_templates.update');
        Route::delete('/document-templates/{id}', [DocumentTemplateController::class, 'destroy'])->name('masters.document_templates.destroy');

        Route::get('/servers', [ServerController::class, 'index'])->name('masters.servers.index');
        Route::post('/servers', [ServerController::class, 'store'])->name('masters.servers.store');
        Route::put('/servers/{id}', [ServerController::class, 'update'])->name('masters.servers.update');
        Route::delete('/servers/{id}', [ServerController::class, 'destroy'])->name('masters.servers.destroy');

        Route::get('/currencies', [CurrencyController::class, 'index'])->name('masters.currencies.index');
        Route::post('/currencies', [CurrencyController::class, 'store'])->name('masters.currencies.store');
        Route::put('/currencies/{id}', [CurrencyController::class, 'update'])->name('masters.currencies.update');
        Route::delete('/currencies/{id}', [CurrencyController::class, 'destroy'])->name('masters.currencies.destroy');
        Route::post('/currencies/sync-rates', [CurrencyController::class, 'syncRates'])->name('masters.currencies.sync_rates');
        Route::get('/currencies/{code}/history', [CurrencyController::class, 'history'])->name('masters.currencies.history');

        Route::get('/tax-types', [TaxTypeController::class, 'index'])->name('masters.tax_types.index');
        Route::post('/tax-types', [TaxTypeController::class, 'store'])->name('masters.tax_types.store');
        Route::put('/tax-types/{id}', [TaxTypeController::class, 'update'])->name('masters.tax_types.update');
        Route::delete('/tax-types/{id}', [TaxTypeController::class, 'destroy'])->name('masters.tax_types.destroy');
        Route::post('/tax-types/calculate', [TaxTypeController::class, 'calculate'])->name('masters.tax_types.calculate');

        Route::get('/accounts', [AccountController::class, 'index'])->name('masters.accounts.index');
        Route::post('/accounts', [AccountController::class, 'store'])->name('masters.accounts.store');

        Route::get('/salary-components', [SalaryComponentController::class, 'index'])->name('masters.salary_components.index');
        Route::post('/salary-components', [SalaryComponentController::class, 'store'])->name('masters.salary_components.store');
        Route::put('/salary-components/{id}', [SalaryComponentController::class, 'update'])->name('masters.salary_components.update');
        Route::delete('/salary-components/{id}', [SalaryComponentController::class, 'destroy'])->name('masters.salary_components.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('masters.users.index');
        Route::post('/users', [UserController::class, 'store'])->name('masters.users.store');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('masters.users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('masters.users.destroy');
        Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('masters.users.toggle_status');
    });

    Route::put('/master/accounts/{id}', [AccountController::class, 'update']);
    Route::post('/payables/purchase-orders/{id}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('/payables/vendor-bills/{id}/payments', [VendorBillController::class, 'recordPayment']);
    Route::post('/treasury/bank-reconciliation/import', [BankReconciliationController::class, 'import']);
    Route::post('/treasury/bank-reconciliation/{id}/match', [BankReconciliationController::class, 'match']);
    Route::delete('/treasury/bank-reconciliation/{id}/match', [BankReconciliationController::class, 'unmatch']);

    // Financial Years & Fiscal Period Locking
    Route::prefix('settings')->group(function () {
        Route::get('/financial-years', [FinancialYearController::class, 'index']);
        Route::post('/financial-years/{id}/close', [FinancialYearController::class, 'close']);
        Route::post('/financial-years', [FinancialYearController::class, 'store']);
        Route::put('/fiscal-periods/{id}/status', [FinancialYearController::class, 'updatePeriodStatus']);
    });

    // Accounts Payable (Purchase Orders & Vendor Bills)
    Route::prefix('payables')->group(function () {
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('/vendor-bills', [VendorBillController::class, 'index']);
        Route::post('/vendor-bills', [VendorBillController::class, 'store']);
    });

    // Bank Reconciliation & Treasury
    Route::prefix('treasury')->group(function () {
        Route::get('/bank-reconciliation', [BankReconciliationController::class, 'index']);
        Route::post('/bank-reconciliation/auto-match', [BankReconciliationController::class, 'autoMatch']);
    });

    // Fixed Assets & Depreciation
    Route::prefix('assets')->group(function () {
        Route::get('/fixed-assets', [FixedAssetController::class, 'index']);
        Route::post('/fixed-assets', [FixedAssetController::class, 'store']);
        Route::post('/fixed-assets/{id}/depreciate', [FixedAssetController::class, 'runDepreciation']);
    });

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);

    Route::get('/employees', [EmployeeController::class, 'webIndex'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employee-integrations', [ApiIntegrationController::class, 'store']);
    Route::post('/employee-integrations/{apiIntegration}/sync', [ApiIntegrationController::class, 'sync']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::post('/employees/{id}/sync-party', [EmployeeController::class, 'syncParty'])->whereNumber('id')->name('employees.sync_party');
    Route::post('/employees/{id}/salary', [EmployeeController::class, 'updateSalary'])->name('employees.update_salary');
    Route::get('/employees/{id}/salary-preview', [EmployeeController::class, 'salaryPreview'])->name('employees.salary_preview');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::put('/profile/general', [ProfileController::class, 'updateGeneral'])->name('profile.general.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/config', [ProfileController::class, 'updateConfig']);

    // Share Links (Admin)
    Route::get('/share-links', [ShareLinkController::class, 'index']);
    Route::post('/share-links', [ShareLinkController::class, 'store']);
    Route::post('/share-links/{id}/revoke', [ShareLinkController::class, 'revoke']);
    Route::post('/share-links/{id}/regenerate', [ShareLinkController::class, 'regenerate']);

    Route::post('/projects/{id}/timesheets', [ProjectController::class, 'storeTimesheet']);
    Route::post('/projects/{id}/payments', [ProjectController::class, 'storePayment']);
    Route::post('/projects/{id}/change-requests', [ProjectController::class, 'storeChangeRequest']);
    Route::post('/projects/{id}/change-requests/{crId}/approve', [ProjectController::class, 'approveChangeRequest']);
    Route::post('/projects/{id}/change-requests/{crId}/update', [ProjectController::class, 'updateChangeRequest']);
    Route::post('/projects/{id}/notes', [ProjectController::class, 'storeNote']);
    Route::post('/projects/{id}/interactions', [ProjectController::class, 'storeInteraction']);
    Route::post('/projects/{id}/commissions', [ProjectController::class, 'storeCommission']);
    Route::put('/projects/{id}/commissions/{commId}', [ProjectController::class, 'updateCommission']);
    Route::delete('/projects/{id}/commissions/{commId}', [ProjectController::class, 'destroyCommission']);
    Route::post('/projects/{id}/commissions/{commId}/payments', [ProjectController::class, 'storeCommissionPayment']);

    // CR-2 Invoice Schedules & Approvals
    Route::post('/projects/{id}/schedules', [ProjectController::class, 'storeSchedule']);
    Route::put('/projects/{id}/schedules/{scheduleId}', [ProjectController::class, 'updateSchedule']);
    Route::delete('/projects/{id}/schedules/{scheduleId}', [ProjectController::class, 'destroySchedule']);
    Route::post('/projects/{id}/schedules/{scheduleId}/pause', [ProjectController::class, 'pauseSchedule']);
    Route::post('/projects/{id}/schedules/{scheduleId}/resume', [ProjectController::class, 'resumeSchedule']);
    Route::post('/projects/{id}/schedules/{scheduleId}/run', [ProjectController::class, 'runScheduleImmediately']);
    Route::post('/projects/{id}/schedules/{scheduleId}/skip', [ProjectController::class, 'skipOccurrence']);
    Route::post('/projects/{id}/invoices/{invoiceId}/approve', [ProjectController::class, 'approveDraftInvoice']);
    Route::post('/projects/{id}/invoices/{invoiceId}/reject', [ProjectController::class, 'rejectDraftInvoice']);

    // Payment Milestones
    Route::post('/projects/{id}/milestones', [ProjectController::class, 'storeMilestone']);
    Route::post('/projects/{id}/milestones/{milestoneId}/skip', [ProjectController::class, 'skipMilestone']);
    Route::post('/projects/{id}/milestones/{milestoneId}/invoice', [ProjectController::class, 'invoiceMilestone']);

    Route::put('/projects/{id}/milestones/{milestoneId}', [ProjectController::class, 'updateMilestone']);
    Route::delete('/projects/{id}/milestones/{milestoneId}', [ProjectController::class, 'destroyMilestone']);

    // Project Documents
    Route::post('/projects/{id}/documents', [ProjectDocumentController::class, 'store']);
    Route::put('/documents/{id}', [ProjectDocumentController::class, 'update']);
    Route::delete('/documents/{id}', [ProjectDocumentController::class, 'destroy']);
    Route::get('/documents/{id}/download', [ProjectDocumentController::class, 'download']);

    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

    Route::get('/journal-entries', [JournalEntryController::class, 'index']);
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets', [BudgetController::class, 'store']);
    Route::get('/budgets/{id}/json', [BudgetController::class, 'getJson']);
    Route::get('/budgets/{id}', [BudgetController::class, 'show']);
    Route::post('/budgets/{id}/transactions', [BudgetController::class, 'storeTransaction']);
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}/edit', [InvoiceController::class, 'edit']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'store']);
    Route::post('/invoices/{id}/duplicate', [InvoiceController::class, 'duplicate']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::patch('/invoices/{id}/status', [InvoiceController::class, 'updateStatus']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf']);
    Route::get('/invoices/{id}/view', [InvoiceController::class, 'viewPdf']);

    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/schedules', [LoanController::class, 'schedules']);
    Route::get('/loans/settlements', [LoanController::class, 'settlements']);
    Route::get('/loans/party-report', [LoanController::class, 'partyReport']);
    Route::get('/loans/party-report/loans', [LoanController::class, 'partyFacilities']);
    Route::post('/loans', [LoanController::class, 'store']);

    Route::get('/loans/{id}', [LoanController::class, 'show']);
    Route::post('/loans/{id}/funding-links', [\App\Http\Controllers\LoanFlowController::class, 'store']);
    Route::delete('/loans/{id}/funding-links/{linkId}', [\App\Http\Controllers\LoanFlowController::class, 'destroy']);
    Route::put('/loans/{id}', [LoanController::class, 'update']);
    Route::post('/loans/{id}/activate', [LoanController::class, 'activate']);
    Route::post('/loans/{id}/status', [LoanController::class, 'updateStatus']);
    Route::post('/loans/{id}/schedule', [LoanController::class, 'addInterestSchedule']);
    Route::post('/loans/{id}/schedule/{scheduleId}/settle', [LoanController::class, 'settleInterestPeriod']);
    Route::put('/loans/{id}/schedule/{scheduleId}/payments/{paymentId}', [LoanController::class, 'updateInterestPayment']);
    Route::delete('/loans/{id}/schedule/{scheduleId}/payments/{paymentId}', [LoanController::class, 'deleteInterestPayment']);
    Route::post('/loans/{id}/schedule/{scheduleId}/edit', [LoanController::class, 'editInterestAmount']);
    Route::post('/loans/{id}/schedule/{scheduleId}/skip', [LoanController::class, 'markInterestNotNeeded']);
    Route::post('/loans/{id}/repayment', [LoanController::class, 'recordPrincipalRepayment']);
    Route::post('/loans/{id}/draw', [LoanController::class, 'addPrincipalDraw']);
    Route::post('/loans/{id}/settle-fully', [LoanController::class, 'settleFully']);
    Route::delete('/loans/{id}', [LoanController::class, 'destroy']);
    // Reminders Engine
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::post('/reminders/{id}/status', [ReminderController::class, 'updateStatus']);
    Route::delete('/reminders/{id}', [ReminderController::class, 'destroy']);

    // Approval Inbox
    Route::get('/approvals', [ApprovalController::class, 'index']);
    Route::post('/approvals/{type}/{id}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{type}/{id}/reject', [ApprovalController::class, 'reject']);

    // Cheques Tracking
    Route::get('/cheques', [ChequeController::class, 'index']);
    Route::post('/cheques', [ChequeController::class, 'store']);
    Route::post('/cheques/{id}/status', [ChequeController::class, 'updateStatus']);
    Route::delete('/cheques/{id}', [ChequeController::class, 'destroy']);

    // Audit Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    // Journal Entry Manual Post
    Route::post('/journal-entries', [JournalEntryController::class, 'store']);

    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/pnl', [ReportController::class, 'pnl'])->name('reports.pnl');
        Route::get('/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
        Route::get('/commissions', [ReportController::class, 'commissions'])->name('reports.commissions');
        Route::get('/cost-allocations', [ReportController::class, 'costAllocations'])->name('reports.cost_allocations');
        Route::get('/project-status', [ReportController::class, 'projectStatus'])->name('reports.project_status');
        Route::get('/client-health', [ReportController::class, 'clientHealth'])->name('reports.client_health');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance_sheet');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash_flow');
        Route::get('/ar-aging', [ReportController::class, 'arAging'])->name('reports.ar_aging');
        Route::get('/project-profitability', [ReportController::class, 'projectProfitability'])->name('reports.project_profitability');
        Route::get('/client-statement', [ReportController::class, 'clientStatement'])->name('reports.client_statement');
        Route::get('/bank-reconciliation', [ReportController::class, 'bankReconciliation'])->name('reports.bank_reconciliation');
        Route::get('/expense-trend', [ReportController::class, 'expenseTrend'])->name('reports.expense_trend');
        Route::get('/party-ledger', [ReportController::class, 'partyLedger'])->name('reports.party_ledger');
        Route::get('/audit-payables', [ReportController::class, 'auditPayables'])->name('reports.audit_payables');
        Route::post('/party-ledger/settlement', [ReportController::class, 'recordPartySettlement'])->name('reports.party_settlement');
    });

    // Cost Allocations & Integrations
    Route::prefix('api')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);

        Route::get('/cost-allocations', [CostAllocationController::class, 'index']);
        Route::post('/cost-allocations', [CostAllocationController::class, 'store']);
        Route::put('/cost-allocations/{costAllocation}', [CostAllocationController::class, 'update']);
        Route::delete('/cost-allocations/{costAllocation}', [CostAllocationController::class, 'destroy']);

        Route::post('/api-integrations', [ApiIntegrationController::class, 'store']);
        Route::post('/api-integrations/{apiIntegration}/sync', [ApiIntegrationController::class, 'sync']);
    });

});
