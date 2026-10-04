<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Asset\Http\Controllers\AssetController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,asset_manager'])
    ->prefix('assets')
    ->name('asset.')
    ->group(function () {
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
