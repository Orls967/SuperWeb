<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AutoDex\Http\Controllers\CarCatalogController;
use Modules\AutoDex\Http\Controllers\GarageController;

Route::middleware(['web', 'auth', 'verified'])->prefix('autodex')->name('autodex.')->group(function () {
    // Katalog Mobil Global
    Route::get('/', [CarCatalogController::class, 'index'])->name('index');
    Route::get('/car/{car:slug}', [CarCatalogController::class, 'show'])->name('show');

    // Garasi & Wishlist User
    Route::get('/garage', [GarageController::class, 'index'])->name('garage.index');
    Route::post('/garage/{car}', [GarageController::class, 'toggleGarage'])->name('garage.toggle');
    Route::post('/wishlist/{car}', [GarageController::class, 'toggleWishlist'])->name('wishlist.toggle');
});
