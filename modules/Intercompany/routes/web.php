<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Intercompany\Http\Controllers\IntercompanyConsolidationController;
use Modules\Intercompany\Http\Controllers\IntercompanyController;

Route::middleware(['web', 'auth', 'role:admin,auditor,legal,treasury'])->prefix('intercompany')->name('intercompany.')->group(function () {
    Route::get('/', [IntercompanyController::class, 'index'])->name('index');
    Route::get('/transactions', [IntercompanyController::class, 'index'])->name('transactions');

    Route::prefix('consolidation')->name('consolidation.')->group(function () {
        Route::get('/', [IntercompanyConsolidationController::class, 'index'])->name('index');
        Route::post('/mirror-tx', [IntercompanyConsolidationController::class, 'createMirrorTransaction'])->name('mirror-tx');
        Route::post('/markup/{rule}', [IntercompanyConsolidationController::class, 'applyMarkup'])->name('markup');
        Route::post('/elimination', [IntercompanyConsolidationController::class, 'postElimination'])->name('elimination');
    });
});
