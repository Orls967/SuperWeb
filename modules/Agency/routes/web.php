<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Agency\Http\Controllers\AgencyController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,agent,procurement,auditor'])
    ->prefix('agency')
    ->name('agency.')
    ->group(function () {
        Route::get('/', [AgencyController::class, 'index'])->name('index');
        Route::get('/{agent}', [AgencyController::class, 'show'])->name('show');
        Route::middleware('role:admin,procurement')->group(function () {
            Route::post('/agents', [AgencyController::class, 'storeAgent'])->name('agents.store');
            Route::post('/{agent}/transition', [AgencyController::class, 'transition'])->name('agents.transition');
            Route::post('/{agent}/schemes', [AgencyController::class, 'storeScheme'])->name('schemes.store');
            Route::post('/attributions', [AgencyController::class, 'recordAttribution'])->name('attributions.store');
            Route::post('/accruals', [AgencyController::class, 'accrueSale'])->name('accruals.store');
            Route::post('/accruals/clawback', [AgencyController::class, 'clawback'])->name('accruals.clawback');
            Route::post('/accruals/release-holds', [AgencyController::class, 'releaseHold'])->name('accruals.release');
            Route::post('/{agent}/payouts', [AgencyController::class, 'storePayout'])->name('payouts.store');
            Route::post('/payouts/{payout}/approve', [AgencyController::class, 'approvePayout'])->name('payouts.approve');
        });
    });
