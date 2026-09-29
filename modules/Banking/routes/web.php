<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Banking\Http\Controllers\AdminLedgerController;
use Modules\Banking\Http\Controllers\MutationController;
use Modules\Banking\Http\Controllers\TopUpController;
use Modules\Banking\Http\Controllers\TransferController;
use Modules\Banking\Http\Controllers\WalletController;

Route::middleware(['web', 'auth'])->group(function () {
    // Wallet & PIN
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/pin', [WalletController::class, 'setPin'])->name('wallet.pin.store');

    // Top Up
    Route::post('/wallet/topup', [TopUpController::class, 'store'])->name('wallet.topup.store');

    // Transfer
    Route::get('/wallet/transfer', [TransferController::class, 'index'])->name('wallet.transfer');
    Route::post('/wallet/transfer/lookup', [TransferController::class, 'lookup'])->name('wallet.transfer.lookup');
    Route::post('/wallet/transfer', [TransferController::class, 'store'])->name('wallet.transfer.store');

    // Mutasi
    Route::get('/wallet/mutasi', [MutationController::class, 'index'])->name('wallet.mutasi');
    Route::get('/wallet/mutasi/export', [MutationController::class, 'export'])->name('wallet.mutasi.export');

    // Admin Ledger
    Route::middleware(['role:admin'])->prefix('admin/ledger')->name('admin.ledger.')->group(function () {
        Route::get('/', [AdminLedgerController::class, 'index'])->name('index');
        Route::get('/transactions/{transaction}', [AdminLedgerController::class, 'show'])->name('show');
        Route::post('/accounts/{account}/freeze', [AdminLedgerController::class, 'freeze'])->name('freeze');
        Route::post('/accounts/{account}/adjust', [AdminLedgerController::class, 'adjust'])->name('adjust');
    });
});
