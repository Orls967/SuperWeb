<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\SopWorkflowController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])
    ->prefix('control-tower')
    ->name('control_tower.')
    ->group(function (): void {
        Route::get('/sop/export.csv', [SopWorkflowController::class, 'exportCsv'])
            ->name('sop.export');

        Route::middleware('role:admin,logistics_admin,planner')->group(function (): void {
            Route::post('/sop/{cycle}/consensus', [SopWorkflowController::class, 'consensus'])
                ->name('sop.consensus');
        });
    });
