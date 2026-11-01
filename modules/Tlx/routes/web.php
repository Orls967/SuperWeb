<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Tlx\Http\Controllers\TelecomMvpController;
use Modules\Tlx\Http\Controllers\TlxDataCenterColocationController;
use Modules\Tlx\Http\Controllers\TlxNetworkSliceController;
use Modules\Tlx\Http\Controllers\TlxTelecomBillingController;

// Telecom MVP routes (fine-grained role guards)
Route::middleware(['web', 'auth', 'role:admin,noc_engineer,tlx_admin'])->prefix('telecom/noc')->group(function () {
    Route::get('/alarms', [TelecomMvpController::class, 'nocAlarms'])->name('tlx.noc.alarms');
});

Route::middleware(['web', 'auth', 'role:admin,datacenter_ops,tlx_admin'])->prefix('telecom/datacenter')->group(function () {
    Route::get('/colocation', [TelecomMvpController::class, 'datacenterColocation'])->name('tlx.datacenter.colocation');
});

Route::middleware(['web', 'auth', 'role:admin,telecom_billing,tlx_admin'])->prefix('telecom/billing')->group(function () {
    Route::get('/connectivity', [TelecomMvpController::class, 'connectivityBilling'])->name('tlx.billing.connectivity');
    Route::get('/connectivity/{id}', [TelecomMvpController::class, 'connectivityInvoiceDetail'])->name('tlx.billing.connectivity.detail');
});

// Existing / legacy telecom routes
Route::middleware(['web', 'auth', 'role:admin,tlx_admin,noc_engineer,datacenter_ops,telecom_billing'])->prefix('telecom')->group(function () {
    Route::get('/', [TlxNetworkSliceController::class, 'index'])->name('tlx.network.index');
    Route::get('/overview', [TlxNetworkSliceController::class, 'index'])->name('telecom.index');
    Route::get('/network/slices', [TlxNetworkSliceController::class, 'slices'])->name('tlx.network.slices');
    Route::post('/network/slices/provision', [TlxNetworkSliceController::class, 'provisionSlice'])->name('tlx.network.slices.provision');
    Route::post('/network/esim/activate', [TlxNetworkSliceController::class, 'activateEsim'])->name('tlx.network.esim.activate');

    Route::get('/datacenter', [TlxDataCenterColocationController::class, 'index'])->name('tlx.datacenter.index');
    Route::get('/datacenter/racks', [TlxDataCenterColocationController::class, 'racks'])->name('tlx.datacenter.racks');
    Route::post('/datacenter/racks/allocate', [TlxDataCenterColocationController::class, 'allocateRack'])->name('tlx.datacenter.racks.allocate');
    Route::post('/datacenter/pue/enforce', [TlxDataCenterColocationController::class, 'enforcePue'])->name('tlx.datacenter.pue.enforce');

    Route::get('/billing', [TlxTelecomBillingController::class, 'index'])->name('tlx.billing.index');
    Route::get('/billing/roaming', [TlxTelecomBillingController::class, 'roaming'])->name('tlx.billing.roaming');
    Route::post('/billing/cdr/rate', [TlxTelecomBillingController::class, 'billCdr'])->name('tlx.billing.cdr.rate');
    Route::post('/billing/roaming/settle', [TlxTelecomBillingController::class, 'settleRoaming'])->name('tlx.billing.roaming.settle');
});
