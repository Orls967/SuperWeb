<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CloudKitchen\Http\Controllers\CloudKitchenController;
use Modules\CloudKitchen\Http\Controllers\CloudKitchenOperationsController;

Route::middleware(['web', 'auth', 'role:admin,kitchen,outlet_manager'])->prefix('cloud-kitchen')->group(function () {
    Route::get('/', [CloudKitchenController::class, 'index'])->name('cloud_kitchen.index');
    Route::post('/orders', [CloudKitchenController::class, 'storeOrder'])->name('cloud_kitchen.orders.store');
    Route::post('/subscriptions/{subscription}/dispatch', [CloudKitchenController::class, 'dispatchMeal'])->name('cloud_kitchen.subscriptions.dispatch');

    // CloudKitchen Operations, Multi-Drop Courier & Thermal HACCP Deepening Routes
    Route::prefix('operations')->name('cloud_kitchen.operations.')->group(function () {
        Route::get('/order-routing', [CloudKitchenOperationsController::class, 'orderRouting'])->name('order-routing');
        Route::get('/courier-batches', [CloudKitchenOperationsController::class, 'courierBatches'])->name('courier-batches');
        Route::get('/revenue-split', [CloudKitchenOperationsController::class, 'revenueSplit'])->name('revenue-split');
        Route::get('/thermal-audit', [CloudKitchenOperationsController::class, 'thermalAudit'])->name('thermal-audit');
        Route::post('/route-order', [CloudKitchenOperationsController::class, 'routeOrder'])->name('route-order');
        Route::post('/batch-couriers', [CloudKitchenOperationsController::class, 'batchCouriers'])->name('batch-couriers');
        Route::post('/settle-split', [CloudKitchenOperationsController::class, 'settleSplit'])->name('settle-split');
        Route::post('/audit-thermal', [CloudKitchenOperationsController::class, 'auditThermal'])->name('audit-thermal');
    });
});
