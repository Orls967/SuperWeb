<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\SafetyStockController;

Route::middleware(['web', 'auth'])->prefix('control-tower')->name('control_tower.')->group(function (): void {
    Route::middleware('role:admin,logistics_admin,planner')->post(
        '/safety-stock/propose',
        [SafetyStockController::class, 'propose'],
    )->name('safety_stock.propose');

    Route::middleware('role:admin,logistics_admin')->post(
        '/safety-stock/{policy}/decide',
        [SafetyStockController::class, 'decide'],
    )->name('safety_stock.decide');

    Route::middleware('role:admin,logistics_admin,planner,auditor')->get(
        '/safety-stock/export.csv',
        [SafetyStockController::class, 'exportCsv'],
    )->name('safety_stock.export');
});
