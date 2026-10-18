<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Fleet\Http\Controllers\FleetController;

Route::middleware(['web', 'auth', 'role:admin,fleet_admin,lease_manager'])->prefix('fleet')->group(function () {
    Route::get('/', [FleetController::class, 'index'])->name('fleet.index');
    Route::post('/contracts', [FleetController::class, 'store'])->name('fleet.contracts.store');
    Route::post('/contracts/{contract}/amortize', [FleetController::class, 'amortize'])->name('fleet.contracts.amortize');
    Route::post('/contracts/{contract}/sla-claim', [FleetController::class, 'slaClaim'])->name('fleet.contracts.sla-claim');
    Route::post('/contracts/{contract}/return-unit', [FleetController::class, 'returnUnit'])->name('fleet.contracts.return-unit');
});
