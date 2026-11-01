<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Mining\Http\Controllers\MiningController;
use Modules\Mining\Http\Controllers\MiningExtractionDispatchController;
use Modules\Mining\Http\Controllers\MiningRoyaltyReclamationSafetyController;
use Modules\Mining\Http\Controllers\MiningWeighbridgeSmelterController;

Route::middleware(['web', 'auth'])->prefix('mining')->group(function () {
    // Weighbridge operator route (mobile 375px)
    Route::middleware(['role:weighbridge_operator,admin,mining_admin,site_manager'])
        ->get('/weighbridge', function () {
            return view('mining::weighbridge');
        })->name('mining.weighbridge');

    // Mine dispatcher route
    Route::middleware(['role:dispatcher_mine,admin,mining_admin,site_manager'])
        ->get('/dispatch', function () {
            return view('mining::dispatch');
        })->name('mining.dispatch.view');

    // Mine manager & admin hub
    Route::middleware(['role:admin,mining_admin,site_manager'])
        ->get('/admin', [MiningController::class, 'index'])->name('mining.admin');

    // Finance officer royalties route
    Route::middleware(['role:finance_officer,admin,mining_admin,site_manager'])
        ->get('/royalties', [MiningRoyaltyReclamationSafetyController::class, 'pnbpRoyalty'])->name('mining.royalties');

    // HSE officer route
    Route::middleware(['role:hse_officer,admin,mining_admin,site_manager'])
        ->get('/hse', [MiningRoyaltyReclamationSafetyController::class, 'blastSafety'])->name('mining.hse');

    // General Mining hub & legacy routes
    Route::middleware(['role:admin,mining_admin,site_manager'])->group(function () {
        Route::get('/', [MiningController::class, 'index'])->name('mining.index');
        Route::post('/pits/{pit}/dispatch', [MiningController::class, 'dispatch'])->name('mining.dispatch');

        Route::prefix('operations')->name('mining.operations.')->group(function () {
            Route::get('/block-planning', [MiningExtractionDispatchController::class, 'blockPlanning'])->name('block-planning');
            Route::post('/block-planning/plan', [MiningExtractionDispatchController::class, 'planBlock'])->name('block-planning.plan');

            Route::get('/haul-dispatch', [MiningExtractionDispatchController::class, 'haulDispatch'])->name('haul-dispatch');
            Route::post('/haul-dispatch/truck', [MiningExtractionDispatchController::class, 'dispatchTruck'])->name('haul-dispatch.truck');

            Route::get('/weighbridge', [MiningWeighbridgeSmelterController::class, 'weighbridgeTickets'])->name('weighbridge');
            Route::post('/weighbridge/ticket', [MiningWeighbridgeSmelterController::class, 'recordTicket'])->name('weighbridge.ticket');

            Route::get('/grade-reconciliation', [MiningWeighbridgeSmelterController::class, 'oreGradeReconciliation'])->name('grade-reconciliation');
            Route::post('/grade-reconciliation/reconcile', [MiningWeighbridgeSmelterController::class, 'reconcileAssay'])->name('grade-reconciliation.reconcile');

            Route::get('/smelter', [MiningWeighbridgeSmelterController::class, 'smelterRuns'])->name('smelter');
            Route::post('/smelter/run', [MiningWeighbridgeSmelterController::class, 'executeSmelter'])->name('smelter.run');

            Route::get('/pnbp-royalty', [MiningRoyaltyReclamationSafetyController::class, 'pnbpRoyalty'])->name('pnbp-royalty');
            Route::post('/pnbp-royalty/calculate', [MiningRoyaltyReclamationSafetyController::class, 'calcRoyalty'])->name('pnbp-royalty.calculate');

            Route::get('/reclamation', [MiningRoyaltyReclamationSafetyController::class, 'reclamationBonds'])->name('reclamation');
            Route::post('/reclamation/bond', [MiningRoyaltyReclamationSafetyController::class, 'postBond'])->name('reclamation.bond');

            Route::get('/blast-safety', [MiningRoyaltyReclamationSafetyController::class, 'blastSafety'])->name('blast-safety');
            Route::post('/blast-safety/lock', [MiningRoyaltyReclamationSafetyController::class, 'lockBlast'])->name('blast-safety.lock');
        });
    });
});
