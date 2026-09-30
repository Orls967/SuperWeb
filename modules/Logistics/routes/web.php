<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\FleetController;
use Modules\Logistics\Http\Controllers\LaneController;
use Modules\Logistics\Http\Controllers\LocationController;
use Modules\Logistics\Http\Controllers\LogisticsDashboardController;

Route::middleware(['web'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [LogisticsDashboardController::class, 'index'])->name('dashboard');

        // Lokasi & Jaringan (Locations)
        Route::resource('locations', LocationController::class)->except(['show']);

        // Jalur Transportasi (Lanes)
        Route::get('/lanes', [LaneController::class, 'index'])->name('lanes.index');
        Route::post('/lanes', [LaneController::class, 'store'])->name('lanes.store');
        Route::delete('/lanes/{lane}', [LaneController::class, 'destroy'])->name('lanes.destroy');

        // Armada Multimoda (Fleet)
        Route::get('/fleet', [FleetController::class, 'index'])->name('fleet.index');
        Route::get('/drivers', [LogisticsDashboardController::class, 'index'])->name('drivers.index');
        Route::get('/shipments', [LogisticsDashboardController::class, 'index'])->name('shipments.index');
        Route::get('/dispatch', [LogisticsDashboardController::class, 'index'])->name('dispatch.index');
        Route::get('/hub', [LogisticsDashboardController::class, 'index'])->name('hub.index');
        Route::get('/driver/tasks', [LogisticsDashboardController::class, 'index'])->name('driver.tasks');
    });
});
