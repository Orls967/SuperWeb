<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Manufacturing\Http\Controllers\ManufacturingController;
use Modules\Manufacturing\Http\Controllers\PlanningController;
use Modules\Manufacturing\Http\Controllers\ProductionController;

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

Route::middleware(['web', 'auth', 'verified', 'role:admin,planner'])
    ->prefix('manufacturing')
    ->name('manufacturing.')
    ->group(function () {
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::post('/planning/params', [PlanningController::class, 'storeParams'])->name('planning.params.store');
        Route::post('/planning/balances', [PlanningController::class, 'storeBalance'])->name('planning.balances.store');
        Route::post('/planning/receipts', [PlanningController::class, 'storeReceipt'])->name('planning.receipts.store');
        Route::post('/planning/forecast', [PlanningController::class, 'storeForecast'])->name('planning.forecast.store');
        Route::post('/planning/forecast/{scenario}/activate', [PlanningController::class, 'activateForecast'])->name('planning.forecast.activate');
        Route::post('/planning/mps', [PlanningController::class, 'storeMps'])->name('planning.mps.store');
        Route::post('/planning/mrp/run', [PlanningController::class, 'runMrp'])->name('planning.mrp.run');
        Route::post('/planning/orders/{order}/firm', [PlanningController::class, 'firmOrder'])->name('planning.orders.firm');
        Route::post('/planning/runs/{run}/propose-purchases', [PlanningController::class, 'proposePurchases'])->name('planning.runs.propose');
    });

Route::middleware(['web', 'auth', 'verified', 'role:admin,planner,operator,qc_inspector'])
    ->prefix('manufacturing/production')
    ->name('manufacturing.production.')
    ->group(function () {
        Route::get('/', [ProductionController::class, 'index'])->name('index');
        Route::post('/orders', [ProductionController::class, 'store'])->middleware('role:admin,planner')->name('orders.store');
        Route::post('/orders/{order}/transition', [ProductionController::class, 'transition'])->name('orders.transition');
        Route::post('/orders/{order}/issue', [ProductionController::class, 'issue'])->name('orders.issue');
        Route::post('/orders/{order}/operations/start', [ProductionController::class, 'startOperation'])->name('operations.start');
        Route::post('/orders/{order}/operations/finish', [ProductionController::class, 'finishOperation'])->name('operations.finish');
        Route::post('/orders/{order}/receive-fg', [ProductionController::class, 'receiveFg'])->name('orders.receive-fg');
        Route::post('/orders/{order}/scrap', [ProductionController::class, 'recordScrap'])->name('orders.scrap');
        Route::post('/orders/{order}/invariants', [ProductionController::class, 'invariants'])->name('orders.invariants');
        Route::post('/downtimes', [ProductionController::class, 'startDowntime'])->name('downtimes.store');
        Route::post('/downtimes/{downtime}/end', [ProductionController::class, 'endDowntime'])->name('downtimes.end');
    });
