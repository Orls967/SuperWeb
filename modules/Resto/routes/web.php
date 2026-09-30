<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Resto\Http\Controllers\IngredientController;
use Modules\Resto\Http\Controllers\KitchenController;
use Modules\Resto\Http\Controllers\MenuItemController;
use Modules\Resto\Http\Controllers\OutletController;
use Modules\Resto\Http\Controllers\PosController;

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

    // POS Kasir & Meja
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos/shift/open', [PosController::class, 'openShift'])->name('pos.shift.open');
    Route::post('pos/shift/close', [PosController::class, 'closeShift'])->name('pos.shift.close');
    Route::post('pos/shift/settle', [PosController::class, 'settleCash'])->name('pos.shift.settle');
    Route::post('pos/session/open', [PosController::class, 'openSession'])->name('pos.session.open');
    Route::get('pos/session/{session}', [PosController::class, 'getSession'])->name('pos.session.show');
    Route::post('pos/session/{session}/hidang', [PosController::class, 'presentHidang'])->name('pos.session.hidang');
    Route::post('pos/session/{session}/item', [PosController::class, 'addItem'])->name('pos.session.item');
    Route::post('pos/session/{session}/bill', [PosController::class, 'calculateBill'])->name('pos.session.bill');
    Route::post('pos/order/{order}/pay', [PosController::class, 'payOrder'])->name('pos.order.pay');
    Route::post('pos/order/{order}/void', [PosController::class, 'voidOrder'])->name('pos.order.void');
    Route::get('pos/order/{order}/receipt', [PosController::class, 'receipt'])->name('pos.order.receipt');

    // Outlets & Central Kitchens
    Route::resource('outlets', OutletController::class);
});
