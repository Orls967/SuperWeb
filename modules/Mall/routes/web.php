<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Mall\Http\Controllers\BillingController;
use Modules\Mall\Http\Controllers\EventController;
use Modules\Mall\Http\Controllers\FacilityController;
use Modules\Mall\Http\Controllers\LeaseController;
use Modules\Mall\Http\Controllers\LoyaltyController;
use Modules\Mall\Http\Controllers\ParkingController;
use Modules\Mall\Http\Controllers\ParkingGateController;
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
        // Site Plan & Denah Lantai (Read-only: admin, mall_admin, auditor)
        Route::middleware(['role:admin,auditor,mall_admin'])->group(function () {
            Route::get('site-plan', [SitePlanController::class, 'index'])->name('site-plan.index');
        });

        // Kontrak Sewa (Leasing)
        Route::middleware(['role:admin,mall_admin'])->group(function () {
            Route::post('leases/{lease}/activate', [LeaseController::class, 'activate'])->name('leases.activate');
            Route::post('leases/{lease}/terminate', [LeaseController::class, 'terminate'])->name('leases.terminate');
            Route::post('leases/{lease}/renew', [LeaseController::class, 'renew'])->name('leases.renew');
            Route::get('leases/create', [LeaseController::class, 'create'])->name('leases.create');
            Route::post('leases', [LeaseController::class, 'store'])->name('leases.store');
        });
        Route::middleware(['role:admin,auditor,mall_admin'])->group(function () {
            Route::get('leases', [LeaseController::class, 'index'])->name('leases.index');
            Route::get('leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
        });

        // Data Tenant Mitra
        Route::middleware(['role:admin,mall_admin'])->group(function () {
            Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
            Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        });
        Route::middleware(['role:admin,auditor,mall_admin'])->group(function () {
            Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        });

        // Tagihan Bulanan, Invoicing & Piutang (Aging Receivable)
        Route::middleware(['role:admin,auditor,mall_admin'])->group(function () {
            Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
            Route::get('invoices/{id}', [BillingController::class, 'show'])->name('invoices.show');
        });
        Route::middleware(['role:admin,mall_admin'])->group(function () {
            Route::post('billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
            Route::post('billing/apply-penalties', [BillingController::class, 'applyPenalties'])->name('billing.apply-penalties');
            Route::post('invoices/{id}/auto-debit', [BillingController::class, 'autoDebit'])->name('invoices.auto-debit');
        });

        // Pencatatan Meteran Utilitas (Listrik & Air)
        Route::middleware(['role:admin,auditor,mall_admin,technician'])->group(function () {
            Route::get('utilities', [UtilityController::class, 'index'])->name('utilities.index');
        });
        Route::middleware(['role:admin,mall_admin,technician'])->group(function () {
            Route::post('utilities/batch', [UtilityController::class, 'storeBatch'])->name('utilities.batch');
        });

        // Parkir, Gate, Langganan Member & Footfall
        Route::middleware(['role:admin,auditor,customer,mall_admin,technician'])->group(function () {
            Route::get('parking/members', [ParkingController::class, 'members'])->name('parking.members');
        });
        Route::middleware(['role:admin,customer,mall_admin,technician'])->group(function () {
            Route::post('parking/members', [ParkingController::class, 'storeMember'])->name('parking.members.store');
        });
        Route::middleware(['role:admin,auditor,mall_admin,technician'])->group(function () {
            Route::get('parking', [ParkingController::class, 'index'])->name('parking.index');
            Route::get('parking/footfall', [ParkingController::class, 'footfall'])->name('parking.footfall');
            Route::get('parking/occupancy', [ParkingGateController::class, 'occupancy'])->name('parking.occupancy');
        });
        Route::middleware(['role:admin,mall_admin,technician'])->group(function () {
            Route::post('parking/validate', [ParkingController::class, 'validateTicket'])->name('parking.validate');
            Route::get('parking/gate/entry', [ParkingGateController::class, 'entry'])->name('parking.gate.entry');
            Route::post('parking/gate/entry', [ParkingGateController::class, 'checkIn'])->name('parking.gate.check-in');
            Route::get('parking/gate/exit', [ParkingGateController::class, 'exit'])->name('parking.gate.exit');
            Route::post('parking/gate/exit', [ParkingGateController::class, 'checkOut'])->name('parking.gate.check-out');
            Route::post('parking/gate/lost-ticket', [ParkingGateController::class, 'lostTicket'])->name('parking.gate.lost-ticket');
            Route::post('parking/gate/{ticketNumber}/settle', [ParkingGateController::class, 'settle'])->name('parking.gate.settle');
        });

        // Portal Khusus Tenant Mall
        Route::middleware(['role:admin,tenant'])->group(function () {
            Route::get('portal', [TenantPortalController::class, 'index'])->name('portal.index');
            Route::get('portal/invoices/{id}', [TenantPortalController::class, 'showInvoice'])->name('portal.invoice');
            Route::post('portal/invoices/{id}/pay', [TenantPortalController::class, 'payInvoice'])->name('portal.pay');
            Route::get('portal/sales', [TenantPortalController::class, 'sales'])->name('portal.sales');
            Route::post('portal/sales', [TenantPortalController::class, 'storeSales'])->name('portal.sales.store');
            Route::post('portal/overtime', [TenantPortalController::class, 'requestOvertime'])->name('portal.overtime');
        });

        // Loyalty Duta Points & Voucher Mall
        Route::middleware(['role:admin,auditor,customer,mall_admin'])->group(function () {
            Route::get('loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
        });
        Route::middleware(['role:admin,customer,mall_admin'])->group(function () {
            Route::post('loyalty/claim', [LoyaltyController::class, 'claimReceipt'])->name('loyalty.claim');
            Route::post('loyalty/redeem/{template}', [LoyaltyController::class, 'redeem'])->name('loyalty.redeem');
            Route::post('loyalty/use', [LoyaltyController::class, 'useVoucher'])->name('loyalty.use');
        });
        Route::middleware(['role:admin,mall_admin'])->group(function () {
            Route::post('loyalty/settle', [LoyaltyController::class, 'settle'])->name('loyalty.settle');
        });

        // Sewa Atrium & Event Mall
        Route::middleware(['role:admin,auditor,mall_admin'])->group(function () {
            Route::get('events', [EventController::class, 'index'])->name('events.index');
        });
        Route::middleware(['role:admin,mall_admin'])->group(function () {
            Route::post('events', [EventController::class, 'store'])->name('events.store');
        });

        // Facility Management & Work Orders
        Route::middleware(['role:admin,auditor,mall_admin,technician'])->group(function () {
            Route::get('facilities', [FacilityController::class, 'index'])->name('facilities.index');
        });
        Route::middleware(['role:admin,mall_admin,technician'])->group(function () {
            Route::post('facilities/generate-pm', [FacilityController::class, 'generatePm'])->name('facilities.generate-pm');
            Route::post('facilities/work-orders/{workOrder}/bill-tenant', [FacilityController::class, 'billToTenant'])->name('facilities.bill-tenant');
        });
    });
});
