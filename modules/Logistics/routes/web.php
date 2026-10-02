<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\DispatchBoardController;
use Modules\Logistics\Http\Controllers\DriverController;
use Modules\Logistics\Http\Controllers\FleetController;
use Modules\Logistics\Http\Controllers\HubOperationsController;
use Modules\Logistics\Http\Controllers\LaneController;
use Modules\Logistics\Http\Controllers\LocationController;
use Modules\Logistics\Http\Controllers\LogisticsDashboardController;
use Modules\Logistics\Http\Controllers\PublicTrackingController;
use Modules\Logistics\Http\Controllers\ShipperPortalController;

// Public Tracking Routes (No login required, rate limited 30 req/min)
Route::middleware(['web', 'throttle:30,1'])->group(function () {
    Route::get('/track', [PublicTrackingController::class, 'index'])->name('track.index');
    Route::get('/track/{tracking_number}', [PublicTrackingController::class, 'track'])->name('track.show');
});

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
        // Pengemudi (Drivers)
        Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
        // Pengiriman & Shipper Portal
        Route::get('/shipments', [ShipperPortalController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/create', [ShipperPortalController::class, 'create'])->name('shipments.create');
        Route::post('/shipments', [ShipperPortalController::class, 'store'])->name('shipments.store');
        Route::get('/shipments/bulk', [ShipperPortalController::class, 'bulkUploadForm'])->name('shipments.bulk');
        Route::post('/shipments/bulk', [ShipperPortalController::class, 'processBulkUpload'])->name('shipments.bulk.process');
        Route::get('/shipments/template', [ShipperPortalController::class, 'downloadTemplate'])->name('shipments.template');
        Route::get('/shipments/errors/{batchId}', [ShipperPortalController::class, 'downloadErrorReport'])->name('shipments.errors');
        Route::get('/shipments/{id}', [ShipperPortalController::class, 'show'])->name('shipments.show');
        Route::get('/shipments/{id}/label', [ShipperPortalController::class, 'label'])->name('shipments.label');

        // Papan Dispatch (dispatcher)
        Route::get('/dispatch', [DispatchBoardController::class, 'index'])->name('dispatch.index');
        Route::post('/dispatch/assign', [DispatchBoardController::class, 'assign'])->name('dispatch.assign');
        Route::delete('/dispatch/{schedule}/release', [DispatchBoardController::class, 'release'])->name('dispatch.release');
        Route::post('/dispatch/assign-shipment', [DispatchBoardController::class, 'assignShipment'])->name('dispatch.assign-shipment');

        // Operasi Hub (Inbound, Sort, Outbound)
        Route::get('/hub', [HubOperationsController::class, 'index'])->name('hub.index');
        Route::post('/hub/inbound', [HubOperationsController::class, 'inbound'])->name('hub.inbound');
        Route::post('/hub/sort', [HubOperationsController::class, 'sort'])->name('hub.sort');
        Route::post('/hub/outbound', [HubOperationsController::class, 'outbound'])->name('hub.outbound');

        Route::get('/driver/tasks', [LogisticsDashboardController::class, 'index'])->name('driver.tasks');
    });
});
