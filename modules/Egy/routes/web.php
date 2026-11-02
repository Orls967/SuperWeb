<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Egy\Http\Controllers\EgyGridMeterController;
use Modules\Egy\Http\Controllers\EgyMicrogridRecController;
use Modules\Egy\Http\Controllers\EgyPowerPurchaseController;

Route::middleware(['web', 'auth', 'role:admin,grid_operator,genco_trader,energy_auditor,renewable_dev'])->prefix('energy')->group(function () {
    Route::get('/', [EgyGridMeterController::class, 'index'])->name('egy.grid.index');
    Route::get('/overview', [EgyGridMeterController::class, 'index'])->name('energy.index');
    Route::get('/meters', [EgyGridMeterController::class, 'meters'])->name('egy.grid.meters');
    Route::get('/dispatch', [EgyGridMeterController::class, 'dispatchView'])->name('egy.grid.dispatch');
    Route::get('/invoices', [EgyGridMeterController::class, 'invoicesView'])->name('egy.grid.invoices');
    Route::post('/telemetry', [EgyGridMeterController::class, 'ingestTelemetry'])->name('egy.grid.telemetry');
    Route::post('/nodes/{nodeId}/anomaly', [EgyGridMeterController::class, 'scanAnomaly'])->name('egy.grid.anomaly');

    Route::get('/ppa', [EgyPowerPurchaseController::class, 'index'])->name('egy.ppa.index');
    Route::get('/ppa/{id}', [EgyPowerPurchaseController::class, 'show'])->name('egy.ppa.show');
    Route::post('/ppa/{id}/settle', [EgyPowerPurchaseController::class, 'settle'])->name('egy.ppa.settle');

    Route::get('/microgrid', [EgyMicrogridRecController::class, 'index'])->name('egy.microgrid.index');
    Route::post('/microgrid/dispatch', [EgyMicrogridRecController::class, 'dispatchBattery'])->name('egy.microgrid.dispatch');
    Route::post('/microgrid/p2p', [EgyMicrogridRecController::class, 'tradeP2p'])->name('egy.microgrid.p2p');

    Route::get('/rec', [EgyMicrogridRecController::class, 'recIndex'])->name('egy.rec.index');
    Route::post('/rec/mint', [EgyMicrogridRecController::class, 'mintRec'])->name('egy.rec.mint');
});
