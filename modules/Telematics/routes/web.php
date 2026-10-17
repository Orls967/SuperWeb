<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Telematics\Http\Controllers\TelematicsController;

Route::middleware(['web', 'auth', 'role:admin,telematics_admin,fleet_manager'])->prefix('telematics')->group(function () {
    Route::get('/', [TelematicsController::class, 'index'])->name('telematics.index');
    Route::post('/devices/{device}/ingest', [TelematicsController::class, 'ingest'])->name('telematics.devices.ingest');
});
