<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\TradeFinance\Http\Controllers\CbamDeclarationController;
use Modules\TradeFinance\Http\Controllers\ClearingController;
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

// D83-1 Clearing escrow USDT per L/C: petugas melihat semua; importir/eksportir hanya miliknya (Policy).
Route::middleware(['web', 'auth', 'role:admin,tf_officer,importer,exporter'])->prefix('trade-finance/clearing')->name('trade_finance.clearing.')->group(function () {
    Route::get('/', [ClearingController::class, 'index'])->name('index');
    Route::get('/{escrow}', [ClearingController::class, 'show'])->name('show');
});
Route::middleware(['web', 'auth', 'role:admin,tf_officer,importer'])->prefix('trade-finance/clearing')->name('trade_finance.clearing.')->group(function () {
    Route::post('/deposit', [ClearingController::class, 'deposit'])->name('deposit');
});
Route::middleware(['web', 'auth', 'role:admin,tf_officer'])->prefix('trade-finance/clearing')->name('trade_finance.clearing.')->group(function () {
    Route::post('/{escrow}/release', [ClearingController::class, 'release'])->name('release');
});

// D83-3 Sertifikat CBAM per kontainer ekspor ke UE.
Route::middleware(['web', 'auth', 'role:admin,compliance_officer'])->prefix('trade/cbam')->name('trade.cbam.')->group(function () {
    Route::get('/', [CbamDeclarationController::class, 'index'])->name('index');
    Route::post('/', [CbamDeclarationController::class, 'declare'])->name('declare');
});
