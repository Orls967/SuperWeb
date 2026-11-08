<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Aviation\Http\Controllers\AviationBookingController;
use Modules\Aviation\Http\Controllers\AviationCargoController;
use Modules\Aviation\Http\Controllers\AviationDispatchController;
use Modules\Aviation\Http\Controllers\AviationFleetController;
use Modules\Aviation\Http\Controllers\AviationOpsController;
use Modules\Aviation\Http\Controllers\AviationPassengerPortalController;
use Modules\Aviation\Http\Controllers\AviationReportsController;
use Modules\Aviation\Http\Controllers\AviationSlotController;

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
});
