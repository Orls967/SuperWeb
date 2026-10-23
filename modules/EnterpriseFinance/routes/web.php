<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceController;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceGovernanceController;

Route::middleware(['web', 'auth', 'role:admin,auditor,treasury'])->prefix('enterprise-finance')->name('enterprise_finance.')->group(function () {
    Route::get('/', [EnterpriseFinanceController::class, 'index'])->name('index');
    Route::get('/budgets', [EnterpriseFinanceController::class, 'index'])->name('budgets');

    Route::prefix('governance')->name('governance.')->group(function () {
        Route::get('/', [EnterpriseFinanceGovernanceController::class, 'index'])->name('index');
        Route::post('/trial-balance/consolidate', [EnterpriseFinanceGovernanceController::class, 'consolidateTrialBalance'])->name('trial_balance.consolidate');
        Route::post('/budget/{budget}/enforce', [EnterpriseFinanceGovernanceController::class, 'enforceBudget'])->name('budget.enforce');
        Route::post('/covenants/monitor', [EnterpriseFinanceGovernanceController::class, 'monitorCovenants'])->name('covenants.monitor');
    });
});
