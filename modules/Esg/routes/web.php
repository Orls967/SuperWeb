<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Esg\Http\Controllers\EsgController;
use Modules\Esg\Http\Controllers\EsgOperationsController;

Route::middleware(['web', 'auth', 'role:admin,esg_officer,auditor'])->prefix('esg')->name('esg.')->group(function () {
    Route::get('/', [EsgController::class, 'index'])->name('index');

    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('/', [EsgOperationsController::class, 'index'])->name('index');
        Route::post('/emissions', [EsgOperationsController::class, 'calculateEmission'])->name('emissions.calculate');
        Route::post('/credits/purchase', [EsgOperationsController::class, 'purchaseCredit'])->name('credits.purchase');
        Route::post('/credits/{credit}/retire', [EsgOperationsController::class, 'retireCredit'])->name('credits.retire');
        Route::post('/supplier-score', [EsgOperationsController::class, 'evaluateSupplier'])->name('supplier.evaluate');
    });
});
