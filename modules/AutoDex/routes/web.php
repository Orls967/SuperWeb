<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AutoDex\Http\Controllers\CarCatalogController;
use Modules\AutoDex\Http\Controllers\GarageController;

Route::middleware(['web', 'auth', 'verified'])->prefix('autodex')->name('autodex.')->group(function () {
    // Read: Katalog & Garasi (admin, customer, auditor)
    Route::middleware('role:admin,customer,auditor')->group(function () {
        Route::get('/', [CarCatalogController::class, 'index'])->name('index');
        Route::get('/car/{car:slug}', [CarCatalogController::class, 'show'])->name('show');
        Route::get('/garage', [GarageController::class, 'index'])->name('garage.index');
    });

    // Mutations: Garasi & Wishlist User (admin, customer - auditor forbidden)
    Route::middleware('role:admin,customer')->group(function () {
        Route::post('/garage/{car}', [GarageController::class, 'toggleGarage'])->name('garage.toggle');
        Route::post('/wishlist/{car}', [GarageController::class, 'toggleWishlist'])->name('wishlist.toggle');
    });
});
