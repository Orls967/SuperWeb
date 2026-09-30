<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Mall\Http\Controllers\BillingController;
use Modules\Mall\Http\Controllers\LeaseController;
use Modules\Mall\Http\Controllers\PublicDirectoryController;
use Modules\Mall\Http\Controllers\SitePlanController;
use Modules\Mall\Http\Controllers\TenantController;
use Modules\Mall\Http\Controllers\TenantPortalController;
use Modules\Mall\Http\Controllers\UtilityController;

Route::middleware(['web'])->prefix('mall')->name('mall.')->group(function () {
    // Direktori Tenant Publik (bisa diakses tanpa login)
    Route::get('directory', [PublicDirectoryController::class, 'index'])->name('directory.index');

    // Fitur Mall & Tenant (memerlukan autentikasi)
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

        // Tagihan Bulanan, Invoicing & Piutang (Aging Receivable)
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
        Route::post('billing/apply-penalties', [BillingController::class, 'applyPenalties'])->name('billing.apply-penalties');
        Route::get('invoices/{id}', [BillingController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{id}/auto-debit', [BillingController::class, 'autoDebit'])->name('invoices.auto-debit');

        // Pencatatan Meteran Utilitas (Listrik & Air)
        Route::get('utilities', [UtilityController::class, 'index'])->name('utilities.index');
        Route::post('utilities/batch', [UtilityController::class, 'storeBatch'])->name('utilities.batch');

        // Portal Khusus Tenant Mall
        Route::get('portal', [TenantPortalController::class, 'index'])->name('portal.index');
        Route::get('portal/invoices/{id}', [TenantPortalController::class, 'showInvoice'])->name('portal.invoice');
        Route::post('portal/invoices/{id}/pay', [TenantPortalController::class, 'payInvoice'])->name('portal.pay');
        Route::get('portal/sales', [TenantPortalController::class, 'sales'])->name('portal.sales');
        Route::post('portal/sales', [TenantPortalController::class, 'storeSales'])->name('portal.sales.store');
        Route::post('portal/overtime', [TenantPortalController::class, 'requestOvertime'])->name('portal.overtime');
    });
});
