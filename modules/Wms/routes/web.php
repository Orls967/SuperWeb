<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Wms\Http\Controllers\WmsController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,hub_operator,procurement,auditor'])
    ->prefix('wms')
    ->name('wms.')
    ->group(function () {
        Route::get('/', [WmsController::class, 'index'])->name('index');
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
