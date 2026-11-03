<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Circular\Http\Controllers\CircularMarketplaceController;

Route::middleware(['web', 'auth', 'verified'])->prefix('circular')->name('circular.')->group(function (): void {
    // Penjual material sekunder / limbah industri
    Route::middleware(['role:admin,material_seller'])->group(function (): void {
        Route::get('/listings', [CircularMarketplaceController::class, 'listingsIndex'])->name('listings.index');
        Route::post('/listings', [CircularMarketplaceController::class, 'storeListing'])->name('listings.store');
    });

    // Pembeli material sekunder / pabrik daur ulang
    Route::middleware(['role:admin,material_buyer'])->group(function (): void {
        Route::get('/market', [CircularMarketplaceController::class, 'marketIndex'])->name('market.index');
        Route::post('/orders', [CircularMarketplaceController::class, 'storeOrder'])->name('orders.store');
        Route::post('/auctions/bids', [CircularMarketplaceController::class, 'submitBid'])->name('auctions.bids.store');
    });

    // Detail order & bid (proteksi kepemilikan / anti-IDOR di controller)
    Route::middleware(['role:admin,circular_admin,material_buyer,material_seller'])->group(function (): void {
        Route::get('/orders/{order}', [CircularMarketplaceController::class, 'showOrder'])->name('orders.show');
        Route::get('/bids/{bid}', [CircularMarketplaceController::class, 'showBid'])->name('bids.show');
    });

    // Operator / Admin Marketplace Sirkular
    Route::middleware(['role:admin,circular_admin'])->group(function (): void {
        Route::get('/auctions', [CircularMarketplaceController::class, 'auctionsIndex'])->name('auctions.index');
        Route::post('/auctions', [CircularMarketplaceController::class, 'storeAuctionRound'])->name('auctions.store');
        Route::post('/auctions/{round}/close', [CircularMarketplaceController::class, 'closeRound'])->name('auctions.close');
        Route::post('/orders/{order}/receipt', [CircularMarketplaceController::class, 'recordReceipt'])->name('orders.receipt');
        Route::post('/orders/{order}/settle', [CircularMarketplaceController::class, 'settleOrder'])->name('orders.settle');
    });
});
