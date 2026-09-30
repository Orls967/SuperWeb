<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Resto\Http\Controllers\AnalyticsController;
use Modules\Resto\Http\Controllers\CateringController;
use Modules\Resto\Http\Controllers\DeliveryController;
use Modules\Resto\Http\Controllers\FranchiseController;
use Modules\Resto\Http\Controllers\IngredientController;
use Modules\Resto\Http\Controllers\KitchenController;
use Modules\Resto\Http\Controllers\MenuItemController;
use Modules\Resto\Http\Controllers\OutletController;
use Modules\Resto\Http\Controllers\PosController;
use Modules\Resto\Http\Controllers\PurchaseController;
use Modules\Resto\Http\Controllers\StockCountController;
use Modules\Resto\Http\Controllers\StockTransferController;

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

    // Rantai Pasok & Purchase Orders
    Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
    Route::post('purchases/{purchase}/pay', [PurchaseController::class, 'pay'])->name('purchases.pay');
    Route::resource('purchases', PurchaseController::class);

    // Transfer Antar Outlet
    Route::post('transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('transfers.receive');
    Route::resource('transfers', StockTransferController::class);

    // Stock Opname
    Route::post('stock-counts/{stock_count}/approve', [StockCountController::class, 'approve'])->name('stock-counts.approve');
    Route::resource('stock-counts', StockCountController::class);

    // Delivery & Bungkus Jarak Jauh
    Route::post('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('deliveries.status');
    Route::post('deliveries/{delivery}/fail', [DeliveryController::class, 'fail'])->name('deliveries.fail');
    Route::resource('deliveries', DeliveryController::class);

    // Katering & Pesanan Acara
    Route::post('catering/{order}/deposit', [CateringController::class, 'holdDeposit'])->name('catering.deposit');
    Route::post('catering/{order}/complete', [CateringController::class, 'complete'])->name('catering.complete');
    Route::post('catering/{order}/cancel', [CateringController::class, 'cancel'])->name('catering.cancel');
    Route::resource('catering', CateringController::class);

    // Analitik & Menu Engineering
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Franchise & Royalti
    Route::get('franchise', [FranchiseController::class, 'index'])->name('franchise.index');
    Route::post('franchise/contract', [FranchiseController::class, 'storeContract'])->name('franchise.contract.store');
    Route::post('franchise/royalty', [FranchiseController::class, 'runRoyalty'])->name('franchise.royalty.run');
});
