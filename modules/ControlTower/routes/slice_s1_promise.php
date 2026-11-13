<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ControlTower\Http\Controllers\PromiseController;

Route::middleware(['web', 'auth', 'role:admin,logistics_admin,planner,auditor'])
    ->prefix('control-tower')
    ->name('control_tower.')
    ->group(function (): void {
        Route::get('/promises/export.csv', [PromiseController::class, 'exportCsv'])
            ->name('promises.export');

        Route::middleware('role:admin,logistics_admin,planner')->group(function (): void {
            Route::post('/promises/quote', [PromiseController::class, 'quote'])
                ->name('promises.quote');
        });
    });
