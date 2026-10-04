<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Supplier\Http\Controllers\SupplierController;
use Modules\Supplier\Http\Controllers\SupplierPortalController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,procurement,party_manager'])
    ->prefix('suppliers')
    ->name('supplier.')
    ->group(function () {
        Route::get('/scan-risks', [SupplierController::class, 'scanRisks'])->name('scan-risks');
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::get('/create', [SupplierController::class, 'create'])->name('create');
        Route::post('/', [SupplierController::class, 'store'])->name('store');

        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::post('/{supplier}/qualifications', [SupplierController::class, 'submitQualification'])->name('qualifications.store');
        Route::post('/{supplier}/qualifications/approve', [SupplierController::class, 'approveQualification'])->name('qualifications.approve');
        Route::post('/{supplier}/transition', [SupplierController::class, 'transition'])->name('transition');
        Route::post('/{supplier}/certifications', [SupplierController::class, 'storeCertification'])->name('certifications.store');
        Route::post('/{supplier}/items', [SupplierController::class, 'storeItem'])->name('items.store');
        Route::post('/{supplier}/scorecards', [SupplierController::class, 'storeScorecard'])->name('scorecards.store');
    });

// 32.5 Portal Pemasok (role: supplier)
Route::middleware(['web', 'auth', 'verified', 'role:supplier,admin'])
    ->prefix('portal/suppliers')
    ->name('supplier.portal.')
    ->group(function () {
        Route::get('/', [SupplierPortalController::class, 'home'])->name('home');
        Route::post('/asn', [SupplierPortalController::class, 'storeAsn'])->name('asn.store');
        Route::post('/asn/{asn}/ship', [SupplierPortalController::class, 'markShipped'])->name('asn.ship');
        Route::post('/documents', [SupplierPortalController::class, 'uploadDocument'])->name('documents.store');
    });
