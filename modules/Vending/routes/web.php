<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Vending\Http\Controllers\SmartVendingController;
use Modules\Vending\Http\Controllers\SmartVendingOperationsController;

Route::middleware(['web', 'auth', 'role:admin,vending_operator,store_manager'])->prefix('vending')->group(function () {
    Route::get('/', [SmartVendingController::class, 'index'])->name('vending.index');
    Route::post('/units/{unit}/dispense', [SmartVendingController::class, 'dispense'])->name('vending.units.dispense');

    Route::prefix('operations')->name('vending.operations.')->group(function () {
        Route::get('/demand-forecast', [SmartVendingOperationsController::class, 'demandForecast'])->name('demand-forecast');
        Route::post('/demand-forecast', [SmartVendingOperationsController::class, 'runForecast'])->name('demand-forecast.run');

        Route::get('/auto-replenishment', [SmartVendingOperationsController::class, 'autoReplenishment'])->name('auto-replenishment');
        Route::post('/auto-replenishment', [SmartVendingOperationsController::class, 'triggerReplenish'])->name('auto-replenishment.trigger');

        Route::get('/cart-dispense', [SmartVendingOperationsController::class, 'cartDispense'])->name('cart-dispense');
        Route::post('/cart-dispense', [SmartVendingOperationsController::class, 'dispenseCart'])->name('cart-dispense.dispense');

        Route::get('/markdowns', [SmartVendingOperationsController::class, 'markdowns'])->name('markdowns');
        Route::post('/markdowns', [SmartVendingOperationsController::class, 'calcMarkdown'])->name('markdowns.calculate');
    });
});
