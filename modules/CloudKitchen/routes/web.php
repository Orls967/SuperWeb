<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CloudKitchen\Http\Controllers\CloudKitchenController;

Route::middleware(['web', 'auth', 'role:admin,kitchen,outlet_manager,customer'])->prefix('cloud-kitchen')->group(function () {
    Route::get('/', [CloudKitchenController::class, 'index'])->name('cloud_kitchen.index');
    Route::post('/subscriptions/{subscription}/dispatch', [CloudKitchenController::class, 'dispatchMeal'])->name('cloud_kitchen.subscriptions.dispatch');
});
