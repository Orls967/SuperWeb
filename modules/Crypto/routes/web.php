<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Crypto\Http\Controllers\CryptoAlertController;
use Modules\Crypto\Http\Controllers\CryptoMarketController;
use Modules\Crypto\Http\Controllers\CryptoPortfolioController;
use Modules\Crypto\Http\Controllers\CryptoTradeController;

Route::middleware(['web'])->group(function () {
    // Market & Public Prices
    Route::get('/crypto', [CryptoMarketController::class, 'index'])->name('crypto.market.index');
    Route::get('/crypto/market/{symbol}', [CryptoMarketController::class, 'show'])->name('crypto.market.show');
    Route::get('/crypto/api/prices', [CryptoMarketController::class, 'pricesApi'])->name('crypto.api.prices');
    Route::get('/crypto/api/chart/{symbol}', [CryptoMarketController::class, 'chartApi'])->name('crypto.api.chart');

    // Authenticated Trading & Portfolio
    Route::middleware(['auth'])->group(function () {
        Route::get('/crypto/portfolio', [CryptoPortfolioController::class, 'index'])->name('crypto.portfolio.index');
        Route::post('/crypto/quote', [CryptoTradeController::class, 'quote'])->name('crypto.trade.quote');
        Route::post('/crypto/trade/execute', [CryptoTradeController::class, 'execute'])->name('crypto.trade.execute');

        // Alerts
        Route::get('/crypto/alerts', [CryptoAlertController::class, 'index'])->name('crypto.alerts.index');
        Route::post('/crypto/alerts', [CryptoAlertController::class, 'store'])->name('crypto.alerts.store');
        Route::delete('/crypto/alerts/{alert}', [CryptoAlertController::class, 'destroy'])->name('crypto.alerts.destroy');
    });
});
