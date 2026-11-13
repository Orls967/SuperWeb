<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\ForecastController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])
    ->prefix('control-tower')
    ->name('control_tower.')
    ->group(function (): void {
        Route::get('/forecast', [ForecastController::class, 'index'])->name('forecast.index');
        Route::get('/forecast/export.csv', [ForecastController::class, 'exportCsv'])->name('forecast.export');

        Route::middleware('role:admin,logistics_admin,planner')->group(function (): void {
            Route::post('/forecast/select', [ForecastController::class, 'select'])->name('forecast.select');
            Route::post('/forecast/override', [ForecastController::class, 'override'])->name('forecast.override');
            Route::post('/forecast/override/{override}/revert', [ForecastController::class, 'revert'])->name('forecast.revert');
        });
    });
