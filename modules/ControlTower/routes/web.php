<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\ControlTowerAutonomousController;
use Modules\ControlTower\Http\Controllers\ControlTowerController;
use Modules\ControlTower\Http\Controllers\ControlTowerOperationsController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])->prefix('control-tower')->name('control_tower.')->group(function () {
    Route::get('/', [ControlTowerController::class, 'index'])->name('index');
    Route::get('/promises', [ControlTowerController::class, 'index'])->name('promises');
    Route::get('/sop', [ControlTowerOperationsController::class, 'sop'])->name('sop.index');
    Route::get('/forecasting', [ControlTowerOperationsController::class, 'forecasting'])->name('forecasting.index');
    Route::get('/twin', [ControlTowerOperationsController::class, 'twin'])->name('twin.index');
    Route::get('/kpi', [ControlTowerOperationsController::class, 'kpi'])->name('kpi.index');
    Route::get('/safety-stock', [ControlTowerOperationsController::class, 'safetyStock'])->name('safety_stock.index');
    Route::get('/export/echelon.csv', [ControlTowerOperationsController::class, 'exportEchelonCsv'])->name('export.echelon');

    Route::middleware('role:admin,logistics_admin,planner')->group(function () {
        Route::post('/sop', [ControlTowerOperationsController::class, 'storeSop'])->name('sop.store');
        Route::post('/sop/{cycle}/transition', [ControlTowerOperationsController::class, 'transitionSop'])->name('sop.transition');
        Route::post('/forecasting/override', [ControlTowerOperationsController::class, 'overrideForecast'])->name('forecasting.override');
        Route::post('/twin/simulate', [ControlTowerOperationsController::class, 'simulateTwin'])->name('twin.simulate');
    });

    Route::prefix('autonomous')->name('autonomous.')->group(function () {
        Route::get('/', [ControlTowerAutonomousController::class, 'index'])->name('index');
        Route::post('/forecast', [ControlTowerAutonomousController::class, 'runForecast'])->name('forecast');
        Route::get('/bottlenecks', [ControlTowerAutonomousController::class, 'detectBottlenecks'])->name('bottlenecks');
        Route::post('/reallocate', [ControlTowerAutonomousController::class, 'reallocateStock'])->name('reallocate');
    });
});
