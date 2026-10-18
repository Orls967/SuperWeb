<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Proptech\Http\Controllers\BimTwinController;
use Modules\Proptech\Http\Controllers\ProptechController;

Route::middleware(['web', 'auth', 'role:admin,proptech_admin,facility_manager'])->prefix('proptech')->group(function () {
    Route::get('/', [ProptechController::class, 'index'])->name('proptech.index');
    Route::post('/zones/{zone}/bill', [ProptechController::class, 'generateBill'])->name('proptech.zones.bill');

    // Phase 77: BIM & Digital Twin Lifecycle
    Route::get('/bim', [BimTwinController::class, 'index'])->name('proptech.bim.index');
    Route::post('/bim/{model}/simulate', [BimTwinController::class, 'simulate'])->name('proptech.bim.simulate');
    Route::post('/bim/capitalize', [BimTwinController::class, 'capitalize'])->name('proptech.bim.capitalize');
});
