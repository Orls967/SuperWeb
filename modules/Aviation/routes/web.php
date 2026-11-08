<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Aviation\Http\Controllers\AviationBookingController;
use Modules\Aviation\Http\Controllers\AviationCargoController;
use Modules\Aviation\Http\Controllers\AviationCodeshareController;
use Modules\Aviation\Http\Controllers\AviationDispatchController;
use Modules\Aviation\Http\Controllers\AviationFleetController;
use Modules\Aviation\Http\Controllers\AviationIrropsController;
use Modules\Aviation\Http\Controllers\AviationLoyaltyController;
use Modules\Aviation\Http\Controllers\AviationOpsController;
use Modules\Aviation\Http\Controllers\AviationPassengerPortalController;
use Modules\Aviation\Http\Controllers\AviationReportsController;
use Modules\Aviation\Http\Controllers\AviationSlotController;
use Modules\Aviation\Http\Controllers\AviationYieldController;

/*
|--------------------------------------------------------------------------
| Web Routes - Modul Aviation (Fase 177)
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function (): void {
    // Flight Operations Network Control
    Route::middleware(['role:admin,ops_controller,superadmin'])->group(function (): void {
        Route::get('/aviation/ops', [AviationOpsController::class, 'index'])->name('aviation.ops.index');
    });

    // Slot Coordination
    Route::middleware(['role:admin,station_manager,superadmin'])->group(function (): void {
        Route::get('/aviation/slots', [AviationSlotController::class, 'index'])->name('aviation.slots.index');
        Route::post('/aviation/slots/request', [AviationSlotController::class, 'requestSlot'])->name('aviation.slots.request');
    });

    // Fleet & Maintenance
    Route::middleware(['role:admin,maintenance_planner,superadmin'])->group(function (): void {
        Route::get('/aviation/fleet', [AviationFleetController::class, 'index'])->name('aviation.fleet.index');
    });

    // Flight Dispatch & Gate
    Route::middleware(['role:admin,dispatcher,ops_controller,superadmin'])->group(function (): void {
        Route::get('/aviation/dispatch', [AviationDispatchController::class, 'index'])->name('aviation.dispatch.index');
        Route::post('/aviation/dispatch/{flight}/execute', [AviationDispatchController::class, 'executeDispatch'])->name('aviation.dispatch.execute');
        Route::post('/aviation/dispatch/{flight}/depart', [AviationDispatchController::class, 'departFlight'])->name('aviation.dispatch.depart');
    });

    // Air Cargo & DG
    Route::middleware(['role:admin,cargo_agent,superadmin'])->group(function (): void {
        Route::get('/aviation/cargo', [AviationCargoController::class, 'index'])->name('aviation.cargo.index');
        Route::post('/aviation/cargo/receive', [AviationCargoController::class, 'receiveCargo'])->name('aviation.cargo.receive');
    });

    // Bookings & Reservations
    Route::middleware(['role:admin,customer_care,superadmin'])->group(function (): void {
        Route::get('/aviation/bookings', [AviationBookingController::class, 'index'])->name('aviation.bookings.index');
        Route::post('/aviation/bookings/confirm', [AviationBookingController::class, 'confirmBooking'])->name('aviation.bookings.confirm');
        Route::post('/aviation/bookings/{ticket}/refund', [AviationBookingController::class, 'refundTicket'])->name('aviation.bookings.refund');
    });

    // Hold Seat (Customer care & Passenger)
    Route::middleware(['role:admin,customer_care,passenger,superadmin'])->group(function (): void {
        Route::post('/aviation/bookings/hold', [AviationBookingController::class, 'holdSeat'])->name('aviation.bookings.hold');
    });

    // Passenger Self-Service Portal
    Route::middleware(['role:admin,passenger,superadmin'])->group(function (): void {
        Route::get('/passenger', [AviationPassengerPortalController::class, 'index'])->name('aviation.passenger.index');
        Route::get('/passenger/ticket/{ticket}', [AviationPassengerPortalController::class, 'showTicket'])->name('aviation.passenger.ticket');
    });

    // Reports & Analytics
    Route::middleware(['role:admin,customer_care,ops_controller,superadmin'])->group(function (): void {
        Route::get('/aviation/reports', [AviationReportsController::class, 'index'])->name('aviation.reports.index');
        Route::get('/aviation/reports/export', [AviationReportsController::class, 'export'])->name('aviation.reports.export');
    });

    // Fase 178: Yield & Revenue Management
    Route::middleware(['role:admin,ops_controller,superadmin'])->group(function (): void {
        Route::get('/aviation/yield', [AviationYieldController::class, 'index'])->name('aviation.yield.index');
        Route::post('/aviation/yield/optimize', [AviationYieldController::class, 'optimize'])->name('aviation.yield.optimize');
    });

    // Fase 178: Disrupsi Operasional (IRROPS) & Hak Penumpang PM 89
    Route::middleware(['role:admin,customer_care,ops_controller,superadmin'])->group(function (): void {
        Route::get('/aviation/irrops', [AviationIrropsController::class, 'index'])->name('aviation.irrops.index');
        Route::post('/aviation/irrops/store', [AviationIrropsController::class, 'store'])->name('aviation.irrops.store');
        Route::post('/aviation/irrops/dispatch-care', [AviationIrropsController::class, 'dispatchCare'])->name('aviation.irrops.dispatch-care');
        Route::post('/aviation/irrops/reaccommodate', [AviationIrropsController::class, 'reaccommodate'])->name('aviation.irrops.reaccommodate');
    });

    // Fase 178: Codeshare & Aliansi Antar-Maskapai
    Route::middleware(['role:admin,ops_controller,superadmin'])->group(function (): void {
        Route::get('/aviation/codeshare', [AviationCodeshareController::class, 'index'])->name('aviation.codeshare.index');
        Route::post('/aviation/codeshare/store', [AviationCodeshareController::class, 'store'])->name('aviation.codeshare.store');
        Route::post('/aviation/codeshare/{id}/activate', [AviationCodeshareController::class, 'activate'])->name('aviation.codeshare.activate');
    });

    // Fase 178: Program Loyalitas Frequent Flyer
    Route::middleware(['role:admin,customer_care,superadmin'])->group(function (): void {
        Route::get('/aviation/loyalty', [AviationLoyaltyController::class, 'index'])->name('aviation.loyalty.index');
        Route::post('/aviation/loyalty/enroll', [AviationLoyaltyController::class, 'enroll'])->name('aviation.loyalty.enroll');
    });
});
