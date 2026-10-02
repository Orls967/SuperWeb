<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Logistics\Http\Controllers\CarrierController;
use Modules\Logistics\Http\Controllers\ClaimController;
use Modules\Logistics\Http\Controllers\CodController;
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

        // Carrier subkontrak & margin
        Route::get('/carriers', [CarrierController::class, 'index'])->name('carriers.index');
        Route::post('/carriers', [CarrierController::class, 'store'])->name('carriers.store');
        Route::post('/carriers/{carrier}/pay', [CarrierController::class, 'pay'])->name('carriers.pay');
        Route::post('/legs/{leg}/assign-carrier', [CarrierController::class, 'assignLeg'])->name('legs.assign-carrier');
        Route::post('/legs/{leg}/complete', [CarrierController::class, 'completeLeg'])->name('legs.complete');
        Route::get('/margins', [CarrierController::class, 'margins'])->name('margins');

        // Klaim
        Route::get('/claims', [ClaimController::class, 'index'])->name('claims.index');
        Route::post('/claims', [ClaimController::class, 'store'])->name('claims.store');
        Route::post('/claims/{claim}/submit', [ClaimController::class, 'submit'])->name('claims.submit');
        Route::post('/claims/{claim}/decide', [ClaimController::class, 'decide'])->name('claims.decide');
        Route::post('/claims/{claim}/pay', [ClaimController::class, 'pay'])->name('claims.pay');

        // Demurrage & Detention
        Route::get('/demurrage', [DemurrageController::class, 'index'])->name('dd.index');
        Route::post('/demurrage/tariffs', [DemurrageController::class, 'storeTariff'])->name('dd.tariffs.store');
        Route::post('/demurrage/start', [DemurrageController::class, 'start'])->name('dd.start');
        Route::post('/demurrage/{dwell}/end', [DemurrageController::class, 'end'])->name('dd.end');
        Route::post('/demurrage/invoice', [DemurrageController::class, 'invoice'])->name('dd.invoice');

        // Bea Cukai
        Route::get('/customs', [CustomsController::class, 'index'])->name('customs.index');
        Route::post('/customs', [CustomsController::class, 'store'])->name('customs.store');
        Route::post('/customs/tariffs', [CustomsController::class, 'storeTariff'])->name('customs.tariffs.store');
        Route::post('/customs/{declaration}/pay', [CustomsController::class, 'pay'])->name('customs.pay');
        Route::post('/customs/{declaration}/clear', [CustomsController::class, 'clear'])->name('customs.clear');

        // BBM & biaya truk
        Route::get('/fuel', [FuelLogController::class, 'index'])->name('fuel.index');
        Route::post('/fuel', [FuelLogController::class, 'store'])->name('fuel.store');

        // COD
        Route::get('/cod', [CodController::class, 'index'])->name('cod.index');
        Route::post('/cod/deposit', [CodController::class, 'deposit'])->name('cod.deposit');

        // Exception & SLA
        Route::get('/exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
        Route::post('/exceptions', [ExceptionController::class, 'store'])->name('exceptions.store');
        Route::post('/exceptions/{exception}/resolve', [ExceptionController::class, 'resolve'])->name('exceptions.resolve');

        // Aplikasi Driver (mobile)
        Route::get('/driver/tasks', [DriverTaskController::class, 'index'])->name('driver.tasks');
        Route::post('/driver/pickup', [DriverTaskController::class, 'pickup'])->name('driver.pickup');
        Route::post('/driver/shipments/{shipment}/start-delivery', [DriverTaskController::class, 'startDelivery'])->name('driver.start-delivery');
        Route::post('/driver/shipments/{shipment}/deliver', [DriverTaskController::class, 'deliver'])->name('driver.deliver');
        Route::post('/driver/shipments/{shipment}/fail', [DriverTaskController::class, 'fail'])->name('driver.fail');
    });
});
