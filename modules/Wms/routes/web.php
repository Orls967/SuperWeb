<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Wms\Http\Controllers\WmsController;
use Modules\Wms\Http\Controllers\WmsOperationsController;
use Modules\Wms\Http\Controllers\WmsRoboticsController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,hub_operator,procurement,auditor'])
    ->prefix('wms')
    ->name('wms.')
    ->group(function () {
        Route::get('/', [WmsController::class, 'index'])->name('index');
        Route::get('/tasks', [WmsOperationsController::class, 'tasks'])->name('tasks');
        Route::get('/valuation', [WmsOperationsController::class, 'valuation'])->name('valuation');
        Route::get('/receiving', [WmsOperationsController::class, 'receiving'])->name('receiving');
        Route::get('/quarantine', [WmsOperationsController::class, 'quarantine'])->name('quarantine');
        Route::get('/utilization', [WmsOperationsController::class, 'utilization'])->name('utilization');
        Route::get('/export/valuation.csv', [WmsOperationsController::class, 'exportValuationCsv'])->name('export.valuation');
        Route::get('/export/bin-stocks.csv', [WmsOperationsController::class, 'exportBinStocksCsv'])->name('export.bin-stocks');
        Route::get('/robotics/fleet', [WmsRoboticsController::class, 'fleet'])->name('robotics.fleet');
        Route::get('/robotics/missions', [WmsRoboticsController::class, 'missions'])->name('robotics.missions');
        Route::get('/robotics/roi', [WmsRoboticsController::class, 'roi'])->name('robotics.roi');

        Route::middleware('role:admin,hub_operator')->group(function () {
            Route::post('/robotics/missions/dispatch', [WmsRoboticsController::class, 'dispatchMission'])->name('robotics.missions.dispatch');
            Route::post('/robotics/missions/{mission}/fallback', [WmsRoboticsController::class, 'triggerFallback'])->name('robotics.missions.fallback');
            Route::post('/robotics/interlock', [WmsRoboticsController::class, 'enforceInterlock'])->name('robotics.interlock');
            Route::post('/operations/putaway', [WmsOperationsController::class, 'executePutaway'])->name('operations.putaway');
            Route::post('/operations/wave-picking', [WmsOperationsController::class, 'allocateWavePicking'])->name('operations.wave-picking');
            Route::post('/operations/receive-inbound', [WmsOperationsController::class, 'receiveInbound'])->name('operations.receive-inbound');
            Route::post('/operations/quarantine/{binStockId}/release', [WmsOperationsController::class, 'releaseQuarantine'])->name('operations.quarantine.release');
            Route::post('/operations/utilization/{warehouseId}/snapshot', [WmsOperationsController::class, 'captureUtilization'])->name('operations.utilization.snapshot');
        });

        Route::middleware('role:admin')->group(function () {
            Route::post('/operations/revalue-cost', [WmsOperationsController::class, 'revalueCost'])->name('operations.revalue-cost');
        });
        Route::middleware('role:admin,hub_operator')->group(function () {
            Route::post('/warehouses', [WmsController::class, 'storeWarehouse'])->name('warehouses.store');
            Route::post('/putaway', [WmsController::class, 'storePutaway'])->name('putaway');
            Route::post('/pick', [WmsController::class, 'storePick'])->name('pick');
            Route::post('/waves', [WmsController::class, 'storeWave'])->name('waves.store');
            Route::post('/waves/{wave}/release', [WmsController::class, 'releaseWave'])->name('waves.release');
            Route::post('/transfers', [WmsController::class, 'storeTransfer'])->name('transfers.store');
            Route::post('/transfers/{transfer}/ship', [WmsController::class, 'shipTransfer'])->name('transfers.ship');
            Route::post('/transfers/{transfer}/receive', [WmsController::class, 'receiveTransfer'])->name('transfers.receive');
            Route::post('/cycle-counts', [WmsController::class, 'storeCycleCount'])->name('cycle-counts.store');
            Route::post('/cycle-counts/{count}/submit', [WmsController::class, 'submitCount'])->name('cycle-counts.submit');
            Route::post('/cycle-counts/{count}/apply', [WmsController::class, 'applyCount'])->middleware('role:admin')->name('cycle-counts.apply');
            Route::post('/dock-appointments', [WmsController::class, 'storeDock'])->name('dock.store');
            Route::post('/replenishments/compute', [WmsController::class, 'computeReplenishment'])->name('replenishments.compute');
        });
    });
