<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceController;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceGovernanceController;

Route::middleware(['web', 'auth', 'role:admin,auditor,treasury'])->prefix('enterprise-finance')->name('enterprise_finance.')->group(function () {
    Route::get('/', [EnterpriseFinanceController::class, 'index'])->name('index');
    Route::get('/budgets', [EnterpriseFinanceController::class, 'index'])->name('budgets');
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
