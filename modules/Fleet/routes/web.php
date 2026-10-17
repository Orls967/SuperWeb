<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Fleet\Http\Controllers\FleetController;

Route::middleware(['web', 'auth', 'role:admin,fleet_admin,lease_manager'])->prefix('fleet')->group(function () {
    Route::get('/', [FleetController::class, 'index'])->name('fleet.index');
    Route::post('/contracts/{contract}/amortize', [FleetController::class, 'amortize'])->name('fleet.contracts.amortize');
});
