<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Treasury\Http\Controllers\TreasuryController;
use Modules\Treasury\Http\Controllers\TreasuryOperationsController;

Route::middleware(['web', 'auth', 'role:admin,auditor,treasury'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/', [TreasuryController::class, 'index'])->name('index');
    Route::post('/fx-revalue', [TreasuryOperationsController::class, 'revalueFx'])->name('fx_revalue');
    Route::post('/cash-pools/{cashPool}/sweep', [TreasuryOperationsController::class, 'sweepCashPool'])->name('sweep_cash_pool');
    Route::post('/forward-contracts/{contract}/settle', [TreasuryOperationsController::class, 'settleHedge'])->name('settle_hedge');
});
