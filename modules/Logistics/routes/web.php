<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\LogisticsDashboardController;

Route::middleware(['web'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [LogisticsDashboardController::class, 'index'])->name('dashboard');
        Route::get('/locations', [LogisticsDashboardController::class, 'index'])->name('locations.index');
        Route::get('/fleet', [LogisticsDashboardController::class, 'index'])->name('fleet.index');
        Route::get('/drivers', [LogisticsDashboardController::class, 'index'])->name('drivers.index');
        Route::get('/shipments', [LogisticsDashboardController::class, 'index'])->name('shipments.index');
        Route::get('/dispatch', [LogisticsDashboardController::class, 'index'])->name('dispatch.index');
        Route::get('/hub', [LogisticsDashboardController::class, 'index'])->name('hub.index');
        Route::get('/driver/tasks', [LogisticsDashboardController::class, 'index'])->name('driver.tasks');
    });
});
