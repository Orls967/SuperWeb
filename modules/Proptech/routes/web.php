<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Proptech\Http\Controllers\BimTwinController;
use Modules\Proptech\Http\Controllers\FlexSpaceController;
use Modules\Proptech\Http\Controllers\ProptechController;
use Modules\Proptech\Http\Controllers\ProptechIntelligenceController;

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

    // Deepened Intelligence Routes (Fase 76-78)
    Route::prefix('intelligence')->name('proptech.intelligence.')->group(function () {
        Route::get('/bms', [ProptechIntelligenceController::class, 'bmsOptimization'])->name('bms');
        Route::post('/bms/optimize', [ProptechIntelligenceController::class, 'optimizeBms'])->name('bms.optimize');

        Route::get('/retrofit', [ProptechIntelligenceController::class, 'retrofitRoi'])->name('retrofit');
        Route::post('/retrofit/calculate', [ProptechIntelligenceController::class, 'calcRetrofit'])->name('retrofit.calculate');

        Route::get('/epc-handover', [ProptechIntelligenceController::class, 'epcHandover'])->name('epc-handover');
        Route::post('/epc-handover', [ProptechIntelligenceController::class, 'handoverBim'])->name('epc-handover.handover');

        Route::get('/evacuation', [ProptechIntelligenceController::class, 'evacuationSim'])->name('evacuation');
        Route::post('/evacuation/run', [ProptechIntelligenceController::class, 'runSim'])->name('evacuation.run');

        Route::get('/flex-booking', [ProptechIntelligenceController::class, 'flexBooking'])->name('flex-booking');
        Route::post('/flex-booking', [ProptechIntelligenceController::class, 'storeFlexBooking'])->name('flex-booking.store');

        Route::get('/smart-lock', [ProptechIntelligenceController::class, 'smartLockPins'])->name('smart-lock');
        Route::post('/smart-lock/generate', [ProptechIntelligenceController::class, 'generatePin'])->name('smart-lock.generate');
    });
});
