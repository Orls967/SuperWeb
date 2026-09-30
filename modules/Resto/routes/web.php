<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Resto\Http\Controllers\IngredientController;
use Modules\Resto\Http\Controllers\KitchenController;
use Modules\Resto\Http\Controllers\MenuItemController;
use Modules\Resto\Http\Controllers\OutletController;

Route::middleware(['web', 'auth'])->prefix('resto')->name('resto.')->group(function () {
    // Menu Management
    Route::post('menu/calculate-cost', [MenuItemController::class, 'calculateCost'])->name('menu.calculate-cost');
    Route::resource('menu', MenuItemController::class);

    // Ingredients & Unit Conversions
    Route::post('ingredients/conversions', [IngredientController::class, 'storeConversion'])->name('ingredients.conversions.store');
    Route::resource('ingredients', IngredientController::class);

    // Kitchen & Etalase Monitor
    Route::get('kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('kitchen/simulate', [KitchenController::class, 'simulate'])->name('kitchen.simulate');
    Route::post('kitchen/cook', [KitchenController::class, 'cook'])->name('kitchen.cook');
    Route::post('kitchen/trays/{tray}/discard', [KitchenController::class, 'discardTray'])->name('kitchen.trays.discard');
    Route::post('kitchen/trays/{tray}/recirculate', [KitchenController::class, 'recirculateTray'])->name('kitchen.trays.recirculate');

    // Outlets & Central Kitchens
    Route::resource('outlets', OutletController::class);
});
