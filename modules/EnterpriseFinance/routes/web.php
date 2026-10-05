<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EnterpriseFinance\Http\Controllers\EnterpriseFinanceController;

Route::middleware(['web', 'auth'])->prefix('enterprise-finance')->name('enterprise_finance.')->group(function () {
    Route::get('/', [EnterpriseFinanceController::class, 'index'])->name('index');
    Route::get('/budgets', [EnterpriseFinanceController::class, 'index'])->name('budgets');
});
