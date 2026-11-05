<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Syariah\Http\Controllers\IjarahController;
use Modules\Syariah\Http\Controllers\MurabahahController;
use Modules\Syariah\Http\Controllers\SukukController;
use Modules\Syariah\Http\Controllers\WaqfController;
use Modules\Syariah\Http\Controllers\WealthController;
use Modules\Syariah\Http\Controllers\ZakatController;

// 1. Murabahah Operasional Petugas Pembiayaan (syariah_officer)
Route::middleware(['web', 'auth', 'role:admin,syariah_officer'])->prefix('syariah/murabahah')->name('syariah.murabahah.')->group(function () {
    Route::get('/', [MurabahahController::class, 'index'])->name('index');
    Route::post('/', [MurabahahController::class, 'propose'])->name('propose');
    Route::post('/{contract}/approve-officer', [MurabahahController::class, 'approveOfficer'])->name('approve-officer');
    Route::post('/{contract}/purchase-asset', [MurabahahController::class, 'purchaseAsset'])->name('purchase-asset');
    Route::post('/{contract}/sign', [MurabahahController::class, 'signContract'])->name('sign');
    Route::post('/{contract}/tazir', [MurabahahController::class, 'collectTazir'])->name('tazir');
});

// Pembayaran angsuran Murabahah (nasabah / officer)
Route::middleware(['web', 'auth', 'role:admin,syariah_officer,nasabah'])->prefix('syariah/murabahah')->name('syariah.murabahah.')->group(function () {
    Route::post('/{contract}/pay-installment', [MurabahahController::class, 'payInstallment'])->name('pay-installment');
});

// Reviu Kepatuhan Murabahah DPS (dps_reviewer)
Route::middleware(['web', 'auth', 'role:admin,dps_reviewer'])->prefix('syariah/review')->name('syariah.review.')->group(function () {
    Route::get('/', [MurabahahController::class, 'reviewIndex'])->name('index');
    Route::post('/{contract}/approve', [MurabahahController::class, 'reviewApprove'])->name('approve');
});

// Portal Nasabah Murabahah
Route::middleware(['web', 'auth', 'role:admin,syariah_officer,dps_reviewer,nasabah'])->prefix('syariah/portal')->name('syariah.portal.')->group(function () {
    Route::get('/', [MurabahahController::class, 'portalIndex'])->name('index');
    Route::get('/{contract}', [MurabahahController::class, 'portalShow'])->name('show');
});

// 2. Sukuk (Fase 162) - Petugas Syariah
Route::middleware(['web', 'auth', 'role:admin,syariah_officer'])->prefix('syariah/sukuk')->name('syariah.sukuk.')->group(function () {
    Route::get('/', [SukukController::class, 'index'])->name('index');
    Route::get('/export', [SukukController::class, 'exportCsv'])->name('export');
    Route::post('/propose', [SukukController::class, 'propose'])->name('propose');
    Route::get('/{id}', [SukukController::class, 'show'])->name('show');
    Route::post('/{id}/issue', [SukukController::class, 'issue'])->name('issue');
    Route::post('/{id}/distribute-coupon', [SukukController::class, 'distributeCoupon'])->name('distribute-coupon');
    Route::post('/{id}/redeem', [SukukController::class, 'redeem'])->name('redeem');
});

// Reviu Kepatuhan Sukuk DPS
Route::middleware(['web', 'auth', 'role:admin,shariah_board,dps_reviewer'])->prefix('syariah/sukuk')->name('syariah.sukuk.')->group(function () {
    Route::post('/{id}/dps-approve', [SukukController::class, 'dpsApprove'])->name('dps-approve');
});

// 3. Ijarah & IMBT (Fase 162) - Petugas Syariah
Route::middleware(['web', 'auth', 'role:admin,syariah_officer'])->prefix('syariah/ijarah')->name('syariah.ijarah.')->group(function () {
    Route::get('/', [IjarahController::class, 'index'])->name('index');
    Route::get('/export', [IjarahController::class, 'exportCsv'])->name('export');
    Route::post('/propose', [IjarahController::class, 'propose'])->name('propose');
    Route::get('/{id}', [IjarahController::class, 'show'])->name('show');
    Route::post('/{id}/activate', [IjarahController::class, 'activate'])->name('activate');
    Route::post('/{id}/exercise-imbt', [IjarahController::class, 'exerciseImbt'])->name('exercise-imbt');
});

// Reviu Kepatuhan Ijarah DPS
Route::middleware(['web', 'auth', 'role:admin,shariah_board,dps_reviewer'])->prefix('syariah/ijarah')->name('syariah.ijarah.')->group(function () {
    Route::post('/{id}/dps-approve', [IjarahController::class, 'dpsApprove'])->name('dps-approve');
});

// Pembayaran Sewa Ijarah (Nasabah / Officer)
Route::middleware(['web', 'auth', 'role:admin,syariah_officer,customer,nasabah'])->prefix('syariah/ijarah')->name('syariah.ijarah.')->group(function () {
    Route::post('/{id}/pay-period', [IjarahController::class, 'payPeriod'])->name('pay-period');
});

// 4. Wealth Syariah (Fase 162) - Penasihat Investasi
Route::middleware(['web', 'auth', 'role:admin,wealth_advisor'])->prefix('syariah/wealth')->name('syariah.wealth.')->group(function () {
    Route::get('/', [WealthController::class, 'index'])->name('index');
    Route::get('/export', [WealthController::class, 'exportCsv'])->name('export');
    Route::post('/instruments', [WealthController::class, 'registerInstrument'])->name('instruments.store');
});

// Portofolio & Transaksi Wealth (Nasabah & Advisor)
Route::middleware(['web', 'auth', 'role:admin,wealth_advisor,customer'])->prefix('syariah/wealth')->name('syariah.wealth.')->group(function () {
    Route::post('/portfolios', [WealthController::class, 'createPortfolio'])->name('portfolios.store');
    Route::get('/portfolios/{id}', [WealthController::class, 'showPortfolio'])->name('portfolios.show');
    Route::post('/orders', [WealthController::class, 'placeOrder'])->name('orders.store');
    Route::post('/purify-dividend', [WealthController::class, 'purifyDividend'])->name('purify-dividend');
});

// 5. Zakat Engine (Fase 162) - Amil
Route::middleware(['web', 'auth', 'role:admin,amil'])->prefix('syariah/zakat')->name('syariah.zakat.')->group(function () {
    Route::get('/', [ZakatController::class, 'index'])->name('index');
    Route::get('/export', [ZakatController::class, 'exportCsv'])->name('export');
    Route::post('/assess', [ZakatController::class, 'assess'])->name('assess');
    Route::post('/distribute', [ZakatController::class, 'distribute'])->name('distribute');
});

// Kalkulator & Sertifikat Zakat Publik / Nasabah
Route::middleware(['web', 'auth', 'role:admin,amil,customer,nasabah'])->prefix('syariah/zakat')->name('syariah.zakat.')->group(function () {
    Route::post('/calculate', [ZakatController::class, 'calculate'])->name('calculate');
    Route::get('/certificates/{id}', [ZakatController::class, 'showCertificate'])->name('certificates.show');
});

// 6. Wakaf Uang & Filantropi (Fase 162) - Nazhir
Route::middleware(['web', 'auth', 'role:admin,nazhir'])->prefix('syariah/waqf')->name('syariah.waqf.')->group(function () {
    Route::get('/', [WaqfController::class, 'index'])->name('index');
    Route::post('/receive-money', [WaqfController::class, 'receiveMoney'])->name('receive-money');
    Route::post('/{id}/withdraw-principal', [WaqfController::class, 'withdrawPrincipal'])->name('withdraw-principal');
    Route::post('/{id}/distribute-benefit', [WaqfController::class, 'distributeBenefit'])->name('distribute-benefit');
    Route::get('/certificates/{id}', [WaqfController::class, 'showCertificate'])->name('certificates.show');
});
