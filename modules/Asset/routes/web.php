<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Asset\Http\Controllers\AssetAuditController;
use Modules\Asset\Http\Controllers\AssetController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,asset_manager'])
    ->prefix('assets')
    ->name('asset.')
    ->group(function () {
        // Fase 31 — rute statis harus sebelum /{asset} agar tidak tertangkap binding.
        Route::get('/audit', [AssetAuditController::class, 'index'])->name('audit');
        Route::get('/depreciation', [AssetAuditController::class, 'depreciation'])->name('depreciation.index');
        Route::post('/depreciation/run', [AssetAuditController::class, 'runDepreciation'])->name('depreciation.run');

        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::get('/create', [AssetController::class, 'create'])->name('create');
        Route::post('/', [AssetController::class, 'store'])->name('store');

        Route::get('/scan', [AssetController::class, 'create'])->name('scan.form');
        Route::post('/scan', [AssetController::class, 'scan'])->name('scan');

        Route::post('/verify-chain', [AssetController::class, 'verifyChain'])->name('verify-chain');

        Route::get('/{asset}', [AssetController::class, 'show'])->name('show');
        Route::post('/{asset}/move', [AssetController::class, 'move'])->name('move');
        Route::post('/{asset}/move/{location}', [AssetController::class, 'recordMoveApproval'])->name('move.approve');
        Route::post('/{asset}/checkout', [AssetController::class, 'checkOut'])->name('checkout');
        Route::post('/{asset}/checkin/{assignment}', [AssetController::class, 'checkIn'])->name('checkin');
        Route::post('/{asset}/insurance', [AssetController::class, 'addInsurance'])->name('insurance.store');
    });

// Fase 31 — operasi per aset (harus setelah rute statis /audit & /depreciation)
Route::middleware(['web', 'auth', 'verified', 'role:admin,asset_manager'])
    ->prefix('assets')
    ->name('asset.')
    ->group(function () {
        Route::post('/{asset}/depreciate', [AssetController::class, 'depreciate'])->name('depreciate');
        Route::post('/{asset}/revaluation', [AssetController::class, 'requestRevaluation'])->name('revaluation.request');
        Route::post('/{asset}/revaluation/{revaluation}/apply', [AssetController::class, 'applyRevaluation'])->name('revaluation.apply');
        Route::post('/{asset}/disposal', [AssetController::class, 'requestDisposal'])->name('disposal.request');
        Route::post('/{asset}/disposal/{disposal}/finalize', [AssetController::class, 'finalizeDisposal'])->name('disposal.finalize');
        Route::post('/{asset}/work-orders', [AssetController::class, 'scheduleWorkOrder'])->name('work-orders.store');
        Route::post('/{asset}/work-orders/{workOrder}/complete', [AssetController::class, 'completeWorkOrder'])->name('work-orders.complete');
        Route::post('/{asset}/leases', [AssetController::class, 'startLease'])->name('leases.store');
        Route::post('/{lease}/payments', [AssetController::class, 'payLeasePeriod'])->name('leases.pay');
    });
