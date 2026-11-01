<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\B2b\Http\Controllers\B2bController;
use Modules\B2b\Http\Controllers\B2bOperationsController;

Route::middleware(['web', 'auth', 'role:admin,b2b_buyer,procurement'])->prefix('b2b')->name('b2b.')->group(function () {
    Route::get('/', [B2bController::class, 'index'])->name('index');

    Route::get('/dashboard', [B2bOperationsController::class, 'dashboard'])->name('dashboard');

    Route::get('/wholesale', [B2bOperationsController::class, 'wholesaleIndex'])->name('wholesale.index');
    Route::post('/wholesale/publish', [B2bOperationsController::class, 'publishWholesale'])->name('wholesale.publish');

    Route::get('/auction', [B2bOperationsController::class, 'auctionIndex'])->name('auction.index');
    Route::post('/auction/create', [B2bOperationsController::class, 'createAuction'])->name('auction.create');

    Route::get('/escrow', [B2bOperationsController::class, 'escrowIndex'])->name('escrow.index');
    Route::post('/escrow/lock', [B2bOperationsController::class, 'lockEscrow'])->name('escrow.lock');
    Route::post('/escrow/release', [B2bOperationsController::class, 'releaseEscrow'])
        ->middleware('role:admin,procurement')
        ->name('escrow.release');
});
