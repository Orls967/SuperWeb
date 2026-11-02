<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Egy\Http\Controllers\Api\EgyInvoiceApiController;
use Modules\Egy\Http\Controllers\Api\EgyMeterApiController;
use Modules\Egy\Http\Controllers\Api\EgyPpaApiController;

Route::middleware(['api', 'throttle:60,1'])->prefix('api/energy')->group(function () {
    Route::post('/telemetry', [EgyMeterApiController::class, 'ingest'])->name('api.energy.telemetry');

    Route::middleware(['auth', 'role:admin,energy_manager,genco_trader'])->group(function () {
        Route::get('/invoices/{id}', [EgyInvoiceApiController::class, 'show'])->name('api.energy.invoices.show');
        Route::post('/billing/calculate', [EgyInvoiceApiController::class, 'calculate'])->name('api.energy.billing.calculate');
        Route::post('/invoices/{id}/issue', [EgyInvoiceApiController::class, 'issue'])->name('api.energy.invoices.issue');
        Route::post('/ppa/{id}/settle', [EgyPpaApiController::class, 'settle'])->name('api.energy.ppa.settle');
    });
});
