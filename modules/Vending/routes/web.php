<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Vending\Http\Controllers\SmartVendingController;

Route::middleware(['web', 'auth', 'role:admin,vending_operator,store_manager'])->prefix('vending')->group(function () {
    Route::get('/', [SmartVendingController::class, 'index'])->name('vending.index');
    Route::post('/units/{unit}/dispense', [SmartVendingController::class, 'dispense'])->name('vending.units.dispense');
});
