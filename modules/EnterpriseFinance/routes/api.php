<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceApiController;

Route::middleware(['api', 'auth', 'role:admin,treasury'])->prefix('api/enterprise-finance')->name('api.enterprise_finance.')->group(function () {
    Route::post('/budgets/{budget}/spend', [EnterpriseFinanceApiController::class, 'spendBudget'])->name('budgets.spend');
    Route::post('/capex/{code}/approve', [EnterpriseFinanceApiController::class, 'approveCapex'])->name('capex.approve');
    Route::post('/covenants/{facility}/evaluate', [EnterpriseFinanceApiController::class, 'evaluateCovenants'])->name('covenants.evaluate');
    Route::post('/trial-balance/consolidate', [EnterpriseFinanceApiController::class, 'consolidateTrialBalance'])->name('trial_balance.consolidate');
    Route::post('/sod/check', [EnterpriseFinanceApiController::class, 'checkSod'])->name('sod.check');
});
