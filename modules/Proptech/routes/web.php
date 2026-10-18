<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Proptech\Http\Controllers\BimTwinController;
use Modules\Proptech\Http\Controllers\FlexSpaceController;
use Modules\Proptech\Http\Controllers\ProptechController;

Route::middleware(['web', 'auth', 'role:admin,proptech_admin,facility_manager'])->prefix('proptech')->group(function () {
    Route::get('/', [ProptechController::class, 'index'])->name('proptech.index');
    Route::post('/zones/{zone}/bill', [ProptechController::class, 'generateBill'])->name('proptech.zones.bill');

    // Phase 77: BIM & Digital Twin Lifecycle
    Route::get('/bim', [BimTwinController::class, 'index'])->name('proptech.bim.index');
    Route::post('/bim/{model}/simulate', [BimTwinController::class, 'simulate'])->name('proptech.bim.simulate');
    Route::post('/bim/capitalize', [BimTwinController::class, 'capitalize'])->name('proptech.bim.capitalize');

    // Phase 78: Flex-Space & Co-Working On-Demand
    Route::get('/flex', [FlexSpaceController::class, 'index'])->name('proptech.flex.index');
    Route::post('/flex/book', [FlexSpaceController::class, 'store'])->name('proptech.flex.store');
    Route::post('/flex/{booking}/check-in', [FlexSpaceController::class, 'checkIn'])->name('proptech.flex.checkin');
    Route::post('/flex/{booking}/no-show', [FlexSpaceController::class, 'noShow'])->name('proptech.flex.noshow');
});
