<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Wealth\Http\Controllers\WealthController;
use Modules\Wealth\Http\Controllers\WealthTreasuryManagementController;

Route::middleware(['web', 'auth', 'role:admin,wealth_manager,treasury,investor,customer'])->prefix('wealth')->group(function () {
    Route::get('/', [WealthController::class, 'index'])->name('wealth.index');
    Route::post('/profiles/{profile}/invest', [WealthController::class, 'invest'])->name('wealth.profiles.invest');

    // Robo-Advisor & Corporate Treasury Deepening Routes
    Route::prefix('treasury')->name('wealth.treasury.')->group(function () {
        Route::get('/risk-profiles', [WealthTreasuryManagementController::class, 'riskProfiles'])->name('risk-profiles');
        Route::get('/mpt-allocation', [WealthTreasuryManagementController::class, 'mptAllocation'])->name('mpt-allocation');
        Route::get('/portfolio-rebalancing', [WealthTreasuryManagementController::class, 'portfolioRebalancing'])->name('portfolio-rebalancing');
        Route::get('/cash-sweep', [WealthTreasuryManagementController::class, 'cashSweep'])->name('cash-sweep');
        Route::post('/compute-profile', [WealthTreasuryManagementController::class, 'computeProfile'])->name('compute-profile');
        Route::post('/allocate-mpt', [WealthTreasuryManagementController::class, 'allocateMpt'])->name('allocate-mpt');
        Route::post('/rebalance', [WealthTreasuryManagementController::class, 'rebalance'])->name('rebalance');
        Route::post('/sweep-cash', [WealthTreasuryManagementController::class, 'sweepCash'])->name('sweep-cash');
    });
});
