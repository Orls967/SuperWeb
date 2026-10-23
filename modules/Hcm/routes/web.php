<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hcm\Http\Controllers\GigEconomyController;
use Modules\Hcm\Http\Controllers\HcmController;

Route::middleware(['web', 'auth', 'role:admin,hcm_manager'])->prefix('hcm')->group(function () {
    Route::get('/', [HcmController::class, 'index'])->name('hcm.index');

    Route::prefix('bounties')->name('hcm.bounties.')->group(function () {
        Route::get('/', [GigEconomyController::class, 'index'])->name('index');
        Route::post('/', [GigEconomyController::class, 'store'])->name('store');
        Route::post('/{bounty}/claim', [GigEconomyController::class, 'claim'])->name('claim');
        Route::post('/claims/{claim}/payout', [GigEconomyController::class, 'payout'])->name('payout');
    });
});
