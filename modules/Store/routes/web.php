<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\Admin\AdminOrderController;
use Modules\Store\Http\Controllers\Admin\AdminProductController;
use Modules\Store\Http\Controllers\C2cController;
use Modules\Store\Http\Controllers\CartController;
use Modules\Store\Http\Controllers\CatalogController;
use Modules\Store\Http\Controllers\CheckoutController;
use Modules\Store\Http\Controllers\OrderController;

Route::middleware(['web'])->group(function () {
    // Public Catalog
    Route::get('/store', [CatalogController::class, 'index'])->name('store.catalog.index');
    Route::get('/store/products/{slug}', [CatalogController::class, 'show'])->name('store.products.show');

    // Marketplace mobil bekas antar pengguna (C2C)
    Route::get('/store/mobil-bekas', [C2cController::class, 'index'])->name('store.c2c.index');

    // Authenticated Store Features
    Route::middleware(['auth'])->group(function () {
        // C2C: penjual
        Route::get('/store/mobil-bekas/penjualan-saya', [C2cController::class, 'mySales'])->name('store.c2c.mySales');
        Route::post('/store/mobil-bekas/listing', [C2cController::class, 'store'])->name('store.c2c.store');
        Route::post('/store/mobil-bekas/listing/{product}/unlist', [C2cController::class, 'unlist'])->name('store.c2c.unlist');

        // C2C: pembeli
        Route::get('/store/mobil-bekas/{product:slug}/beli', [C2cController::class, 'buyForm'])->name('store.c2c.buyForm');
        Route::post('/store/mobil-bekas/{product:slug}/beli', [C2cController::class, 'buy'])->name('store.c2c.buy');

        // C2C: alur escrow
        Route::post('/store/orders/{order:uuid}/c2c/handover', [C2cController::class, 'handover'])->name('store.c2c.handover');
        Route::post('/store/orders/{order:uuid}/c2c/confirm', [C2cController::class, 'confirm'])->name('store.c2c.confirm');
        Route::post('/store/orders/{order:uuid}/c2c/dispute', [C2cController::class, 'dispute'])->name('store.c2c.dispute');
        Route::post('/store/orders/{order:uuid}/c2c/cancel', [C2cController::class, 'cancel'])->name('store.c2c.cancel');
        // Cart
        Route::get('/store/cart', [CartController::class, 'index'])->name('store.cart.index');
        Route::get('/store/cart/count', [CartController::class, 'count'])->name('store.cart.count');
        Route::post('/store/cart', [CartController::class, 'store'])->name('store.cart.store');
        Route::patch('/store/cart/{product}', [CartController::class, 'update'])->name('store.cart.update');
        Route::delete('/store/cart/{product}', [CartController::class, 'destroy'])->name('store.cart.destroy');

        // Checkout
        Route::get('/store/checkout', [CheckoutController::class, 'index'])->name('store.checkout.index');
        Route::post('/store/checkout', [CheckoutController::class, 'process'])->name('store.checkout.process');

        // Orders
        Route::get('/store/orders', [OrderController::class, 'index'])->name('store.orders.index');
        Route::get('/store/orders/{order:uuid}', [OrderController::class, 'show'])->name('store.orders.show');
        Route::post('/store/orders/{order:uuid}/cancel', [OrderController::class, 'cancel'])->name('store.orders.cancel');
        Route::post('/store/orders/{order:uuid}/confirm', [OrderController::class, 'confirm'])->name('store.orders.confirm');
    });

    // Admin Store Operations
    Route::middleware(['auth', 'role:admin'])->prefix('admin/store')->name('store.admin.')->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order:uuid}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order:uuid}/ship', [AdminOrderController::class, 'ship'])->name('orders.ship');
        Route::post('/orders/{order:uuid}/complete', [AdminOrderController::class, 'complete'])->name('orders.complete');
        Route::post('/orders/{order:uuid}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order:uuid}/resolve-dispute', [AdminOrderController::class, 'resolveDispute'])->name('orders.resolveDispute');

        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::post('/products/{product}/toggle', [AdminProductController::class, 'toggleListing'])->name('products.toggle');
        Route::post('/products/{product}/adjust-stock', [AdminProductController::class, 'adjustStock'])->name('products.adjustStock');
        Route::post('/products/cars/listing', [AdminProductController::class, 'createCarListing'])->name('products.createCarListing');
    });
});
