<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hotel\Http\Controllers\HotelBanquetTimesharePackageController;
use Modules\Hotel\Http\Controllers\HotelController;
use Modules\Hotel\Http\Controllers\HotelNightAuditFolioController;
use Modules\Hotel\Http\Controllers\HotelYieldAndOverbookingController;

Route::middleware(['web', 'auth', 'role:admin,hotel_admin,front_desk'])->prefix('hotel')->group(function () {
    Route::get('/', [HotelController::class, 'index'])->name('hotel.index');
    Route::post('/reservations/{reservation}/settle', [HotelController::class, 'settleFolio'])->name('hotel.reservations.settle');

    // Advanced PMS Routes (Fase 91-92)
    Route::prefix('pms')->name('hotel.pms.')->group(function () {
        Route::get('/reservations', [HotelYieldAndOverbookingController::class, 'reservations'])->name('reservations');
        Route::post('/reservations/book', [HotelYieldAndOverbookingController::class, 'bookRoom'])->name('reservations.book');

        Route::get('/yield-rates', [HotelYieldAndOverbookingController::class, 'yieldRates'])->name('yield-rates');
        Route::post('/yield-rates/calculate', [HotelYieldAndOverbookingController::class, 'calcRate'])->name('yield-rates.calculate');

        Route::get('/overbooking', [HotelYieldAndOverbookingController::class, 'overbookingWalk'])->name('overbooking');
        Route::post('/overbooking/walk', [HotelYieldAndOverbookingController::class, 'executeWalk'])->name('overbooking.walk');

        Route::get('/night-audit', [HotelNightAuditFolioController::class, 'nightAudit'])->name('night-audit');
        Route::post('/night-audit/run', [HotelNightAuditFolioController::class, 'runNightAudit'])->name('night-audit.run');

        Route::get('/split-folios', [HotelNightAuditFolioController::class, 'splitFolios'])->name('split-folios');
        Route::post('/split-folios/settle', [HotelNightAuditFolioController::class, 'settleSplitFolio'])->name('split-folios.settle');

        Route::get('/banquet', [HotelBanquetTimesharePackageController::class, 'banquetOrders'])->name('banquet');
        Route::post('/banquet/create', [HotelBanquetTimesharePackageController::class, 'createBanquetOrder'])->name('banquet.create');

        Route::get('/timeshare', [HotelBanquetTimesharePackageController::class, 'timeshare'])->name('timeshare');
        Route::post('/timeshare/mint', [HotelBanquetTimesharePackageController::class, 'mintTimeshare'])->name('timeshare.mint');

        Route::get('/destination-packages', [HotelBanquetTimesharePackageController::class, 'destinationPackages'])->name('destination-packages');
        Route::post('/destination-packages/settle', [HotelBanquetTimesharePackageController::class, 'settlePackage'])->name('destination-packages.settle');
    });
});
