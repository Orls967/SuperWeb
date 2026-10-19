<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\TradeFinance\Http\Controllers\CrossBorderController;
use Modules\TradeFinance\Http\Controllers\TradeFinanceController;

Route::middleware(['web', 'auth', 'role:admin,auditor,procurement,treasury'])->prefix('trade-finance')->name('trade_finance.')->group(function () {
    Route::get('/', [TradeFinanceController::class, 'index'])->name('index');
    Route::get('/lcs', [TradeFinanceController::class, 'index'])->name('lcs');

    Route::prefix('cross-border')->name('crossborder.')->group(function () {
        Route::get('/', [CrossBorderController::class, 'index'])->name('index');
        Route::post('/deposit', [CrossBorderController::class, 'deposit'])->name('deposit');
        Route::post('/{escrow}/release', [CrossBorderController::class, 'release'])->name('release');
        Route::post('/{escrow}/dispute', [CrossBorderController::class, 'dispute'])->name('dispute');
        Route::post('/{escrow}/resolve-dispute', [CrossBorderController::class, 'resolveDispute'])->name('resolve-dispute');
        Route::post('/cbam', [CrossBorderController::class, 'issueCbam'])->name('cbam');
    });
});
