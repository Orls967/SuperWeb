<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Intercompany\Http\Controllers\IntercompanyApiController;

Route::middleware(['api', 'auth', 'role:admin,auditor,legal,treasury'])->prefix('api/intercompany')->name('api.intercompany.')->group(function () {
    Route::get('/entities', [IntercompanyApiController::class, 'getEntities'])->name('entities');
    Route::get('/transactions', [IntercompanyApiController::class, 'getTransactions'])->name('transactions.index');
    Route::post('/transactions', [IntercompanyApiController::class, 'storeTransaction'])->name('transactions.store');
    Route::get('/loans', [IntercompanyApiController::class, 'getLoans'])->name('loans.index');
    Route::post('/loans', [IntercompanyApiController::class, 'issueLoan'])->name('loans.store');
    Route::get('/eliminations', [IntercompanyApiController::class, 'getEliminations'])->name('eliminations.index');
    Route::post('/eliminations/run', [IntercompanyApiController::class, 'runElimination'])->name('eliminations.run');
    Route::get('/matrix', [IntercompanyApiController::class, 'getMatrix'])->name('matrix');
    Route::post('/transfer-pricing/evaluate', [IntercompanyApiController::class, 'evaluateTransferPricing'])->name('tp.evaluate');
    Route::post('/multivariate-benchmark', [IntercompanyApiController::class, 'evaluateMultivariateBenchmark'])->name('tp.multivariate');
    Route::post('/multilateral-netting', [IntercompanyApiController::class, 'calculateMultilateralNetting'])->name('netting.calculate');
    Route::post('/consolidation-tree', [IntercompanyApiController::class, 'executeConsolidationRollup'])->name('consolidation.tree');
    Route::post('/nci-dividend-cascade', [IntercompanyApiController::class, 'calculateNciDividendCascade'])->name('nci.cascade');
    Route::get('/nci', [IntercompanyApiController::class, 'getNciSummary'])->name('nci');
});
