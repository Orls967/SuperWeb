<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\International\Http\Controllers\InternationalController;
use Modules\International\Http\Controllers\InternationalTaxAndRoyaltyController;

Route::middleware(['web', 'auth', 'role:admin,auditor,legal,treasury'])->prefix('international')->name('international.')->group(function () {
    Route::get('/', [InternationalController::class, 'index'])->name('index');
    Route::get('/jvs', [InternationalController::class, 'index'])->name('jvs');

    Route::prefix('tax-royalty')->name('tax_royalty.')->group(function () {
        Route::get('/', [InternationalTaxAndRoyaltyController::class, 'index'])->name('index');
        Route::post('/royalty/{license}', [InternationalTaxAndRoyaltyController::class, 'calculateRoyalty'])->name('royalty.calculate');
        Route::post('/treaty-relief', [InternationalTaxAndRoyaltyController::class, 'applyTreatyRelief'])->name('treaty.relief');
        Route::post('/oem-tooling/{contract}', [InternationalTaxAndRoyaltyController::class, 'reconcileOemTooling'])->name('oem.tooling');
    });
});
