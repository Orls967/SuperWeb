<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Treasury\Http\Controllers\TreasuryController;
use Modules\Treasury\Http\Controllers\TreasuryOperationsController;

// Rute umum: dapat diakses oleh admin, auditor, treasury
Route::middleware(['web', 'auth', 'role:admin,auditor,treasury'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/', [TreasuryController::class, 'index'])->name('index');
    Route::get('/positions', [TreasuryController::class, 'positions'])->name('positions');
    Route::get('/forecast', [TreasuryController::class, 'forecast'])->name('forecast');
    Route::get('/facilities', [TreasuryController::class, 'facilities'])->name('facilities');

    // Operasi posting
    Route::post('/fx-revalue', [TreasuryOperationsController::class, 'revalueFx'])->name('fx_revalue');
    Route::post('/cash-pools/{cashPool}/sweep', [TreasuryOperationsController::class, 'sweepCashPool'])->name('sweep_cash_pool');
    Route::post('/forward-contracts/{contract}/settle', [TreasuryOperationsController::class, 'settleHedge'])->name('settle_hedge');
});

// Rute operasional terbatas (dealer/eksekutor): hanya admin dan treasury (auditor read-only / forbidden)
Route::middleware(['web', 'auth', 'role:admin,treasury'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/fx', [TreasuryController::class, 'fx'])->name('fx');
    Route::get('/reconciliation', [TreasuryController::class, 'reconciliation'])->name('reconciliation');
    Route::get('/hedges', [TreasuryController::class, 'hedges'])->name('hedges');
    Route::get('/pools', [TreasuryController::class, 'pools'])->name('pools');
});
