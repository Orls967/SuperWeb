<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\TwinAndKpiController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])
    ->prefix('control-tower')
    ->name('control_tower.')
    ->group(function (): void {
        Route::get('/twin/export.csv', [TwinAndKpiController::class, 'exportTwinCsv'])
            ->name('twin.export');
        Route::get('/kpi/drilldown', [TwinAndKpiController::class, 'kpiDrilldown'])
            ->name('kpi.drilldown');
        Route::get('/kpi/export.csv', [TwinAndKpiController::class, 'exportKpiCsv'])
            ->name('kpi.export');

        Route::middleware('role:admin,logistics_admin,planner')->group(function (): void {
            Route::post('/twin/whatif', [TwinAndKpiController::class, 'runWhatIf'])
                ->name('twin.whatif');
        });
    });
