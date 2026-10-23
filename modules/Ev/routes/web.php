<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ev\Http\Controllers\EvController;

Route::middleware(['web', 'auth', 'role:admin,ev_admin,station_operator,driver'])->prefix('ev')->group(function () {
    Route::get('/', [EvController::class, 'index'])->name('ev.index');
    Route::post('/chargers/{charger}/reserve', [EvController::class, 'reserve'])->name('ev.chargers.reserve');
    Route::post('/chargers/{charger}/check-in', [EvController::class, 'checkIn'])->name('ev.chargers.checkin');

    Route::middleware(['role:admin,ev_admin,station_operator'])->group(function () {
        Route::post('/sessions/{session}/complete', [EvController::class, 'complete'])->name('ev.sessions.complete');
        Route::post('/stations/{station}/load-balance', [EvController::class, 'loadBalance'])->name('ev.stations.loadbalance');
    });
});
