<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Resto\Http\Controllers\IngredientController;
use Modules\Resto\Http\Controllers\MenuItemController;
use Modules\Resto\Http\Controllers\OutletController;

Route::middleware(['web', 'auth'])->prefix('resto')->name('resto.')->group(function () {
    // Menu Management
    Route::post('menu/calculate-cost', [MenuItemController::class, 'calculateCost'])->name('menu.calculate-cost');
    Route::resource('menu', MenuItemController::class);

    // Ingredients & Unit Conversions
    Route::post('ingredients/conversions', [IngredientController::class, 'storeConversion'])->name('ingredients.conversions.store');
    Route::resource('ingredients', IngredientController::class);

    // Outlets & Central Kitchens
    Route::resource('outlets', OutletController::class);
});
