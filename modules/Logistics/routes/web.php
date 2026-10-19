<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\CarrierController;
use Modules\Logistics\Http\Controllers\ClaimController;
use Modules\Logistics\Http\Controllers\CodController;
use Modules\Logistics\Http\Controllers\ControlTowerController;
use Modules\Logistics\Http\Controllers\CustomsController;
use Modules\Logistics\Http\Controllers\DemurrageController;
use Modules\Logistics\Http\Controllers\DispatchBoardController;
use Modules\Logistics\Http\Controllers\DriverController;
use Modules\Logistics\Http\Controllers\DriverTaskController;
use Modules\Logistics\Http\Controllers\ExceptionController;
use Modules\Logistics\Http\Controllers\FleetController;
use Modules\Logistics\Http\Controllers\FuelLogController;
use Modules\Logistics\Http\Controllers\HubOperationsController;
use Modules\Logistics\Http\Controllers\LaneController;
use Modules\Logistics\Http\Controllers\LocationController;
use Modules\Logistics\Http\Controllers\LogisticsDashboardController;
use Modules\Logistics\Http\Controllers\PublicTrackingController;
use Modules\Logistics\Http\Controllers\ReverseLogisticsController;
use Modules\Logistics\Http\Controllers\ShipperPortalController;

// Public Tracking Routes (No login required, rate limited 30 req/min)
Route::middleware(['web', 'throttle:30,1'])->group(function () {
    Route::get('/track', [PublicTrackingController::class, 'index'])->name('track.index');
    Route::get('/track/{tracking_number}', [PublicTrackingController::class, 'track'])->name('track.show');
});

Route::middleware(['web'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        // Operational Overview & Fleet View
        Route::middleware(['role:admin,auditor,dispatcher,hub_operator,logistics_admin'])->group(function () {
            Route::get('/', [LogisticsDashboardController::class, 'index'])->name('dashboard');
            Route::get('/fleet', [FleetController::class, 'index'])->name('fleet.index');
            Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
            Route::get('/exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
            Route::get('/reverse', [ReverseLogisticsController::class, 'index'])->name('reverse.index');
        });

        // Control Tower
        Route::middleware(['role:admin,logistics_admin'])->group(function () {
            Route::get('/control-tower', [ControlTowerController::class, 'index'])->name('control-tower.index');
        });

        // Fleet Fuel Logging Read
        Route::middleware(['role:admin,auditor,dispatcher,driver,logistics_admin'])->group(function () {
            Route::get('/fuel', [FuelLogController::class, 'index'])->name('fuel.index');
        });

        // General Operational Monitoring
        Route::middleware(['role:admin,auditor,dispatcher,logistics_admin'])->group(function () {
            Route::get('/lanes', [LaneController::class, 'index'])->name('lanes.index');
            Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
            Route::get('/dispatch', [DispatchBoardController::class, 'index'])->name('dispatch.index');
        });

        // Hub Operations Monitoring
        Route::middleware(['role:admin,auditor,hub_operator,logistics_admin'])->group(function () {
            Route::get('/hub', [HubOperationsController::class, 'index'])->name('hub.index');
        });

        // Financial & Settlement Read-Only
        Route::middleware(['role:admin,auditor,dispatcher,logistics_admin,procurement,treasury'])->group(function () {
            Route::get('/margins', [CarrierController::class, 'margins'])->name('margins');
            Route::get('/carriers', [CarrierController::class, 'index'])->name('carriers.index');
        });

        Route::middleware(['role:admin,auditor,dispatcher,hub_operator,logistics_admin,treasury'])->group(function () {
            Route::get('/cod', [CodController::class, 'index'])->name('cod.index');
        });

        Route::middleware(['role:admin,auditor,dispatcher,logistics_admin,treasury'])->group(function () {
            Route::get('/demurrage', [DemurrageController::class, 'index'])->name('dd.index');
        });

        Route::middleware(['role:admin,auditor,dispatcher,logistics_admin,shipper,treasury'])->group(function () {
            Route::get('/claims', [ClaimController::class, 'index'])->name('claims.index');
            Route::get('/customs', [CustomsController::class, 'index'])->name('customs.index');
        });

        // Shipments Read-Only
        Route::middleware(['role:admin,auditor,dispatcher,logistics_admin,shipper'])->group(function () {
            Route::get('/shipments', [ShipperPortalController::class, 'index'])->name('shipments.index');
        });

        // Network & Master Data Management
        Route::middleware(['role:admin,logistics_admin'])->group(function () {
            Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create');
            Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
            Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
            Route::match(['put', 'patch'], '/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
            Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
            Route::delete('/lanes/{lane}', [LaneController::class, 'destroy'])->name('lanes.destroy');
            Route::post('/customs/tariffs', [CustomsController::class, 'storeTariff'])->name('customs.tariffs.store');
            Route::post('/legs/{leg}/assign-carrier', [CarrierController::class, 'assignLeg'])->name('legs.assign-carrier');
        });

        Route::middleware(['role:admin,logistics_admin,shipper'])->group(function () {
            Route::post('/customs/{declaration}/clear', [CustomsController::class, 'clear'])->name('customs.clear');
        });

        // Dispatch & Route Operations
        Route::middleware(['role:admin,dispatcher,logistics_admin'])->group(function () {
            Route::post('/lanes', [LaneController::class, 'store'])->name('lanes.store');
            Route::post('/dispatch/assign', [DispatchBoardController::class, 'assign'])->name('dispatch.assign');
            Route::delete('/dispatch/{schedule}/release', [DispatchBoardController::class, 'release'])->name('dispatch.release');
            Route::post('/dispatch/assign-shipment', [DispatchBoardController::class, 'assignShipment'])->name('dispatch.assign-shipment');
            Route::post('/legs/{leg}/complete', [CarrierController::class, 'completeLeg'])->name('legs.complete');
            Route::post('/exceptions/{exception}/resolve', [ExceptionController::class, 'resolve'])->name('exceptions.resolve');
            Route::post('/claims/{claim}/decide', [ClaimController::class, 'decide'])->name('claims.decide');
        });

        Route::middleware(['role:admin,dispatcher,hub_operator,logistics_admin'])->group(function () {
            Route::post('/exceptions', [ExceptionController::class, 'store'])->name('exceptions.store');
        });

        // Shipper & Booking Operations
        Route::middleware(['role:admin,dispatcher,logistics_admin,shipper'])->group(function () {
            Route::get('/shipments/create', [ShipperPortalController::class, 'create'])->name('shipments.create');
            Route::post('/shipments', [ShipperPortalController::class, 'store'])->name('shipments.store');
            Route::get('/shipments/bulk', [ShipperPortalController::class, 'bulkUploadForm'])->name('shipments.bulk');
            Route::post('/shipments/bulk', [ShipperPortalController::class, 'processBulkUpload'])->name('shipments.bulk.process');
            Route::get('/shipments/template', [ShipperPortalController::class, 'downloadTemplate'])->name('shipments.template');
            Route::get('/shipments/errors/{batchId}', [ShipperPortalController::class, 'downloadErrorReport'])->name('shipments.errors');
            Route::post('/claims', [ClaimController::class, 'store'])->name('claims.store');
            Route::post('/claims/{claim}/submit', [ClaimController::class, 'submit'])->name('claims.submit');
            Route::post('/customs', [CustomsController::class, 'store'])->name('customs.store');
        });

        Route::middleware(['role:admin,auditor,dispatcher,driver,hub_operator,logistics_admin,shipper'])->group(function () {
            Route::get('/shipments/{id}', [ShipperPortalController::class, 'show'])->name('shipments.show')->whereNumber('id');
            Route::get('/shipments/{id}/label', [ShipperPortalController::class, 'label'])->name('shipments.label')->whereNumber('id');
        });

        // Hub Floor Operations
        Route::middleware(['role:admin,hub_operator,logistics_admin'])->group(function () {
            Route::post('/hub/inbound', [HubOperationsController::class, 'inbound'])->name('hub.inbound');
            Route::post('/hub/sort', [HubOperationsController::class, 'sort'])->name('hub.sort');
            Route::post('/hub/outbound', [HubOperationsController::class, 'outbound'])->name('hub.outbound');
        });

        // Carrier Procurement
        Route::middleware(['role:admin,logistics_admin,procurement'])->group(function () {
            Route::post('/carriers', [CarrierController::class, 'store'])->name('carriers.store');
        });

        // Fleet Fuel Logging
        Route::middleware(['role:admin,dispatcher,driver,logistics_admin'])->group(function () {
            Route::post('/fuel', [FuelLogController::class, 'store'])->name('fuel.store');
        });

        // Mobile Driver Tasks
        Route::middleware(['role:admin,driver,logistics_admin'])->group(function () {
            Route::get('/driver/tasks', [DriverTaskController::class, 'index'])->name('driver.tasks');
            Route::post('/driver/pickup', [DriverTaskController::class, 'pickup'])->name('driver.pickup');
            Route::post('/driver/shipments/{shipment}/start-delivery', [DriverTaskController::class, 'startDelivery'])->name('driver.start-delivery');
            Route::post('/driver/shipments/{shipment}/deliver', [DriverTaskController::class, 'deliver'])->name('driver.deliver');
            Route::post('/driver/shipments/{shipment}/fail', [DriverTaskController::class, 'fail'])->name('driver.fail');
        });

        // Demurrage Billing Operations
        Route::middleware(['role:admin,logistics_admin'])->group(function () {
            Route::post('/demurrage/tariffs', [DemurrageController::class, 'storeTariff'])->name('dd.tariffs.store');
            Route::post('/demurrage/start', [DemurrageController::class, 'start'])->name('dd.start');
            Route::post('/demurrage/{dwell}/end', [DemurrageController::class, 'end'])->name('dd.end');
            Route::post('/demurrage/invoice', [DemurrageController::class, 'invoice'])->name('dd.invoice');
        });

        // Direct Financial Mutations (Strictly Finance / Logistics Admin Roles, Auditor Forbidden)
        Route::middleware(['role:admin,logistics_admin,treasury'])->group(function () {
            Route::post('/carriers/{carrier}/pay', [CarrierController::class, 'pay'])->name('carriers.pay');
            Route::post('/claims/{claim}/pay', [ClaimController::class, 'pay'])->name('claims.pay');
        });

        Route::middleware(['role:admin,shipper,treasury'])->group(function () {
            Route::post('/customs/{declaration}/pay', [CustomsController::class, 'pay'])->name('customs.pay');
        });

        Route::middleware(['role:admin,hub_operator,logistics_admin'])->group(function () {
            Route::post('/cod/deposit', [CodController::class, 'deposit'])->name('cod.deposit');
        });

        // Reverse Logistics Mutations
        Route::middleware(['role:admin,dispatcher,hub_operator,logistics_admin'])->group(function () {
            Route::post('/reverse', [ReverseLogisticsController::class, 'store'])->name('reverse.store');
            Route::post('/reverse/{order}/intake', [ReverseLogisticsController::class, 'intake'])->name('reverse.intake');
        });
    });
});
