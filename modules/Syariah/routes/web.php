<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Syariah\Http\Controllers\MurabahahController;

// D19-2 Operasional Petugas Pembiayaan Syariah (syariah_officer)
Route::middleware(['web', 'auth', 'role:admin,syariah_officer'])->prefix('syariah/murabahah')->name('syariah.murabahah.')->group(function () {
    Route::get('/', [MurabahahController::class, 'index'])->name('index');
    Route::post('/', [MurabahahController::class, 'propose'])->name('propose');
    Route::post('/{contract}/approve-officer', [MurabahahController::class, 'approveOfficer'])->name('approve-officer');
    Route::post('/{contract}/purchase-asset', [MurabahahController::class, 'purchaseAsset'])->name('purchase-asset');
    Route::post('/{contract}/sign', [MurabahahController::class, 'signContract'])->name('sign');
    Route::post('/{contract}/tazir', [MurabahahController::class, 'collectTazir'])->name('tazir');
});

// Pembayaran angsuran dapat dilakukan oleh petugas maupun nasabah pemilik akad (Policy)
Route::middleware(['web', 'auth', 'role:admin,syariah_officer,nasabah'])->prefix('syariah/murabahah')->name('syariah.murabahah.')->group(function () {
    Route::post('/{contract}/pay-installment', [MurabahahController::class, 'payInstallment'])->name('pay-installment');
});

// D19-2 Reviu Kepatuhan Dewan Pengawas Syariah / DPS (dps_reviewer, four-eyes)
Route::middleware(['web', 'auth', 'role:admin,dps_reviewer'])->prefix('syariah/review')->name('syariah.review.')->group(function () {
    Route::get('/', [MurabahahController::class, 'reviewIndex'])->name('index');
    Route::post('/{contract}/approve', [MurabahahController::class, 'reviewApprove'])->name('approve');
});

// Portal Nasabah Syariah (nasabah hanya melihat akad miliknya sendiri, anti-IDOR via MurabahahContractPolicy)
Route::middleware(['web', 'auth', 'role:admin,syariah_officer,dps_reviewer,nasabah'])->prefix('syariah/portal')->name('syariah.portal.')->group(function () {
    Route::get('/', [MurabahahController::class, 'portalIndex'])->name('index');
    Route::get('/{contract}', [MurabahahController::class, 'portalShow'])->name('show');
});
