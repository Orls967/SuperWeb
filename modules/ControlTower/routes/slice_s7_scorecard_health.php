<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\ScorecardAndHealthController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])
    ->prefix('control-tower')
    ->name('control_tower.')
    ->group(function (): void {
        Route::get('/suppliers', [ScorecardAndHealthController::class, 'suppliers'])
            ->name('suppliers.index');
        Route::get('/suppliers/export.csv', [ScorecardAndHealthController::class, 'exportSuppliersCsv'])
            ->name('suppliers.export');
        Route::get('/inventory-health', [ScorecardAndHealthController::class, 'inventoryHealth'])
            ->name('inventory_health.index');
        Route::get('/inventory-health/export.csv', [ScorecardAndHealthController::class, 'exportInventoryHealthCsv'])
            ->name('inventory_health.export');

        Route::middleware('role:admin,logistics_admin,planner')->group(function (): void {
            Route::post('/suppliers/refresh', [ScorecardAndHealthController::class, 'refreshSuppliers'])
                ->name('suppliers.refresh');
            Route::post('/inventory-health/refresh', [ScorecardAndHealthController::class, 'refreshInventoryHealth'])
                ->name('inventory_health.refresh');
        });
    });
