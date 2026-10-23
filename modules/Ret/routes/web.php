<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ret\Http\Controllers\RetDarkStoreFulfillmentController;
use Modules\Ret\Http\Controllers\RetInventoryPricingController;
use Modules\Ret\Http\Controllers\RetOmnichannelOrderController;

Route::middleware(['web', 'auth', 'role:admin,retail_ops,marketplace_mgr,category_mgr,last_mile_cs'])->prefix('retail')->group(function () {
    Route::get('/', [RetOmnichannelOrderController::class, 'index'])->name('ret.orders.index');
    Route::get('/overview', [RetOmnichannelOrderController::class, 'index'])->name('retail.index');
    Route::get('/orders/{id}', [RetOmnichannelOrderController::class, 'show'])->name('ret.orders.show');
    Route::post('/checkout', [RetOmnichannelOrderController::class, 'checkout'])->name('ret.orders.checkout');
    Route::post('/orders/pickup', [RetOmnichannelOrderController::class, 'pickup'])->name('ret.orders.pickup');

    Route::get('/darkstore', [RetDarkStoreFulfillmentController::class, 'index'])->name('ret.darkstore.index');
    Route::get('/darkstore/pickpack', [RetDarkStoreFulfillmentController::class, 'pickPackView'])->name('ret.darkstore.pickpack');
    Route::post('/darkstore/allocate', [RetDarkStoreFulfillmentController::class, 'allocate'])->name('ret.darkstore.allocate');

    Route::get('/pricing', [RetInventoryPricingController::class, 'index'])->name('ret.pricing.index');
    Route::post('/pricing/markdown', [RetInventoryPricingController::class, 'markdown'])->name('ret.pricing.markdown');
    Route::post('/pricing/shrinkage', [RetInventoryPricingController::class, 'reconcileShrinkage'])->name('ret.pricing.shrinkage');

    Route::get('/compliance', [RetInventoryPricingController::class, 'complianceView'])->name('ret.compliance.index');
    Route::post('/compliance/audit', [RetInventoryPricingController::class, 'auditPlanogram'])->name('ret.compliance.audit');
});
