<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pricing\Http\Controllers\PricingController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,procurement,auditor'])
    ->prefix('pricing')
    ->name('pricing.')
    ->group(function () {
        Route::get('/', [PricingController::class, 'index'])->name('index');
        Route::post('/price-lists', [PricingController::class, 'storePriceList'])->name('price-lists.store');
        Route::post('/price-lists/items', [PricingController::class, 'storePriceListItem'])->name('price-lists.items.store');
        Route::post('/price-lists/{list}/activate', [PricingController::class, 'activatePriceList'])->name('price-lists.activate');
        Route::post('/discounts', [PricingController::class, 'storeDiscount'])->name('discounts.store');
        Route::post('/promotions', [PricingController::class, 'storePromotion'])->name('promotions.store');
        Route::post('/promotions/claims', [PricingController::class, 'storeClaim'])->name('promotions.claims.store');
        Route::post('/claims/{claim}/validate', [PricingController::class, 'validateClaim'])->name('claims.validate');
        Route::post('/claims/{claim}/settle', [PricingController::class, 'settleClaim'])->name('claims.settle');
        Route::post('/margin-policies', [PricingController::class, 'storeMarginPolicy'])->name('margin-policies.store');
        Route::post('/overrides', [PricingController::class, 'storeOverride'])->name('overrides.store');
        Route::post('/overrides/{override}/decide', [PricingController::class, 'decideOverride'])->name('overrides.decide');
        Route::post('/analytics', [PricingController::class, 'computeAnalytics'])->name('analytics.compute');
    });
