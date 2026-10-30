<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Egy\Http\Controllers\Api\EgyMeterApiController;

Route::middleware(['api', 'throttle:60,1'])->prefix('api/energy')->group(function () {
    Route::post('/telemetry', [EgyMeterApiController::class, 'ingest'])->name('api.energy.telemetry');
});
