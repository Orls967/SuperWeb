<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Manufacturing\Http\Controllers\ManufacturingController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,planner,operator,qc_inspector'])
    ->prefix('manufacturing')
    ->name('manufacturing.')
    ->group(function () {
        Route::get('/', [ManufacturingController::class, 'index'])->name('index');
        Route::middleware('role:admin,planner')->group(function () {
            Route::post('/plants', [ManufacturingController::class, 'storePlant'])->name('plants.store');
            Route::post('/materials', [ManufacturingController::class, 'storeMaterial'])->name('materials.store');
            Route::post('/boms', [ManufacturingController::class, 'storeBom'])->name('boms.store');
            Route::post('/routings', [ManufacturingController::class, 'storeRouting'])->name('routings.store');
            Route::post('/formulas', [ManufacturingController::class, 'storeFormula'])->name('formulas.store');
            Route::post('/formulas/{formula}/submit', [ManufacturingController::class, 'submitFormula'])->name('formulas.submit');
            Route::post('/workers', [ManufacturingController::class, 'storeWorker'])->name('workers.store');
        });
        Route::post('/formulas/{formula}/approve', [ManufacturingController::class, 'approveFormula'])
            ->middleware('role:admin,qc_inspector')
            ->name('formulas.approve');
    });
