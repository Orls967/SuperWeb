<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Rwa\Http\Controllers\RwaController;
use Modules\Rwa\Http\Controllers\RwaExchangeController;
use Modules\Rwa\Http\Controllers\RwaOfferingsController;

Route::middleware(['web', 'auth', 'role:admin,rwa_admin,asset_manager,investor'])->prefix('rwa')->group(function () {
    Route::get('/', [RwaController::class, 'index'])->name('rwa.index');
    Route::get('/offerings', [RwaExchangeController::class, 'offerings'])->name('rwa.offerings');
    Route::post('/offerings/{offering}/subscribe', [RwaOfferingsController::class, 'subscribe'])->name('rwa.offerings.subscribe');
    Route::get('/orderbook', [RwaExchangeController::class, 'orderbook'])->name('rwa.orderbook');
    Route::get('/yield-distribution', [RwaExchangeController::class, 'yieldDistribution'])->name('rwa.yield-distribution');
    Route::post('/assets/{asset}/distribute', [RwaController::class, 'distribute'])->name('rwa.assets.distribute');

    // RWA Secondary Exchange, Offerings & Yield Deepening Routes
    Route::prefix('exchange')->name('rwa.exchange.')->group(function () {
        Route::get('/offerings', [RwaExchangeController::class, 'offerings'])->name('offerings');
        Route::get('/orderbook', [RwaExchangeController::class, 'orderbook'])->name('orderbook');
        Route::get('/yield-distribution', [RwaExchangeController::class, 'yieldDistribution'])->name('yield-distribution');
        Route::get('/holdings', [RwaExchangeController::class, 'holdings'])->name('holdings');
        Route::post('/subscribe-offering', [RwaExchangeController::class, 'subscribeOffering'])->name('subscribe-offering');
        Route::post('/match-trade', [RwaExchangeController::class, 'matchTrade'])->name('match-trade');
        Route::post('/distribute-yield', [RwaExchangeController::class, 'distributeYield'])->name('distribute-yield');
    });
});
