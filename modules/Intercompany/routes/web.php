<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Intercompany\Http\Controllers\IntercompanyConsolidationController;
use Modules\Intercompany\Http\Controllers\IntercompanyController;

Route::middleware(['web', 'auth', 'role:admin,auditor,legal,treasury'])->prefix('intercompany')->name('intercompany.')->group(function () {
    Route::get('/', [IntercompanyController::class, 'index'])->name('index');
    Route::get('/transactions', [IntercompanyController::class, 'transactions'])->name('transactions');
    Route::post('/transactions', [IntercompanyController::class, 'storeTransaction'])->name('transactions.store');

    Route::get('/loans', [IntercompanyController::class, 'loans'])->name('loans');
    Route::post('/loans', [IntercompanyController::class, 'issueLoan'])->name('loans.store');

    Route::get('/eliminations', [IntercompanyController::class, 'eliminations'])->name('eliminations');
    Route::get('/transfer-pricing', [IntercompanyController::class, 'transferPricing'])->name('transfer-pricing');

    Route::get('/cost-sharing', [IntercompanyController::class, 'costSharing'])->name('cost-sharing');
    Route::post('/cost-sharing', [IntercompanyController::class, 'allocateCosts'])->name('cost-sharing.allocate');

    Route::get('/asset-transfers', [IntercompanyController::class, 'assetTransfers'])->name('asset-transfers');
    Route::post('/assets/transfer', [IntercompanyController::class, 'transferAsset'])->name('assets.transfer');
    Route::post('/stocks/transfer', [IntercompanyController::class, 'transferStock'])->name('stocks.transfer');

    Route::get('/segments', [IntercompanyController::class, 'segments'])->name('segments');
    Route::get('/export/matrix', [IntercompanyController::class, 'exportMatrix'])->name('export.matrix');

    // Kompatibilitas Warisan Konsolidasi
    Route::prefix('consolidation')->name('consolidation.')->group(function () {
        Route::get('/', [IntercompanyConsolidationController::class, 'index'])->name('index');
        Route::post('/mirror-tx', [IntercompanyConsolidationController::class, 'createMirrorTransaction'])->name('mirror-tx');
        Route::post('/markup/{rule}', [IntercompanyConsolidationController::class, 'applyMarkup'])->name('markup');
        Route::post('/elimination', [IntercompanyConsolidationController::class, 'postElimination'])->name('elimination');
    });
});
