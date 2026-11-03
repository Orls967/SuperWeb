<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Food\Http\Controllers\FoodBrandController;

Route::middleware(['auth', 'role:brand_manager,admin'])->prefix('food')->name('food.')->group(function () {
    Route::get('brands', [FoodBrandController::class, 'index'])->name('brands.index');
    Route::post('brands', [FoodBrandController::class, 'store'])->name('brands.store');
});
