<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| API Version 1 (v1) Foundation Routes
|--------------------------------------------------------------------------
|
| Standardized REST API foundation endpoints.
|
*/
Route::prefix('v1')->group(function () {
    Route::get('/ping', [\App\Http\Controllers\Api\V1\PingController::class, 'ping'])->name('api.v1.ping');

    // Admin Authentication Routes
    Route::prefix('admin/auth')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\AdminAuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('api.v1.admin.auth.login');

        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\V1\AdminAuthController::class, 'me'])
                ->name('api.v1.admin.auth.me');
            Route::post('/logout', [\App\Http\Controllers\Api\V1\AdminAuthController::class, 'logout'])
                ->name('api.v1.admin.auth.logout');
        });
    });

    // User / Student Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\UserAuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('api.v1.auth.login');

        Route::middleware(['auth:sanctum', 'user.identity'])->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\V1\UserAuthController::class, 'me'])
                ->name('api.v1.auth.me');
            Route::post('/logout', [\App\Http\Controllers\Api\V1\UserAuthController::class, 'logout'])
                ->name('api.v1.auth.logout');
        });
    });

    // Academic & Course Management Routes
    Route::prefix('academic')->group(function () {
        // Course Categories
        Route::get('/categories', [\App\Http\Controllers\Api\V1\CourseCategoryApiController::class, 'index'])->name('api.v1.academic.categories.index');
        Route::get('/categories/{id}', [\App\Http\Controllers\Api\V1\CourseCategoryApiController::class, 'show'])->name('api.v1.academic.categories.show');

        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::post('/categories', [\App\Http\Controllers\Api\V1\CourseCategoryApiController::class, 'store'])
                ->middleware('permission:course-create')
                ->name('api.v1.academic.categories.store');
            Route::put('/categories/{id}', [\App\Http\Controllers\Api\V1\CourseCategoryApiController::class, 'update'])
                ->middleware('permission:course-edit')
                ->name('api.v1.academic.categories.update');
            Route::delete('/categories/{id}', [\App\Http\Controllers\Api\V1\CourseCategoryApiController::class, 'destroy'])
                ->middleware('permission:course-delete')
                ->name('api.v1.academic.categories.destroy');
        });

        // Courses
        Route::get('/courses', [\App\Http\Controllers\Api\V1\CourseApiController::class, 'index'])->name('api.v1.academic.courses.index');
        Route::get('/courses/{id}', [\App\Http\Controllers\Api\V1\CourseApiController::class, 'show'])->name('api.v1.academic.courses.show');

        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::post('/courses', [\App\Http\Controllers\Api\V1\CourseApiController::class, 'store'])
                ->middleware('permission:course-create')
                ->name('api.v1.academic.courses.store');
            Route::put('/courses/{id}', [\App\Http\Controllers\Api\V1\CourseApiController::class, 'update'])
                ->middleware('permission:course-edit')
                ->name('api.v1.academic.courses.update');
            Route::delete('/courses/{id}', [\App\Http\Controllers\Api\V1\CourseApiController::class, 'destroy'])
                ->middleware('permission:course-delete')
                ->name('api.v1.academic.courses.destroy');
        });

        // Academic Sessions
        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::get('/sessions', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'index'])
                ->middleware('permission:session-list')
                ->name('api.v1.academic.sessions.index');
            Route::get('/sessions/{id}', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'show'])
                ->middleware('permission:session-list')
                ->name('api.v1.academic.sessions.show');
            Route::post('/sessions', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'store'])
                ->middleware('permission:session-create')
                ->name('api.v1.academic.sessions.store');
            Route::put('/sessions/{id}', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'update'])
                ->middleware('permission:session-edit')
                ->name('api.v1.academic.sessions.update');
            Route::delete('/sessions/{id}', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'destroy'])
                ->middleware('permission:session-delete')
                ->name('api.v1.academic.sessions.destroy');
        });

        // Batches
        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::get('/batches', [\App\Http\Controllers\Api\V1\BatchApiController::class, 'index'])
                ->middleware('permission:batch-list')
                ->name('api.v1.academic.batches.index');
            Route::get('/batches/{id}', [\App\Http\Controllers\Api\V1\BatchApiController::class, 'show'])
                ->middleware('permission:batch-list')
                ->name('api.v1.academic.batches.show');
            Route::post('/batches', [\App\Http\Controllers\Api\V1\BatchApiController::class, 'store'])
                ->middleware('permission:batch-create')
                ->name('api.v1.academic.batches.store');
            Route::put('/batches/{id}', [\App\Http\Controllers\Api\V1\BatchApiController::class, 'update'])
                ->middleware('permission:batch-edit')
                ->name('api.v1.academic.batches.update');
            Route::delete('/batches/{id}', [\App\Http\Controllers\Api\V1\BatchApiController::class, 'destroy'])
                ->middleware('permission:batch-delete')
                ->name('api.v1.academic.batches.destroy');
        });

        // Student Management Routes
        // Self-service student profile for authenticated student user
        Route::middleware(['auth:sanctum', 'user.identity'])->group(function () {
            Route::get('/students/me', [\App\Http\Controllers\Api\V1\StudentApiController::class, 'myRecord'])
                ->name('api.v1.academic.students.me');
        });

        // Individual student record show (accessible by Admin or owner Student User)
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/students/{id}', [\App\Http\Controllers\Api\V1\StudentApiController::class, 'show'])
                ->name('api.v1.academic.students.show');
        });

        // Administrative Student CRUD operations
        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::get('/students', [\App\Http\Controllers\Api\V1\StudentApiController::class, 'index'])
                ->middleware('permission:student-list')
                ->name('api.v1.academic.students.index');
            Route::post('/students', [\App\Http\Controllers\Api\V1\StudentApiController::class, 'store'])
                ->middleware('permission:student-create')
                ->name('api.v1.academic.students.store');
            Route::put('/students/{id}', [\App\Http\Controllers\Api\V1\StudentApiController::class, 'update'])
                ->middleware('permission:student-edit')
                ->name('api.v1.academic.students.update');
            Route::delete('/students/{id}', [\App\Http\Controllers\Api\V1\StudentApiController::class, 'destroy'])
                ->middleware('permission:student-delete')
                ->name('api.v1.academic.students.destroy');
        });

        // Online Admissions Routes
        // Public admission inquiry submission (rate-limited)
        Route::post('/admissions', [\App\Http\Controllers\Api\V1\AdmissionApiController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.v1.academic.admissions.store');

        // Administrative Admissions operations
        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::get('/admissions', [\App\Http\Controllers\Api\V1\AdmissionApiController::class, 'index'])
                ->middleware('permission:student-list')
                ->name('api.v1.academic.admissions.index');
            Route::get('/admissions/{id}', [\App\Http\Controllers\Api\V1\AdmissionApiController::class, 'show'])
                ->middleware('permission:student-list')
                ->name('api.v1.academic.admissions.show');
            Route::put('/admissions/{id}', [\App\Http\Controllers\Api\V1\AdmissionApiController::class, 'update'])
                ->middleware('permission:student-edit')
                ->name('api.v1.academic.admissions.update');
        });
    });

    // =========================================================================
    // FEES COLLECTION, TRANSACTIONS & BKASH RECONCILIATION ROUTES (Phase 2.5)
    // =========================================================================
    Route::prefix('fees')->group(function () {

        // Student Self-Service: view own fee balance & history
        Route::middleware(['auth:sanctum', 'user.identity'])->group(function () {
            Route::get('/my-balance', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'myBalance'])
                ->name('api.v1.fees.my-balance');
        });

        // Student or Admin access: balance, transaction detail, and receipt
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/students/{student_id}/balance', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'studentBalance'])
                ->name('api.v1.fees.students.balance');
            Route::get('/transactions/{id}', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'show'])
                ->name('api.v1.fees.transactions.show');
            Route::get('/transactions/{id}/receipt', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'receipt'])
                ->name('api.v1.fees.transactions.receipt');

            // bKash Payment Initiation & Server-Side Verification Callback
            Route::post('/bkash/create', [\App\Http\Controllers\Api\V1\BkashApiController::class, 'createPayment'])
                ->name('api.v1.fees.bkash.create');
            Route::post('/bkash/callback', [\App\Http\Controllers\Api\V1\BkashApiController::class, 'callback'])
                ->name('api.v1.fees.bkash.callback');
        });

        // Administrative Fee Management
        Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
            Route::get('/transactions', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'index'])
                ->middleware('permission:fees-list|fees-log')
                ->name('api.v1.fees.transactions.index');
            Route::post('/transactions', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'store'])
                ->middleware('permission:fees-create')
                ->name('api.v1.fees.transactions.store');
            Route::put('/transactions/{id}', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'update'])
                ->middleware('permission:fees-edit')
                ->name('api.v1.fees.transactions.update');
            Route::put('/transactions/{id}/status', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'updateStatus'])
                ->middleware('permission:fees-edit')
                ->name('api.v1.fees.transactions.status');
            Route::delete('/transactions/{id}', [\App\Http\Controllers\Api\V1\FeeTransactionApiController::class, 'destroy'])
                ->middleware('permission:fees-delete')
                ->name('api.v1.fees.transactions.destroy');

            // Administrative bKash Gateway Reconciliation Queries
            Route::get('/bkash/query/{paymentID}', [\App\Http\Controllers\Api\V1\BkashApiController::class, 'query'])
                ->middleware('permission:fees-list|fees-log')
                ->name('api.v1.fees.bkash.query');
            Route::get('/bkash/search/{trxID}', [\App\Http\Controllers\Api\V1\BkashApiController::class, 'search'])
                ->middleware('permission:fees-list|fees-log')
                ->name('api.v1.fees.bkash.search');
        });
    });

    // =========================================================================
    // INSTITUTIONAL ACCOUNTING & REPORTING ROUTES (Phase 2.6)
    // =========================================================================
    Route::prefix('accounting')->middleware(['auth:sanctum', 'admin.identity'])->group(function () {

        // Branches Management
        Route::get('/branches', [\App\Http\Controllers\Api\V1\BranchApiController::class, 'index'])
            ->name('api.v1.accounting.branches.index');
        Route::post('/branches', [\App\Http\Controllers\Api\V1\BranchApiController::class, 'store'])
            ->name('api.v1.accounting.branches.store');
        Route::get('/branches/{id}', [\App\Http\Controllers\Api\V1\BranchApiController::class, 'show'])
            ->name('api.v1.accounting.branches.show');
        Route::put('/branches/{id}', [\App\Http\Controllers\Api\V1\BranchApiController::class, 'update'])
            ->name('api.v1.accounting.branches.update');
        Route::delete('/branches/{id}', [\App\Http\Controllers\Api\V1\BranchApiController::class, 'destroy'])
            ->name('api.v1.accounting.branches.destroy');

        // Income Categories Management
        Route::get('/categories/income', [\App\Http\Controllers\Api\V1\IncomeCategoryApiController::class, 'index'])
            ->name('api.v1.accounting.categories.income.index');
        Route::post('/categories/income', [\App\Http\Controllers\Api\V1\IncomeCategoryApiController::class, 'store'])
            ->name('api.v1.accounting.categories.income.store');
        Route::get('/categories/income/{id}', [\App\Http\Controllers\Api\V1\IncomeCategoryApiController::class, 'show'])
            ->name('api.v1.accounting.categories.income.show');
        Route::put('/categories/income/{id}', [\App\Http\Controllers\Api\V1\IncomeCategoryApiController::class, 'update'])
            ->name('api.v1.accounting.categories.income.update');
        Route::delete('/categories/income/{id}', [\App\Http\Controllers\Api\V1\IncomeCategoryApiController::class, 'destroy'])
            ->name('api.v1.accounting.categories.income.destroy');

        // Expense Categories Management (Protected by actual Spatie permissions)
        Route::get('/categories/expense', [\App\Http\Controllers\Api\V1\ExpenseCategoryApiController::class, 'index'])
            ->middleware('permission:expanse-categoris-list')
            ->name('api.v1.accounting.categories.expense.index');
        Route::post('/categories/expense', [\App\Http\Controllers\Api\V1\ExpenseCategoryApiController::class, 'store'])
            ->middleware('permission:expanse-categoris-create')
            ->name('api.v1.accounting.categories.expense.store');
        Route::get('/categories/expense/{id}', [\App\Http\Controllers\Api\V1\ExpenseCategoryApiController::class, 'show'])
            ->middleware('permission:expanse-categoris-list')
            ->name('api.v1.accounting.categories.expense.show');
        Route::put('/categories/expense/{id}', [\App\Http\Controllers\Api\V1\ExpenseCategoryApiController::class, 'update'])
            ->middleware('permission:expanse-categoris-edit')
            ->name('api.v1.accounting.categories.expense.update');
        Route::delete('/categories/expense/{id}', [\App\Http\Controllers\Api\V1\ExpenseCategoryApiController::class, 'destroy'])
            ->middleware('permission:expanse-categoris-delete')
            ->name('api.v1.accounting.categories.expense.destroy');

        // Income Management
        Route::get('/incomes', [\App\Http\Controllers\Api\V1\IncomeApiController::class, 'index'])
            ->name('api.v1.accounting.incomes.index');
        Route::post('/incomes', [\App\Http\Controllers\Api\V1\IncomeApiController::class, 'store'])
            ->name('api.v1.accounting.incomes.store');
        Route::get('/incomes/{id}', [\App\Http\Controllers\Api\V1\IncomeApiController::class, 'show'])
            ->name('api.v1.accounting.incomes.show');
        Route::put('/incomes/{id}', [\App\Http\Controllers\Api\V1\IncomeApiController::class, 'update'])
            ->name('api.v1.accounting.incomes.update');
        Route::delete('/incomes/{id}', [\App\Http\Controllers\Api\V1\IncomeApiController::class, 'destroy'])
            ->name('api.v1.accounting.incomes.destroy');

        // Expense Management (Protected by actual Spatie permissions)
        Route::get('/expenses', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'index'])
            ->middleware('permission:expanse-list')
            ->name('api.v1.accounting.expenses.index');
        Route::post('/expenses', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'store'])
            ->middleware('permission:expanse-create')
            ->name('api.v1.accounting.expenses.store');
        Route::get('/expenses/{id}', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'show'])
            ->middleware('permission:expanse-list')
            ->name('api.v1.accounting.expenses.show');
        Route::put('/expenses/{id}', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'update'])
            ->middleware('permission:expanse-edit')
            ->name('api.v1.accounting.expenses.update');
        Route::delete('/expenses/{id}', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'destroy'])
            ->middleware('permission:expanse-delete')
            ->name('api.v1.accounting.expenses.destroy');

        // Reporting & Cash Reconciliation
        Route::get('/reports/daily-summary', [\App\Http\Controllers\Api\V1\AccountingReportApiController::class, 'dailySummary'])
            ->name('api.v1.accounting.reports.daily-summary');
        Route::get('/reports/expense-by-category', [\App\Http\Controllers\Api\V1\AccountingReportApiController::class, 'expenseByCategory'])
            ->middleware('permission:expanse-list')
            ->name('api.v1.accounting.reports.expense-by-category');
        Route::get('/reports/income-by-category', [\App\Http\Controllers\Api\V1\AccountingReportApiController::class, 'incomeByCategory'])
            ->name('api.v1.accounting.reports.income-by-category');
        Route::get('/reports/monthly-summary', [\App\Http\Controllers\Api\V1\AccountingReportApiController::class, 'monthlySummary'])
            ->name('api.v1.accounting.reports.monthly-summary');
        Route::get('/reports/category-details/{type}/{categoryId}', [\App\Http\Controllers\Api\V1\AccountingReportApiController::class, 'categoryDetails'])
            ->name('api.v1.accounting.reports.category-details');
    });

    // =========================================================================
    // SHAREHOLDERS & CAPITAL LEDGER ROUTES (Phase 2.7.1)
    // =========================================================================
    Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
        // Shareholders Management
        Route::get('/shareholders', [\App\Http\Controllers\Api\V1\ShareholderApiController::class, 'index'])
            ->name('api.v1.shareholders.index');
        Route::post('/shareholders', [\App\Http\Controllers\Api\V1\ShareholderApiController::class, 'store'])
            ->name('api.v1.shareholders.store');
        Route::get('/shareholders/{id}', [\App\Http\Controllers\Api\V1\ShareholderApiController::class, 'show'])
            ->name('api.v1.shareholders.show');
        Route::put('/shareholders/{id}', [\App\Http\Controllers\Api\V1\ShareholderApiController::class, 'update'])
            ->name('api.v1.shareholders.update');
        Route::delete('/shareholders/{id}', [\App\Http\Controllers\Api\V1\ShareholderApiController::class, 'destroy'])
            ->name('api.v1.shareholders.destroy');
        Route::get('/shareholders/{id}/statement', [\App\Http\Controllers\Api\V1\ShareholderApiController::class, 'statement'])
            ->name('api.v1.shareholders.statement');

        // Capital Investments Management
        Route::get('/investments', [\App\Http\Controllers\Api\V1\InvestmentApiController::class, 'index'])
            ->name('api.v1.investments.index');
        Route::post('/investments', [\App\Http\Controllers\Api\V1\InvestmentApiController::class, 'store'])
            ->name('api.v1.investments.store');
        Route::get('/investments/{id}', [\App\Http\Controllers\Api\V1\InvestmentApiController::class, 'show'])
            ->name('api.v1.investments.show');
        Route::put('/investments/{id}', [\App\Http\Controllers\Api\V1\InvestmentApiController::class, 'update'])
            ->name('api.v1.investments.update');
        Route::delete('/investments/{id}', [\App\Http\Controllers\Api\V1\InvestmentApiController::class, 'destroy'])
            ->name('api.v1.investments.destroy');
    });

    // =========================================================================
    // WITHDRAWAL ROUTES (Phase 2.7.2)
    // =========================================================================
    Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
        // Capital Investment Withdrawals
        Route::get('/withdrawals/investment', [\App\Http\Controllers\Api\V1\InvestmentWithdrawApiController::class, 'index'])
            ->name('api.v1.withdrawals.investment.index');
        Route::post('/withdrawals/investment', [\App\Http\Controllers\Api\V1\InvestmentWithdrawApiController::class, 'store'])
            ->name('api.v1.withdrawals.investment.store');
        Route::get('/withdrawals/investment/{id}', [\App\Http\Controllers\Api\V1\InvestmentWithdrawApiController::class, 'show'])
            ->name('api.v1.withdrawals.investment.show');
        Route::put('/withdrawals/investment/{id}', [\App\Http\Controllers\Api\V1\InvestmentWithdrawApiController::class, 'update'])
            ->name('api.v1.withdrawals.investment.update');
        Route::delete('/withdrawals/investment/{id}', [\App\Http\Controllers\Api\V1\InvestmentWithdrawApiController::class, 'destroy'])
            ->name('api.v1.withdrawals.investment.destroy');
        Route::post('/withdrawals/investment/{id}/approve', [\App\Http\Controllers\Api\V1\InvestmentWithdrawApiController::class, 'approve'])
            ->name('api.v1.withdrawals.investment.approve');
        Route::post('/withdrawals/investment/{id}/reject', [\App\Http\Controllers\Api\V1\InvestmentWithdrawApiController::class, 'reject'])
            ->name('api.v1.withdrawals.investment.reject');

        // Shareholder Dividend Withdrawals
        Route::get('/withdrawals/dividend', [\App\Http\Controllers\Api\V1\DividendWithdrawApiController::class, 'index'])
            ->name('api.v1.withdrawals.dividend.index');
        Route::post('/withdrawals/dividend', [\App\Http\Controllers\Api\V1\DividendWithdrawApiController::class, 'store'])
            ->name('api.v1.withdrawals.dividend.store');
        Route::get('/withdrawals/dividend/{id}', [\App\Http\Controllers\Api\V1\DividendWithdrawApiController::class, 'show'])
            ->name('api.v1.withdrawals.dividend.show');
        Route::put('/withdrawals/dividend/{id}', [\App\Http\Controllers\Api\V1\DividendWithdrawApiController::class, 'update'])
            ->name('api.v1.withdrawals.dividend.update');
        Route::delete('/withdrawals/dividend/{id}', [\App\Http\Controllers\Api\V1\DividendWithdrawApiController::class, 'destroy'])
            ->name('api.v1.withdrawals.dividend.destroy');
        Route::post('/withdrawals/dividend/{id}/approve', [\App\Http\Controllers\Api\V1\DividendWithdrawApiController::class, 'approve'])
            ->name('api.v1.withdrawals.dividend.approve');
        Route::post('/withdrawals/dividend/{id}/reject', [\App\Http\Controllers\Api\V1\DividendWithdrawApiController::class, 'reject'])
            ->name('api.v1.withdrawals.dividend.reject');
    });

    // =========================================================================
    // BANK LOANS & EMI ROUTES (Phase 2.7.3)
    // =========================================================================
    Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
        // Bank Loans Management
        Route::get('/loans/bank-loans', [\App\Http\Controllers\Api\V1\BankLoanApiController::class, 'index'])
            ->name('api.v1.loans.bank-loans.index');
        Route::post('/loans/bank-loans', [\App\Http\Controllers\Api\V1\BankLoanApiController::class, 'store'])
            ->name('api.v1.loans.bank-loans.store');
        Route::get('/loans/bank-loans/{id}', [\App\Http\Controllers\Api\V1\BankLoanApiController::class, 'show'])
            ->name('api.v1.loans.bank-loans.show');
        Route::put('/loans/bank-loans/{id}', [\App\Http\Controllers\Api\V1\BankLoanApiController::class, 'update'])
            ->name('api.v1.loans.bank-loans.update');
        Route::delete('/loans/bank-loans/{id}', [\App\Http\Controllers\Api\V1\BankLoanApiController::class, 'destroy'])
            ->name('api.v1.loans.bank-loans.destroy');
        Route::get('/loans/bank-loans/{id}/statement', [\App\Http\Controllers\Api\V1\BankLoanApiController::class, 'statement'])
            ->name('api.v1.loans.bank-loans.statement');
        Route::post('/loans/bank-loans/{id}/generate-schedule', [\App\Http\Controllers\Api\V1\BankLoanApiController::class, 'generateSchedule'])
            ->name('api.v1.loans.bank-loans.generate-schedule');

        // EMI Installments Management
        Route::get('/loans/installments', [\App\Http\Controllers\Api\V1\EmiInstallmentApiController::class, 'index'])
            ->name('api.v1.loans.installments.index');
        Route::post('/loans/installments', [\App\Http\Controllers\Api\V1\EmiInstallmentApiController::class, 'store'])
            ->name('api.v1.loans.installments.store');
        Route::get('/loans/installments/{id}', [\App\Http\Controllers\Api\V1\EmiInstallmentApiController::class, 'show'])
            ->name('api.v1.loans.installments.show');
        Route::put('/loans/installments/{id}', [\App\Http\Controllers\Api\V1\EmiInstallmentApiController::class, 'update'])
            ->name('api.v1.loans.installments.update');
        Route::delete('/loans/installments/{id}', [\App\Http\Controllers\Api\V1\EmiInstallmentApiController::class, 'destroy'])
            ->name('api.v1.loans.installments.destroy');
        Route::post('/loans/installments/{id}/pay', [\App\Http\Controllers\Api\V1\EmiInstallmentApiController::class, 'pay'])
            ->name('api.v1.loans.installments.pay');
    });

    // =========================================================================
    // ASSET / SEIP GOVERNMENT PROJECTS ROUTES (Phase 2.7.4)
    // =========================================================================
    // Public online candidate intake
    Route::post('/projects/public-register', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'publicRegister'])
        ->name('api.v1.projects.public-register');

    Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
        // Project Overview & Summary Reporting
        Route::get('/projects/reports/summary', [\App\Http\Controllers\Api\V1\AssetProjectApiController::class, 'summaryReport'])
            ->name('api.v1.projects.reports.summary');

        // Project Showcase / Entities Management
        Route::get('/projects/assets', [\App\Http\Controllers\Api\V1\AssetProjectApiController::class, 'index'])
            ->name('api.v1.projects.assets.index');
        Route::post('/projects/assets', [\App\Http\Controllers\Api\V1\AssetProjectApiController::class, 'store'])
            ->name('api.v1.projects.assets.store');
        Route::get('/projects/assets/{id}', [\App\Http\Controllers\Api\V1\AssetProjectApiController::class, 'show'])
            ->name('api.v1.projects.assets.show');
        Route::put('/projects/assets/{id}', [\App\Http\Controllers\Api\V1\AssetProjectApiController::class, 'update'])
            ->name('api.v1.projects.assets.update');
        Route::delete('/projects/assets/{id}', [\App\Http\Controllers\Api\V1\AssetProjectApiController::class, 'destroy'])
            ->name('api.v1.projects.assets.destroy');

        // Project Training Courses
        Route::get('/projects/courses', [\App\Http\Controllers\Api\V1\AssetCourseApiController::class, 'index'])
            ->name('api.v1.projects.courses.index');
        Route::post('/projects/courses', [\App\Http\Controllers\Api\V1\AssetCourseApiController::class, 'store'])
            ->name('api.v1.projects.courses.store');
        Route::get('/projects/courses/{id}', [\App\Http\Controllers\Api\V1\AssetCourseApiController::class, 'show'])
            ->name('api.v1.projects.courses.show');
        Route::put('/projects/courses/{id}', [\App\Http\Controllers\Api\V1\AssetCourseApiController::class, 'update'])
            ->name('api.v1.projects.courses.update');
        Route::delete('/projects/courses/{id}', [\App\Http\Controllers\Api\V1\AssetCourseApiController::class, 'destroy'])
            ->name('api.v1.projects.courses.destroy');

        // Trainees Management & Lifecycle Tracking
        Route::get('/projects/trainees', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'index'])
            ->name('api.v1.projects.trainees.index');
        Route::post('/projects/trainees', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'store'])
            ->name('api.v1.projects.trainees.store');
        Route::get('/projects/trainees/{id}', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'show'])
            ->name('api.v1.projects.trainees.show');
        Route::put('/projects/trainees/{id}', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'update'])
            ->name('api.v1.projects.trainees.update');
        Route::delete('/projects/trainees/{id}', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'destroy'])
            ->name('api.v1.projects.trainees.destroy');

        // Qualification Status, Call Types & Assessment Feedbacks
        Route::post('/projects/trainees/{id}/status', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'updateStatus'])
            ->name('api.v1.projects.trainees.status');
        Route::post('/projects/trainees/{id}/call-type', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'updateCallType'])
            ->name('api.v1.projects.trainees.call-type');
        Route::get('/projects/trainees/{id}/feedbacks', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'getFeedbacks'])
            ->name('api.v1.projects.trainees.feedbacks.index');
        Route::post('/projects/trainees/{id}/feedbacks', [\App\Http\Controllers\Api\V1\AssetTraineeApiController::class, 'storeFeedback'])
            ->name('api.v1.projects.trainees.feedbacks.store');
    });

    // =========================================================================
    // CERTIFICATES & VERIFICATION ROUTES (Phase 2.8)
    // =========================================================================
    // Public Certificate Verification (Unauthenticated)
    Route::get('/certificates/verify/{certificateNo?}', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'verify'])
        ->name('api.v1.certificates.verify');

    Route::middleware(['auth:sanctum', 'admin.identity'])->group(function () {
        // Summary & Reports
        Route::get('/certificates/reports/summary', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'summaryReport'])
            ->name('api.v1.certificates.reports.summary');

        // Student Eligibility Assessment
        Route::get('/certificates/eligibility/{studentId}', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'eligibility'])
            ->where('studentId', '[0-9]+')
            ->name('api.v1.certificates.eligibility');

        // BTEB Board Certificates Management
        Route::get('/certificates/bteb', [\App\Http\Controllers\Api\V1\BtebCertificateApiController::class, 'index'])
            ->name('api.v1.certificates.bteb.index');
        Route::post('/certificates/bteb', [\App\Http\Controllers\Api\V1\BtebCertificateApiController::class, 'store'])
            ->name('api.v1.certificates.bteb.store');
        Route::get('/certificates/bteb/{id}', [\App\Http\Controllers\Api\V1\BtebCertificateApiController::class, 'show'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.bteb.show');
        Route::put('/certificates/bteb/{id}', [\App\Http\Controllers\Api\V1\BtebCertificateApiController::class, 'update'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.bteb.update');
        Route::delete('/certificates/bteb/{id}', [\App\Http\Controllers\Api\V1\BtebCertificateApiController::class, 'destroy'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.bteb.destroy');

        // Institutional (ICBD) Certificates Management
        Route::get('/certificates', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'index'])
            ->name('api.v1.certificates.index');
        Route::post('/certificates', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'store'])
            ->name('api.v1.certificates.store');
        Route::get('/certificates/{id}', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'show'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.show');
        Route::put('/certificates/{id}', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'update'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.update');
        Route::delete('/certificates/{id}', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'destroy'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.destroy');
        Route::post('/certificates/{id}/status', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'updateStatus'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.status');
        Route::get('/certificates/{id}/qr', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'qr'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.qr');
        Route::get('/certificates/{id}/print', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'print'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.print');
        Route::get('/certificates/{id}/pdf', [\App\Http\Controllers\Api\V1\CertificateApiController::class, 'pdf'])
            ->where('id', '[0-9]+')
            ->name('api.v1.certificates.pdf');
    });
});



