<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\RiskAndAlertController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])
    ->prefix('control-tower')
    ->name('control_tower.')
    ->group(function (): void {
        Route::get('/risk', [RiskAndAlertController::class, 'risk'])->name('risk.index');
        Route::get('/risk/export.csv', [RiskAndAlertController::class, 'exportRiskCsv'])->name('risk.export');
        Route::get('/alerts', [RiskAndAlertController::class, 'alerts'])->name('alerts.index');
        Route::get('/alerts/export.csv', [RiskAndAlertController::class, 'exportAlertsCsv'])->name('alerts.export');

        Route::middleware('role:admin,logistics_admin,planner')->group(function (): void {
            Route::post('/risk/scan', [RiskAndAlertController::class, 'scanRisk'])->name('risk.scan');
            Route::post('/alerts/scan', [RiskAndAlertController::class, 'scanAlerts'])->name('alerts.scan');
            Route::post('/alerts/{alert}/mitigate', [RiskAndAlertController::class, 'mitigateAlert'])->name('alerts.mitigate');
        });
    });
