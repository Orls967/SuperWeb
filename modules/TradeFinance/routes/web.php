<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\TradeFinance\Http\Controllers\CrossBorderController;
use Modules\TradeFinance\Http\Controllers\TradeFinanceController;
use Modules\TradeFinance\Http\Controllers\TradeFinanceOperationsController;

Route::middleware(['web', 'auth', 'role:admin,auditor,procurement,treasury'])->prefix('trade-finance')->name('trade_finance.')->group(function () {
    Route::get('/', [TradeFinanceController::class, 'index'])->name('index');
    Route::get('/lcs', [TradeFinanceController::class, 'index'])->name('lcs');

    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('/', [TradeFinanceOperationsController::class, 'index'])->name('index');
        Route::post('/lc/issue', [TradeFinanceOperationsController::class, 'issueLc'])->name('lc.issue');
        Route::post('/lc/{lc}/verify-presentation', [TradeFinanceOperationsController::class, 'verifyPresentation'])->name('lc.verify-presentation');
        Route::post('/scf/discount', [TradeFinanceOperationsController::class, 'discountScf'])->name('scf.discount');
    });

    Route::prefix('cross-border')->name('crossborder.')->group(function () {
        Route::get('/', [CrossBorderController::class, 'index'])->name('index');
        Route::post('/deposit', [CrossBorderController::class, 'deposit'])->name('deposit');
        Route::post('/{escrow}/release', [CrossBorderController::class, 'release'])->name('release');
        Route::post('/{escrow}/dispute', [CrossBorderController::class, 'dispute'])->name('dispute');
        Route::post('/{escrow}/resolve-dispute', [CrossBorderController::class, 'resolveDispute'])->name('resolve-dispute');
        Route::post('/cbam', [CrossBorderController::class, 'issueCbam'])->name('cbam');
    });
});
