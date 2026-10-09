<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Manufacturing\Http\Controllers\MaintenanceController;
use Modules\Manufacturing\Http\Controllers\ManufacturingController;
use Modules\Manufacturing\Http\Controllers\PlanningController;
use Modules\Manufacturing\Http\Controllers\ProductionController;
use Modules\Manufacturing\Http\Controllers\QualityController;

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

Route::middleware(['web', 'auth', 'verified', 'role:admin,planner,auditor'])
    ->prefix('manufacturing')
    ->name('manufacturing.')
    ->group(function () {
        Route::get('/costing', [ProductionController::class, 'costing'])->name('costing.index');
        Route::post('/costing/versions/{version}/submit', [ProductionController::class, 'submitCostVersion'])->name('costing.versions.submit');
        Route::post('/costing/versions/{version}/approve', [ProductionController::class, 'approveCostVersion'])->name('costing.versions.approve');
    });

Route::middleware(['web', 'auth', 'verified', 'role:admin,planner,qc_inspector,auditor'])
    ->prefix('manufacturing')
    ->name('manufacturing.')
    ->group(function () {
        Route::get('/quality', [QualityController::class, 'index'])->name('quality.index');
        Route::post('/quality/plans', [QualityController::class, 'storePlan'])->name('quality.plans.store');
        Route::post('/quality/inspections', [QualityController::class, 'inspect'])->name('quality.inspections.store');
        Route::post('/quality/inspections/{inspection}/waiver', [QualityController::class, 'submitWaiver'])->name('quality.inspections.waiver');
        Route::post('/quality/inspections/{inspection}/approve', [QualityController::class, 'approveWaiver'])->name('quality.inspections.approve');
        Route::post('/quality/ncrs', [QualityController::class, 'storeNcr'])->name('quality.ncrs.store');
        Route::post('/quality/ncrs/{ncr}/capas', [QualityController::class, 'storeCapa'])->name('quality.capas.store');
        Route::post('/quality/capas/{capa}/complete', [QualityController::class, 'completeCapa'])->name('quality.capas.complete');
        Route::post('/quality/lots/{lot}/certificates', [QualityController::class, 'addCertificate'])->name('quality.lots.certificates');
        Route::post('/quality/lots/{lot}/release', [QualityController::class, 'releaseLot'])->name('quality.lots.release');
        Route::get('/quality/lots/{lot}/trace', [QualityController::class, 'trace'])->name('quality.lots.trace');
        Route::post('/quality/lots/{lot}/recall', [QualityController::class, 'storeRecall'])->name('quality.lots.recall');
        Route::post('/quality/recalls/{recall}/notify', [QualityController::class, 'notifyRecall'])->name('quality.recalls.notify');
        Route::post('/quality/recalls/{recall}/complete', [QualityController::class, 'completeRecall'])->name('quality.recalls.complete');
    });

Route::middleware(['web', 'auth', 'verified', 'role:admin,planner,operator,asset_manager,auditor'])
    ->prefix('manufacturing')
    ->name('manufacturing.')
    ->group(function () {
        Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('/maintenance/orders', [MaintenanceController::class, 'storeOrder'])->name('maintenance.orders.store');
        Route::post('/maintenance/orders/{order}/transition', [MaintenanceController::class, 'transitionOrder'])->name('maintenance.orders.transition');
        Route::post('/maintenance/sensors', [MaintenanceController::class, 'storeSensor'])->name('maintenance.sensors.store');
        Route::post('/maintenance/oee', [MaintenanceController::class, 'computeOee'])->name('maintenance.oee.compute');
        Route::post('/maintenance/parts', [MaintenanceController::class, 'storePart'])->name('maintenance.parts.store');
        Route::post('/maintenance/incidents', [MaintenanceController::class, 'storeIncident'])->name('maintenance.incidents.store');
        Route::post('/maintenance/incidents/{incident}/close', [MaintenanceController::class, 'closeIncident'])->name('maintenance.incidents.close');
        Route::post('/maintenance/permits', [MaintenanceController::class, 'storePermit'])->name('maintenance.permits.store');
        Route::post('/maintenance/permits/{permit}/approve', [MaintenanceController::class, 'approvePermit'])->name('maintenance.permits.approve');
    });
