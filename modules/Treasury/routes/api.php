<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Treasury\Http\Controllers\TreasuryApiController;

Route::middleware(['api', 'auth', 'role:admin,treasury'])->prefix('api/treasury')->name('api.treasury.')->group(function () {
    Route::post('/fx-transfers', [TreasuryApiController::class, 'postFxTransfer'])->name('fx_transfer');
    Route::get('/hedges/{contract}', [TreasuryApiController::class, 'getHedge'])->name('get_hedge');
    Route::post('/facilities/{facility}/draw', [TreasuryApiController::class, 'drawFacility'])->name('draw_facility');
});
