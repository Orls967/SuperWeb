<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Egy\Http\Controllers\EgyBillController;
use Modules\Egy\Http\Controllers\EgyConsolidatedBillController;
use Modules\Egy\Http\Controllers\EgyCoolingController;
use Modules\Egy\Http\Controllers\EgyCustomerPortalController;
use Modules\Egy\Http\Controllers\EgyDispatchController;
use Modules\Egy\Http\Controllers\EgyEscoController;
use Modules\Egy\Http\Controllers\EgyGridController;
use Modules\Egy\Http\Controllers\EgyGridMeterController;
use Modules\Egy\Http\Controllers\EgyMeterController;
use Modules\Egy\Http\Controllers\EgyMicrogridRecController;
use Modules\Egy\Http\Controllers\EgyPowerPurchaseController;
use Modules\Egy\Http\Controllers\EgyPpaController;
use Modules\Egy\Http\Controllers\EgyWasteController;
use Modules\Egy\Http\Controllers\EgyWaterController;

// 1. Grid Operator & Admin: /energy/grid & /energy/dispatch
Route::middleware(['web', 'auth', 'role:admin,grid_operator'])->prefix('energy')->group(function () {
    Route::get('/grid', [EgyGridController::class, 'dashboard'])->name('egy.grid.dashboard');
    Route::get('/dispatch', [EgyDispatchController::class, 'index'])->name('egy.dispatch.index');
    Route::post('/dispatch', [EgyDispatchController::class, 'run'])->name('egy.dispatch.store');
    Route::post('/dispatch/run', [EgyDispatchController::class, 'run'])->name('egy.dispatch.run');
});

// 2. Energy Billing & Admin: /energy/meters & /energy/bills
Route::middleware(['web', 'auth', 'role:admin,energy_billing'])->prefix('energy')->group(function () {
    Route::get('/meters', [EgyMeterController::class, 'index'])->name('egy.meters.index');
    Route::get('/bills', [EgyBillController::class, 'index'])->name('egy.bills.index');
    Route::get('/bills/export', [EgyBillController::class, 'export'])->name('egy.bills.export');
    Route::get('/bills/{id}', [EgyBillController::class, 'show'])->name('egy.bills.show');
    Route::post('/bills/generate', [EgyBillController::class, 'generate'])->name('egy.bills.generate');
    Route::post('/bills/{id}/pay', [EgyBillController::class, 'pay'])->name('egy.bills.pay');
    Route::post('/bills/{id}/adjust', [EgyBillController::class, 'adjust'])->name('egy.bills.adjust');
});

// 3. Genco Trader & Admin: /energy/ppa
Route::middleware(['web', 'auth', 'role:admin,genco_trader'])->prefix('energy')->group(function () {
    Route::get('/ppa', [EgyPpaController::class, 'index'])->name('egy.ppa.list');
    Route::get('/ppa/{id}', [EgyPpaController::class, 'show'])->name('egy.ppa.detail');
    Route::post('/ppa/{id}/settle', [EgyPpaController::class, 'settle'])->name('egy.ppa.process_settle');
});

// 4. Customer Portal: /customer/energy
Route::middleware(['web', 'auth', 'role:admin,customer'])->prefix('customer/energy')->group(function () {
    Route::get('/', [EgyCustomerPortalController::class, 'index'])->name('egy.customer.index');
    Route::get('/bills/{id}', [EgyCustomerPortalController::class, 'showBill'])->name('egy.customer.bill');
});

// 5. Fase 127: Utility Operator — Water, Waste, District Cooling
Route::middleware(['web', 'auth', 'role:admin,utility_operator'])->prefix('energy')->group(function () {
    Route::get('/water', [EgyWaterController::class, 'index'])->name('egy.water.index');
    Route::get('/water/leaks', [EgyWaterController::class, 'leaks'])->name('egy.water.leaks');
    Route::get('/water/export', [EgyWaterController::class, 'exportAuditReport'])->name('egy.water.export');
    Route::get('/waste', [EgyWasteController::class, 'index'])->name('egy.waste.index');
    Route::get('/waste/export', [EgyWasteController::class, 'exportProductionReport'])->name('egy.waste.export');
    Route::get('/district-cooling', [EgyCoolingController::class, 'index'])->name('egy.cooling.index');
    Route::get('/district-cooling/export', [EgyCoolingController::class, 'exportPerformanceReport'])->name('egy.cooling.export');
});

// 6. Fase 127: Energy Billing — Consolidated Multi-Utility Invoices
Route::middleware(['web', 'auth', 'role:admin,energy_billing'])->prefix('energy')->group(function () {
    Route::get('/consolidated-bills', [EgyConsolidatedBillController::class, 'index'])->name('egy.bills.consolidated.index');
    Route::get('/consolidated-bills/export', [EgyConsolidatedBillController::class, 'export'])->name('egy.bills.consolidated.export');
});
Route::middleware(['web', 'auth', 'role:admin,customer,energy_billing'])->prefix('energy')->group(function () {
    Route::get('/consolidated-bills/{id}', [EgyConsolidatedBillController::class, 'show'])->name('egy.bills.consolidated.show');
});

// 7. Fase 127: Energy Auditor — ESCO Performance Contracting
Route::middleware(['web', 'auth', 'role:admin,energy_auditor'])->prefix('energy')->group(function () {
    Route::get('/esco', [EgyEscoController::class, 'index'])->name('egy.esco.index');
});
Route::middleware(['web', 'auth', 'role:admin,customer,energy_auditor'])->prefix('energy')->group(function () {
    Route::get('/esco/{id}', [EgyEscoController::class, 'show'])->name('egy.esco.show');
});

// 8. Shared overview and legacy endpoints for backward compatibility
Route::middleware(['web', 'auth', 'role:admin,energy_auditor,genco_trader,grid_operator,renewable_dev'])->prefix('energy')->group(function () {
    Route::get('/', [EgyGridMeterController::class, 'index'])->name('egy.grid.index');
    Route::get('/overview', [EgyGridMeterController::class, 'index'])->name('energy.index');
    Route::get('/legacy/meters', [EgyGridMeterController::class, 'meters'])->name('egy.grid.meters');
    Route::get('/legacy/dispatch', [EgyGridMeterController::class, 'dispatchView'])->name('egy.grid.dispatch');
    Route::get('/invoices', [EgyGridMeterController::class, 'invoicesView'])->name('egy.grid.invoices');
    Route::post('/telemetry', [EgyGridMeterController::class, 'ingestTelemetry'])->name('egy.grid.telemetry');
    Route::post('/nodes/{nodeId}/anomaly', [EgyGridMeterController::class, 'scanAnomaly'])->name('egy.grid.anomaly');

    Route::get('/legacy/ppa', [EgyPowerPurchaseController::class, 'index'])->name('egy.ppa.index');
    Route::get('/legacy/ppa/{id}', [EgyPowerPurchaseController::class, 'show'])->name('egy.ppa.show');
    Route::post('/legacy/ppa/{id}/settle', [EgyPowerPurchaseController::class, 'settle'])->name('egy.ppa.settle');

    Route::get('/microgrid', [EgyMicrogridRecController::class, 'index'])->name('egy.microgrid.index');
    Route::post('/microgrid/dispatch', [EgyMicrogridRecController::class, 'dispatchBattery'])->name('egy.microgrid.dispatch');
    Route::post('/microgrid/rec/mint', [EgyMicrogridRecController::class, 'mintRec'])->name('egy.rec.mint');
});
