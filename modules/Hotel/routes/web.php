<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hotel\Http\Controllers\GuestStayPortalController;
use Modules\Hotel\Http\Controllers\HotelBanquetMiceController;
use Modules\Hotel\Http\Controllers\HotelBanquetProductionController;
use Modules\Hotel\Http\Controllers\HotelBanquetTimesharePackageController;
use Modules\Hotel\Http\Controllers\HotelCityLedgerBillingController;
use Modules\Hotel\Http\Controllers\HotelComplianceController;
use Modules\Hotel\Http\Controllers\HotelController;
use Modules\Hotel\Http\Controllers\HotelCorporatePortalController;
use Modules\Hotel\Http\Controllers\HotelDestinationBundleController;
use Modules\Hotel\Http\Controllers\HotelEnergyController;
use Modules\Hotel\Http\Controllers\HotelEngineeringController;
use Modules\Hotel\Http\Controllers\HotelFolioSplitController;
use Modules\Hotel\Http\Controllers\HotelFrontDeskController;
use Modules\Hotel\Http\Controllers\HotelGuestPortalController;
use Modules\Hotel\Http\Controllers\HotelHousekeepingController;
use Modules\Hotel\Http\Controllers\HotelHousekeepingMobileController;
use Modules\Hotel\Http\Controllers\HotelInventoryRateController;
use Modules\Hotel\Http\Controllers\HotelLoyaltyPassportController;
use Modules\Hotel\Http\Controllers\HotelNightAuditFolioController;
use Modules\Hotel\Http\Controllers\HotelNightAuditUiController;
use Modules\Hotel\Http\Controllers\HotelPackageEscrowAuditController;
use Modules\Hotel\Http\Controllers\HotelReservationUiController;
use Modules\Hotel\Http\Controllers\HotelSpaAppointmentController;
use Modules\Hotel\Http\Controllers\HotelTimeshareSecondaryMarketController;
use Modules\Hotel\Http\Controllers\HotelTimeshareSyndicationController;
use Modules\Hotel\Http\Controllers\HotelYieldAndOverbookingController;

Route::middleware(['web', 'auth', 'role:admin,hotel_admin,front_desk'])->prefix('hotel')->group(function () {
    Route::get('/', [HotelController::class, 'index'])->name('hotel.index');
    Route::post('/reservations/{reservation}/settle', [HotelController::class, 'settleFolio'])->name('hotel.reservations.settle');

    // Advanced PMS Routes (Fase 91-92)
    Route::prefix('pms')->name('hotel.pms.')->group(function () {
        Route::get('/reservations', [HotelYieldAndOverbookingController::class, 'reservations'])->name('reservations');
        Route::post('/reservations/book', [HotelYieldAndOverbookingController::class, 'bookRoom'])->name('reservations.book');

        Route::get('/yield-rates', [HotelYieldAndOverbookingController::class, 'yieldRates'])->name('yield-rates');
        Route::post('/yield-rates/calculate', [HotelYieldAndOverbookingController::class, 'calcRate'])->name('yield-rates.calculate');

        Route::get('/overbooking', [HotelYieldAndOverbookingController::class, 'overbookingWalk'])->name('overbooking');
        Route::post('/overbooking/walk', [HotelYieldAndOverbookingController::class, 'executeWalk'])->name('overbooking.walk');

        Route::get('/close-day', [HotelNightAuditFolioController::class, 'nightAudit'])->name('close-day');
        Route::post('/close-day/run', [HotelNightAuditFolioController::class, 'runNightAudit'])->name('close-day.run');

        Route::get('/split-folios', [HotelNightAuditFolioController::class, 'splitFolios'])->name('split-folios');
        Route::post('/split-folios/settle', [HotelNightAuditFolioController::class, 'settleSplitFolio'])->name('split-folios.settle');

        Route::get('/banquet', [HotelBanquetTimesharePackageController::class, 'banquetOrders'])->name('banquet');
        Route::post('/banquet/create', [HotelBanquetTimesharePackageController::class, 'createBanquetOrder'])->name('banquet.create');

        Route::get('/timeshare', [HotelBanquetTimesharePackageController::class, 'timeshare'])->name('timeshare');
        Route::post('/timeshare/mint', [HotelBanquetTimesharePackageController::class, 'mintTimeshare'])->name('timeshare.mint');

        Route::get('/destination-packages', [HotelBanquetTimesharePackageController::class, 'destinationPackages'])->name('destination-packages');
        Route::post('/destination-packages/settle', [HotelBanquetTimesharePackageController::class, 'settlePackage'])->name('destination-packages.settle');
    });
});

// Siklus Tamu - Front Office & Reservation
Route::middleware(['web', 'auth', 'role:admin,hotel_manager,front_office,reservation_agent,hotel_admin,front_desk'])->prefix('hotel')->group(function () {
    Route::get('/reservations', [HotelReservationUiController::class, 'index'])->name('hotel.reservations.index');
    Route::post('/reservations', [HotelReservationUiController::class, 'store'])->name('hotel.reservations.store');
});

// Siklus Tamu - Housekeeping Board & Status Updates
Route::middleware(['web', 'auth', 'role:admin,hotel_manager,front_office,housekeeping'])->prefix('hotel')->group(function () {
    Route::get('/housekeeping', [HotelHousekeepingController::class, 'index'])->name('hotel.housekeeping.index');
    Route::post('/housekeeping/{room}/status', [HotelHousekeepingController::class, 'updateStatus'])->name('hotel.housekeeping.update');
});

// Siklus Tamu - Close Day Operasional
Route::middleware(['web', 'auth', 'role:admin,hotel_manager,night_auditor'])->prefix('hotel')->group(function () {
    Route::get('/close-day', [HotelNightAuditUiController::class, 'index'])->name('hotel.close-day.index');
    Route::post('/close-day/run', [HotelNightAuditUiController::class, 'run'])->name('hotel.close-day.run');
});

// Siklus Tamu - Portal Tamu (Self-Service)
Route::middleware(['web', 'auth', 'role:guest,customer,admin'])->prefix('hotel')->group(function () {
    Route::get('/portal/guest', [HotelGuestPortalController::class, 'index'])->name('hotel.portal.guest');
});

// Siklus Tamu - Portal Korporat (City Ledger)
Route::middleware(['web', 'auth', 'role:corporate_client,admin'])->prefix('hotel')->group(function () {
    Route::get('/portal/corporate', [HotelCorporatePortalController::class, 'index'])->name('hotel.portal.corporate');
});

// Rute Fase 91 PMS, Central Reservation, Rate Management & Smart Room
Route::middleware(['web', 'auth'])->group(function (): void {
    // Front Desk
    Route::middleware(['role:admin,hotel_admin,front_office,front_desk'])->group(function (): void {
        Route::get('/hotel/front-desk', [HotelFrontDeskController::class, 'index'])->name('hotel.front_desk');
        Route::post('/hotel/front-desk/check-in', [HotelFrontDeskController::class, 'checkIn'])->name('hotel.front_desk.check_in');
        Route::post('/hotel/front-desk/walk', [HotelFrontDeskController::class, 'relocateWalk'])->name('hotel.front_desk.walk');
    });

    // Inventory & Rates Grid
    Route::middleware(['role:admin,hotel_admin,revenue_mgr'])->group(function (): void {
        Route::get('/hotel/inventory', [HotelInventoryRateController::class, 'inventoryGrid'])->name('hotel.inventory');
        Route::get('/hotel/rates', [HotelInventoryRateController::class, 'ratesView'])->name('hotel.rates');
        Route::post('/hotel/rates/publish', [HotelInventoryRateController::class, 'publishRates'])->name('hotel.rates.publish');
        Route::get('/hotel/rates/simulator', [HotelInventoryRateController::class, 'overbookingSimulator'])->name('hotel.rates.simulator');
    });

    // Housekeeping Mobile (375px)
    Route::middleware(['role:admin,hotel_admin,front_office,housekeeping'])->group(function (): void {
        Route::get('/hotel/housekeeping', [HotelHousekeepingMobileController::class, 'index'])->name('hotel.housekeeping.mobile');
        Route::post('/hotel/housekeeping/tasks/{taskId}/status', [HotelHousekeepingMobileController::class, 'updateStatus'])->name('hotel.housekeeping.tasks.status');
    });

    // Engineering Board & Maintenance
    Route::middleware(['role:admin,hotel_admin,engineering'])->group(function (): void {
        Route::get('/hotel/engineering', [HotelEngineeringController::class, 'index'])->name('hotel.engineering');
        Route::post('/hotel/engineering/work-orders', [HotelEngineeringController::class, 'createWorkOrder'])->name('hotel.engineering.work_orders');
        Route::post('/hotel/engineering/compensate', [HotelEngineeringController::class, 'resolveCompensation'])->name('hotel.engineering.compensate');
    });

    // APOA Foreign Guest Compliance
    Route::middleware(['role:admin,hotel_admin,hotel_gm,front_office'])->group(function (): void {
        Route::get('/hotel/compliance/apoa', [HotelComplianceController::class, 'apoaQueue'])->name('hotel.compliance.apoa');
        Route::post('/hotel/compliance/apoa/submit', [HotelComplianceController::class, 'submitApoaBatch'])->name('hotel.compliance.apoa.submit');
    });

    // Smart Room & Energy Twin
    Route::middleware(['role:admin,hotel_admin,hotel_gm'])->group(function (): void {
        Route::get('/hotel/energy', [HotelEnergyController::class, 'index'])->name('hotel.energy');
        Route::post('/hotel/energy/scan', [HotelEnergyController::class, 'triggerScan'])->name('hotel.energy.scan');
    });

    // Guest Stay Portal
    Route::middleware(['role:admin,customer,guest'])->group(function (): void {
        Route::get('/guest/stay', [GuestStayPortalController::class, 'stayDetails'])->name('guest.stay');
    });

    // Rute Fase 92: Folio Multi-Payer, Banquet, Timeshare & Destination Package
    Route::middleware(['role:admin,hotel_admin,front_office,front_desk'])->group(function (): void {
        Route::get('/hotel/folios/{folioId}/split', [HotelFolioSplitController::class, 'show'])->name('hotel.folio.split.show');
        Route::post('/hotel/folios/{folioId}/split/settle', [HotelFolioSplitController::class, 'settle'])->name('hotel.folio.split.settle');
        Route::get('/hotel/city-ledger', [HotelCityLedgerBillingController::class, 'index'])->name('hotel.city_ledger.index');
        Route::get('/hotel/city-ledger/{accountId}/aging', [HotelCityLedgerBillingController::class, 'agingSchedule'])->name('hotel.city_ledger.aging');
        Route::get('/hotel/spa/appointments', [HotelSpaAppointmentController::class, 'index'])->name('hotel.spa.appointments.index');
        Route::post('/hotel/spa/appointments/book', [HotelSpaAppointmentController::class, 'book'])->name('hotel.spa.appointments.book');
    });

    Route::middleware(['role:admin,hotel_admin,hotel_gm,sales_mgr'])->group(function (): void {
        Route::get('/hotel/banquets', [HotelBanquetMiceController::class, 'index'])->name('hotel.banquet.index');
        Route::post('/hotel/banquets/{contractId}/approve', [HotelBanquetMiceController::class, 'approve'])->name('hotel.banquet.approve');
        Route::get('/hotel/banquets/production-sheet', [HotelBanquetProductionController::class, 'index'])->name('hotel.banquet.production');
        Route::post('/hotel/banquets/{contractId}/explode-bom', [HotelBanquetProductionController::class, 'explodeBom'])->name('hotel.banquet.explode_bom');
        Route::get('/hotel/timeshares', [HotelTimeshareSyndicationController::class, 'index'])->name('hotel.timeshare.index');
        Route::post('/hotel/timeshares/{unitId}/schedule', [HotelTimeshareSyndicationController::class, 'schedule'])->name('hotel.timeshare.schedule');
        Route::post('/hotel/timeshares/{unitId}/distribute', [HotelTimeshareSyndicationController::class, 'distribute'])->name('hotel.timeshare.distribute');
        Route::get('/hotel/timeshares/secondary-market', [HotelTimeshareSecondaryMarketController::class, 'index'])->name('hotel.timeshare.secondary');
        Route::post('/hotel/timeshares/secondary-market/swap', [HotelTimeshareSecondaryMarketController::class, 'executeSwap'])->name('hotel.timeshare.swap');
        Route::get('/hotel/packages', [HotelDestinationBundleController::class, 'index'])->name('hotel.packages.index');
        Route::post('/hotel/packages/{packageId}/book', [HotelDestinationBundleController::class, 'book'])->name('hotel.packages.book');
        Route::post('/hotel/packages/{packageId}/release-milestone', [HotelDestinationBundleController::class, 'releaseMilestone'])->name('hotel.packages.release_milestone');
        Route::get('/hotel/packages/escrow-audit', [HotelPackageEscrowAuditController::class, 'index'])->name('hotel.packages.escrow_audit');
        Route::get('/hotel/packages/escrow-audit/{bookingId}', [HotelPackageEscrowAuditController::class, 'auditBooking'])->name('hotel.packages.audit_booking');
    });

    Route::middleware(['role:admin,hotel_admin,guest,customer'])->group(function (): void {
        Route::get('/hotel/loyalty/passport/{guestId}', [HotelLoyaltyPassportController::class, 'profile'])->name('hotel.loyalty.profile');
        Route::post('/hotel/loyalty/accrue/{guestId}', [HotelLoyaltyPassportController::class, 'accrue'])->name('hotel.loyalty.accrue');
        Route::post('/hotel/loyalty/score-churn/{guestId}', [HotelLoyaltyPassportController::class, 'scoreChurn'])->name('hotel.loyalty.score_churn');
    });
});
