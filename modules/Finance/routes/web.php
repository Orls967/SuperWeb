<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\LoanController;

Route::middleware(['web', 'auth'])->prefix('pembiayaan')->name('finance.')->group(function () {
    // Read: loans index, simulation view, detail (admin, customer, treasury, auditor)
    Route::middleware('role:admin,customer,treasury,auditor')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('loans.index');
        Route::get('/simulasi/{product:slug}', [LoanController::class, 'simulate'])->name('loans.simulate');
        Route::get('/{loan:uuid}', [LoanController::class, 'show'])->name('loans.show');
    });

    // Mutations: quote, apply loan, pay installment, pay off, add collateral (admin, customer, treasury - auditor forbidden)
    Route::middleware('role:admin,customer,treasury')->group(function () {
        Route::post('/simulasi/quote', [LoanController::class, 'quote'])->name('loans.quote');
        Route::post('/ajukan/{product:slug}', [LoanController::class, 'store'])->name('loans.store');
        Route::post('/{loan:uuid}/bayar-cicilan', [LoanController::class, 'payInstallment'])->name('loans.payInstallment');
        Route::post('/{loan:uuid}/lunasi', [LoanController::class, 'payOff'])->name('loans.payOff');
        Route::post('/{loan:uuid}/tambah-kolateral', [LoanController::class, 'topUpCollateral'])->name('loans.topUpCollateral');
    });
});
