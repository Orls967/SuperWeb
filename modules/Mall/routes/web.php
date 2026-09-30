<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Mall\Http\Controllers\LeaseController;
use Modules\Mall\Http\Controllers\PublicDirectoryController;
use Modules\Mall\Http\Controllers\SitePlanController;
use Modules\Mall\Http\Controllers\TenantController;

Route::middleware(['web'])->prefix('mall')->name('mall.')->group(function () {
    // Direktori Tenant Publik (bisa diakses tanpa login)
    Route::get('directory', [PublicDirectoryController::class, 'index'])->name('directory.index');

    // Fitur Manajemen Leasing & Properti (memerlukan autentikasi)
    Route::middleware(['auth'])->group(function () {
        // Site Plan & Denah Lantai
        Route::get('site-plan', [SitePlanController::class, 'index'])->name('site-plan.index');

        // Kontrak Sewa (Leasing)
        Route::post('leases/{lease}/activate', [LeaseController::class, 'activate'])->name('leases.activate');
        Route::post('leases/{lease}/terminate', [LeaseController::class, 'terminate'])->name('leases.terminate');
        Route::post('leases/{lease}/renew', [LeaseController::class, 'renew'])->name('leases.renew');
        Route::resource('leases', LeaseController::class)->except(['edit', 'update', 'destroy']);

        // Data Tenant Mitra
        Route::resource('tenants', TenantController::class)->except(['edit', 'update', 'destroy']);
    });
});
