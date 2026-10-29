<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hotel\Http\Controllers\HotelBanquetTimesharePackageController;
use Modules\Hotel\Http\Controllers\HotelController;
use Modules\Hotel\Http\Controllers\HotelCorporatePortalController;
use Modules\Hotel\Http\Controllers\HotelGuestPortalController;
use Modules\Hotel\Http\Controllers\HotelHousekeepingController;
use Modules\Hotel\Http\Controllers\HotelNightAuditFolioController;
use Modules\Hotel\Http\Controllers\HotelNightAuditUiController;
use Modules\Hotel\Http\Controllers\HotelReservationUiController;
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

// Siklus Tamu - Front Office & Reservation
Route::middleware(['web', 'auth', 'role:admin,hotel_manager,front_office,reservation_agent,hotel_admin,front_desk'])->prefix('hotel')->group(function () {
    Route::get('/reservations', [HotelReservationUiController::class, 'index'])->name('hotel.reservations.index');
    Route::post('/reservations', [HotelReservationUiController::class, 'store'])->name('hotel.reservations.store');
});

// Siklus Tamu - Housekeeping Board & Status Updates
Route::middleware(['web', 'auth', 'role:admin,hotel_manager,front_office,housekeeping'])->prefix('hotel')->group(function () {
    Route::get('/housekeeping', [HotelHousekeepingController::class, 'index'])->name('hotel.housekeeping.index');
    Route::post('/housekeeping/{room}/status', [HotelHousekeepingController::class, 'updateStatus'])->name('hotel.housekeeping.update');
});

// Siklus Tamu - Night Audit Operasional
Route::middleware(['web', 'auth', 'role:admin,hotel_manager,night_auditor'])->prefix('hotel')->group(function () {
    Route::get('/night-audit', [HotelNightAuditUiController::class, 'index'])->name('hotel.night-audit.index');
    Route::post('/night-audit/run', [HotelNightAuditUiController::class, 'run'])->name('hotel.night-audit.run');
});

// Siklus Tamu - Portal Tamu (Self-Service)
Route::middleware(['web', 'auth', 'role:guest,customer,admin'])->prefix('hotel')->group(function () {
    Route::get('/portal/guest', [HotelGuestPortalController::class, 'index'])->name('hotel.portal.guest');
});

// Siklus Tamu - Portal Korporat (City Ledger)
Route::middleware(['web', 'auth', 'role:corporate_client,admin'])->prefix('hotel')->group(function () {
    Route::get('/portal/corporate', [HotelCorporatePortalController::class, 'index'])->name('hotel.portal.corporate');
});
