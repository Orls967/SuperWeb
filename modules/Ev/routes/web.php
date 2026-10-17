<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ev\Http\Controllers\EvController;

Route::middleware(['web', 'auth', 'role:admin,ev_admin,station_operator,driver'])->prefix('ev')->group(function () {
    Route::get('/', [EvController::class, 'index'])->name('ev.index');
    Route::post('/chargers/{charger}/reserve', [EvController::class, 'reserve'])->name('ev.chargers.reserve');
});
