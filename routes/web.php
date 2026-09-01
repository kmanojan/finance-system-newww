<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InvoiceTypeController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\UserController;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Public Share Links (No Auth Required)
Route::get('/share/{token}', [ShareLinkController::class, 'showPublic']);
Route::post('/share/{token}/password', [ShareLinkController::class, 'verifyPassword']);

// PWA Manifest and Service Worker routes with explicit MIME and Service-Worker headers
Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');
    if (!file_exists($path)) abort(404);
    return response()->file($path, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    if (!file_exists($path)) abort(404);
    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});

// Rich Editor Mentions API (Loans, Parties & Employees)
$mentionsHandler = function () {
    $loans = collect();
    if (Schema::hasTable('loans')) {
        $hasLoanCode = Schema::hasColumn('loans', 'loan_code');
        $selects = [
            'loans.id', 
            'loans.lender_name', 
            'loans.party_id',
            'parties.name as party_name',
            'loans.principal_amount', 
            'loans.currency', 
            'loans.status',
            'loans.purpose'
        ];
        if ($hasLoanCode) {
            $selects[] = 'loans.loan_code';
        }

        $loans = DB::table('loans')
            ->leftJoin('parties', 'loans.party_id', '=', 'parties.id')
            ->select($selects)
            ->orderBy('loans.id', 'desc')
            ->get()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'loan_code' => ($l->loan_code ?? null) ?: ('LN-' . str_pad($l->id, 4, '0', STR_PAD_LEFT)),
                    'lender_name' => $l->lender_name,
                    'party_name' => $l->party_name,
                    'party_id' => $l->party_id,
                    'principal_amount' => (float)$l->principal_amount,
                    'currency' => $l->currency ?: 'LKR',
                    'status' => $l->status,
                    'purpose' => $l->purpose ? trim(strip_tags($l->purpose)) : '',
                ];
            });
    }

    $parties = collect();
    if (Schema::hasTable('parties')) {
        $parties = DB::table('parties')
            ->select('id', 'name', 'contact_person', 'phone', 'email', 'currency', 'party_types')
            ->orderBy('name', 'asc')
            ->get();
    }

    $employees = collect();
    if (Schema::hasTable('employees')) {
        $employees = DB::table('employees')
            ->select('id', 'full_name', 'first_name', 'last_name', 'employee_code', 'job_position')
            ->orderBy('first_name', 'asc')
            ->get()
            ->map(function ($e) {
                $name = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? ''));
                if (empty($name)) $name = $e->full_name ?? 'Employee #' . $e->id;
                return [
                    'id' => $e->id,
                    'name' => $name,
                    'code' => $e->employee_code,
                    'job_position' => $e->job_position,
                ];
            });
    }

    return response()->json([
        'loans' => $loans,
        'parties' => $parties,
        'employees' => $employees,
    ]);
};

Route::get('/rich-editor/mentions', $mentionsHandler);
Route::get('/api/rich-editor/mentions', $mentionsHandler);

// Dynamic Upload File Serving (Vercel Serverless Compatible)
Route::get('/uploads/{path}', function ($path) {
    $tmpFile = '/tmp/uploads/' . $path;
    $publicFile = public_path('uploads/' . $path);

    $filePath = file_exists($tmpFile) ? $tmpFile : (file_exists($publicFile) ? $publicFile : null);

    if (!$filePath) {
        abort(404);
    }

    return response()->file($filePath);
})->where('path', '.*');

// Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect('/dashboard');
    });

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

        Route::get('/currencies', [\App\Http\Controllers\CurrencyController::class, 'index'])->name('masters.currencies.index');
        Route::post('/currencies', [\App\Http\Controllers\CurrencyController::class, 'store'])->name('masters.currencies.store');
        Route::put('/currencies/{id}', [\App\Http\Controllers\CurrencyController::class, 'update'])->name('masters.currencies.update');
        Route::delete('/currencies/{id}', [\App\Http\Controllers\CurrencyController::class, 'destroy'])->name('masters.currencies.destroy');
        Route::post('/currencies/sync-rates', [\App\Http\Controllers\CurrencyController::class, 'syncRates'])->name('masters.currencies.sync_rates');
        Route::get('/currencies/{code}/history', [\App\Http\Controllers\CurrencyController::class, 'history'])->name('masters.currencies.history');

        Route::get('/tax-types', [\App\Http\Controllers\TaxTypeController::class, 'index'])->name('masters.tax_types.index');
        Route::post('/tax-types', [\App\Http\Controllers\TaxTypeController::class, 'store'])->name('masters.tax_types.store');
        Route::put('/tax-types/{id}', [\App\Http\Controllers\TaxTypeController::class, 'update'])->name('masters.tax_types.update');
        Route::delete('/tax-types/{id}', [\App\Http\Controllers\TaxTypeController::class, 'destroy'])->name('masters.tax_types.destroy');
        Route::post('/tax-types/calculate', [\App\Http\Controllers\TaxTypeController::class, 'calculate'])->name('masters.tax_types.calculate');

        Route::get('/accounts', [\App\Http\Controllers\AccountController::class, 'index'])->name('masters.accounts.index');
        Route::post('/accounts', [\App\Http\Controllers\AccountController::class, 'store'])->name('masters.accounts.store');

        Route::get('/users', [UserController::class, 'index'])->name('masters.users.index');
        Route::post('/users', [UserController::class, 'store'])->name('masters.users.store');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('masters.users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('masters.users.destroy');
        Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('masters.users.toggle_status');
    });

    // Financial Years & Fiscal Period Locking
    Route::prefix('settings')->group(function () {
        Route::get('/financial-years', [\App\Http\Controllers\FinancialYearController::class, 'index']);
        Route::post('/financial-years', [\App\Http\Controllers\FinancialYearController::class, 'store']);
        Route::put('/fiscal-periods/{id}/status', [\App\Http\Controllers\FinancialYearController::class, 'updatePeriodStatus']);
    });

    // Accounts Payable (Purchase Orders & Vendor Bills)
    Route::prefix('payables')->group(function () {
        Route::get('/purchase-orders', [\App\Http\Controllers\PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [\App\Http\Controllers\PurchaseOrderController::class, 'store']);
        Route::get('/vendor-bills', [\App\Http\Controllers\VendorBillController::class, 'index']);
        Route::post('/vendor-bills', [\App\Http\Controllers\VendorBillController::class, 'store']);
    });

    // Bank Reconciliation & Treasury
    Route::prefix('treasury')->group(function () {
        Route::get('/bank-reconciliation', [\App\Http\Controllers\BankReconciliationController::class, 'index']);
        Route::post('/bank-reconciliation/auto-match', [\App\Http\Controllers\BankReconciliationController::class, 'autoMatch']);
    });

    // Fixed Assets & Depreciation
    Route::prefix('assets')->group(function () {
        Route::get('/fixed-assets', [\App\Http\Controllers\FixedAssetController::class, 'index']);
        Route::post('/fixed-assets', [\App\Http\Controllers\FixedAssetController::class, 'store']);
        Route::post('/fixed-assets/{id}/depreciate', [\App\Http\Controllers\FixedAssetController::class, 'runDepreciation']);
    });


    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);

    Route::get('/employees', [\App\Http\Controllers\EmployeeController::class, 'webIndex']);

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
    Route::put('/loans/{id}', [LoanController::class, 'update']);
    Route::post('/loans/{id}/activate', [LoanController::class, 'activate']);
    Route::post('/loans/{id}/status', [LoanController::class, 'updateStatus']);
    Route::post('/loans/{id}/schedule', [LoanController::class, 'addInterestSchedule']);
    Route::post('/loans/{id}/schedule/{scheduleId}/settle', [LoanController::class, 'settleInterestPeriod']);
    Route::post('/loans/{id}/schedule/{scheduleId}/edit', [LoanController::class, 'editInterestAmount']);
    Route::post('/loans/{id}/schedule/{scheduleId}/skip', [LoanController::class, 'markInterestNotNeeded']);
    Route::post('/loans/{id}/repayment', [LoanController::class, 'recordPrincipalRepayment']);
    Route::post('/loans/{id}/draw', [LoanController::class, 'addPrincipalDraw']);
    Route::post('/loans/{id}/settle-fully', [LoanController::class, 'settleFully']);
    Route::delete('/loans/{id}', [LoanController::class, 'destroy']);
    // Reminders Engine
    Route::get('/reminders', [\App\Http\Controllers\ReminderController::class, 'index']);
    Route::post('/reminders', [\App\Http\Controllers\ReminderController::class, 'store']);
    Route::post('/reminders/{id}/status', [\App\Http\Controllers\ReminderController::class, 'updateStatus']);
    Route::delete('/reminders/{id}', [\App\Http\Controllers\ReminderController::class, 'destroy']);

    // Approval Inbox
    Route::get('/approvals', [\App\Http\Controllers\ApprovalController::class, 'index']);
    Route::post('/approvals/{type}/{id}/approve', [\App\Http\Controllers\ApprovalController::class, 'approve']);
    Route::post('/approvals/{type}/{id}/reject', [\App\Http\Controllers\ApprovalController::class, 'reject']);

    // Cheques Tracking
    Route::get('/cheques', [\App\Http\Controllers\ChequeController::class, 'index']);
    Route::post('/cheques', [\App\Http\Controllers\ChequeController::class, 'store']);
    Route::post('/cheques/{id}/status', [\App\Http\Controllers\ChequeController::class, 'updateStatus']);
    Route::delete('/cheques/{id}', [\App\Http\Controllers\ChequeController::class, 'destroy']);

    // Audit Logs
    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index']);

    // Journal Entry Manual Post
    Route::post('/journal-entries', [\App\Http\Controllers\JournalEntryController::class, 'store']);

    Route::prefix('reports')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
        Route::get('/pnl', [\App\Http\Controllers\ReportController::class, 'pnl'])->name('reports.pnl');
        Route::get('/expenses', [\App\Http\Controllers\ReportController::class, 'expenses'])->name('reports.expenses');
        Route::get('/commissions', [\App\Http\Controllers\ReportController::class, 'commissions'])->name('reports.commissions');
        Route::get('/cost-allocations', [\App\Http\Controllers\ReportController::class, 'costAllocations'])->name('reports.cost_allocations');
        Route::get('/project-status', [\App\Http\Controllers\ReportController::class, 'projectStatus'])->name('reports.project_status');
        Route::get('/client-health', [\App\Http\Controllers\ReportController::class, 'clientHealth'])->name('reports.client_health');
        Route::get('/balance-sheet', [\App\Http\Controllers\ReportController::class, 'balanceSheet'])->name('reports.balance_sheet');
        Route::get('/cash-flow', [\App\Http\Controllers\ReportController::class, 'cashFlow'])->name('reports.cash_flow');
        Route::get('/ar-aging', [\App\Http\Controllers\ReportController::class, 'arAging'])->name('reports.ar_aging');
        Route::get('/project-profitability', [\App\Http\Controllers\ReportController::class, 'projectProfitability'])->name('reports.project_profitability');
        Route::get('/client-statement', [\App\Http\Controllers\ReportController::class, 'clientStatement'])->name('reports.client_statement');
        Route::get('/bank-reconciliation', [\App\Http\Controllers\ReportController::class, 'bankReconciliation'])->name('reports.bank_reconciliation');
        Route::get('/expense-trend', [\App\Http\Controllers\ReportController::class, 'expenseTrend'])->name('reports.expense_trend');
        Route::get('/party-ledger', [\App\Http\Controllers\ReportController::class, 'partyLedger'])->name('reports.party_ledger');
        Route::post('/party-ledger/settlement', [\App\Http\Controllers\ReportController::class, 'recordPartySettlement'])->name('reports.party_settlement');
    });


    // Cost Allocations & Integrations
    Route::prefix('api')->group(function () {
        Route::get('/employees', [\App\Http\Controllers\EmployeeController::class, 'index']);
        
        Route::get('/cost-allocations', [\App\Http\Controllers\CostAllocationController::class, 'index']);
        Route::post('/cost-allocations', [\App\Http\Controllers\CostAllocationController::class, 'store']);
        Route::delete('/cost-allocations/{costAllocation}', [\App\Http\Controllers\CostAllocationController::class, 'destroy']);
        
        Route::post('/api-integrations', [\App\Http\Controllers\ApiIntegrationController::class, 'store']);
        Route::post('/api-integrations/{apiIntegration}/sync', [\App\Http\Controllers\ApiIntegrationController::class, 'sync']);
    });

    // Run Migrations Route (Production Utility)
    Route::get('/run-migrations', function () {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'output' => \Illuminate\Support\Facades\Artisan::output()
        ]);
    });
});

Route::get('/migrate', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return response()->json([
        'status' => 'success',
        'output' => \Illuminate\Support\Facades\Artisan::output()
    ]);
});
