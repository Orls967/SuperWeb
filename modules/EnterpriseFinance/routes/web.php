<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EnterpriseFinance\Http\Controllers\AuditDataPackController;
use Modules\EnterpriseFinance\Http\Controllers\ComplianceCalendarController;
use Modules\EnterpriseFinance\Http\Controllers\CorporateTaxController;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseBudgetController;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceController;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceGovernanceController;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinancialStatementController;
use Modules\EnterpriseFinance\Http\Controllers\FinancialPeriodCloseController;
use Modules\EnterpriseFinance\Http\Controllers\InternalControlController;
use Modules\EnterpriseFinance\Http\Controllers\SodGovernanceController;

Route::middleware(['web', 'auth'])->prefix('enterprise-finance')->name('enterprise_finance.')->group(function () {
    // Rute Warisan / Kompatibilitas
    Route::middleware('role:admin,auditor,treasury')->group(function () {
        Route::get('/', [EnterpriseFinanceController::class, 'index'])->name('index');
        Route::get('/budget-lifecycle', [EnterpriseFinanceController::class, 'budgetLifecycle'])->name('budget_lifecycle');
        Route::get('/working-capital', [EnterpriseFinanceController::class, 'workingCapital'])->name('working_capital');
        Route::get('/sod-matrix', [EnterpriseFinanceController::class, 'sodMatrix'])->name('sod_matrix');
        Route::get('/tax-compliance', [EnterpriseFinanceController::class, 'taxCompliance'])->name('tax_compliance');
        Route::get('/compliance-calendar', [EnterpriseFinanceController::class, 'complianceCalendar'])->name('compliance_calendar');
        Route::get('/ma-goodwill', [EnterpriseFinanceController::class, 'maGoodwill'])->name('ma_goodwill');
        Route::get('/reports', [EnterpriseFinanceController::class, 'reports'])->name('reports');

        Route::prefix('governance')->name('governance.')->group(function () {
            Route::get('/', [EnterpriseFinanceGovernanceController::class, 'index'])->name('index');
            Route::post('/trial-balance/consolidate', [EnterpriseFinanceGovernanceController::class, 'consolidateTrialBalance'])->name('trial_balance.consolidate');
            Route::post('/budget/{budget}/enforce', [EnterpriseFinanceGovernanceController::class, 'enforceBudget'])->name('budget.enforce');
            Route::post('/covenants/monitor', [EnterpriseFinanceGovernanceController::class, 'monitorCovenants'])->name('covenants.monitor');
        });
    });

    // Rute Spesifikasi Fase 54 (Kelengkapan)
    Route::middleware('role:admin,budget_owner,finance_controller,auditor,treasury')->group(function () {
        Route::get('/budgets', [EnterpriseBudgetController::class, 'index'])->name('budgets'); // /enterprise-finance/budgets
        Route::get('/budgets/{id}/commitments', [EnterpriseBudgetController::class, 'commitments'])->name('budgets.commitments');
    });

    Route::middleware('role:admin,finance_controller,auditor')->group(function () {
        Route::get('/statements', [EnterpriseFinancialStatementController::class, 'index'])->name('statements'); // /enterprise-finance/statements
        Route::get('/tax', [CorporateTaxController::class, 'index'])->name('tax'); // /enterprise-finance/tax
        Route::post('/tax/settle', [CorporateTaxController::class, 'settle'])->name('tax.settle');
        Route::get('/sod', [SodGovernanceController::class, 'index'])->name('sod'); // /enterprise-finance/sod
        Route::post('/sod/exception', [SodGovernanceController::class, 'grantException'])->name('sod.exception');
        Route::get('/controls', [InternalControlController::class, 'index'])->name('controls'); // /enterprise-finance/controls
        Route::post('/controls/run', [InternalControlController::class, 'runTests'])->name('controls.run');
    });

    Route::middleware('role:admin,finance_controller')->group(function () {
        Route::get('/close', [FinancialPeriodCloseController::class, 'index'])->name('close'); // /enterprise-finance/close
        Route::post('/close', [FinancialPeriodCloseController::class, 'closePeriod'])->name('close.execute');
    });

    Route::middleware('role:admin,finance_controller,legal,auditor')->group(function () {
        Route::get('/compliance', [ComplianceCalendarController::class, 'index'])->name('compliance'); // /enterprise-finance/compliance
    });

    Route::middleware('role:admin,auditor,finance_controller')->group(function () {
        Route::get('/audit-pack', [AuditDataPackController::class, 'index'])->name('audit_pack'); // /enterprise-finance/audit-pack
        Route::post('/audit-pack/generate', [AuditDataPackController::class, 'generate'])->name('audit_pack.generate');
    });
});
