<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Venue\Http\Controllers\GateScanController;
use Modules\Venue\Http\Controllers\GuestTicketPortalController;
use Modules\Venue\Http\Controllers\VenueArtistRoyaltyEscrowController;
use Modules\Venue\Http\Controllers\VenueBiometricKioskController;
use Modules\Venue\Http\Controllers\VenueController;
use Modules\Venue\Http\Controllers\VenueCrowdSafetyController;
use Modules\Venue\Http\Controllers\VenueEconomyController;
use Modules\Venue\Http\Controllers\VenueEventController;
use Modules\Venue\Http\Controllers\VenueGuestExperienceController;
use Modules\Venue\Http\Controllers\VenuePermitController;
use Modules\Venue\Http\Controllers\VenueReportController;
use Modules\Venue\Http\Controllers\VenueSecondaryAdminController;
use Modules\Venue\Http\Controllers\VenueSecondaryMarketController;
use Modules\Venue\Http\Controllers\VenueTableBottleServiceController;
use Modules\Venue\Http\Controllers\VenueTableController;
use Modules\Venue\Http\Controllers\VenueTicketingAccessController;
use Modules\Venue\Http\Controllers\VenueZoneController;

// Mobile Gate Access Console (375px)
Route::middleware(['web', 'auth', 'role:venue_staff'])->get('/venue/gate', [GateScanController::class, 'index'])->name('venue.gate.mobile');
Route::middleware(['web', 'auth', 'role:venue_staff'])->post('/venue/gate/scan', [GateScanController::class, 'scan'])->name('venue.gate.scan-action');

// Guest Portal (Customer)
Route::middleware(['web', 'auth', 'role:admin,customer'])->prefix('guest/tickets')->name('guest.tickets.')->group(function () {
    Route::get('/', [GuestTicketPortalController::class, 'index'])->name('index');
    Route::get('/{ticket_uuid}', [GuestTicketPortalController::class, 'show'])->name('show');
});

// Crowd Safety Zones
Route::middleware(['web', 'auth', 'role:admin,crowd_safety'])->prefix('venue/zones')->name('venue.zones.')->group(function () {
    Route::get('/', [VenueZoneController::class, 'index'])->name('index');
    Route::get('/crowd-safety', [VenueZoneController::class, 'crowdSafety'])->name('crowd-safety');
    Route::post('/{zone}/lockdown', [VenueZoneController::class, 'toggleLockdown'])->name('lockdown');
    Route::post('/{zone}/weather-adjust', [VenueZoneController::class, 'adjustForWeather'])->name('weather-adjust');
});

// Compliance & Crowd Permits
Route::middleware(['web', 'auth', 'role:admin,compliance_officer,venue_manager'])->prefix('venue/permits')->name('venue.permits.')->group(function () {
    Route::get('/', [VenuePermitController::class, 'index'])->name('index');
    Route::post('/', [VenuePermitController::class, 'store'])->name('store');
});

// Reports & Financial Analytics
Route::middleware(['web', 'auth', 'role:admin,venue_manager'])->prefix('venue/reports')->name('venue.reports.')->group(function () {
    Route::get('/', [VenueReportController::class, 'index'])->name('index');
    Route::get('/export', [VenueReportController::class, 'exportCsv'])->name('export');
});

// Events & Ticketing Lifecycle
Route::middleware(['web', 'auth', 'role:admin,venue_manager'])->prefix('venue/events')->name('venue.events.')->group(function () {
    Route::get('/', [VenueEventController::class, 'index'])->name('index');
    Route::get('/create', [VenueEventController::class, 'create'])->name('create');
    Route::post('/', [VenueEventController::class, 'store'])->name('store');
    Route::get('/{event}', [VenueEventController::class, 'show'])->name('show');
    Route::post('/{event}/close', [VenueEventController::class, 'closeEvent'])->name('close');
    Route::get('/{event}/simulator', [VenueEventController::class, 'simulator'])->name('simulator');
});

// Operational Venue Group (Existing compatibility & VIP Tables)
Route::middleware(['web', 'auth', 'role:admin,venue_manager,venue_staff'])->prefix('venue')->group(function () {
    Route::get('/', [VenueController::class, 'index'])->name('venue.index');
    Route::post('/tickets/{ticket}/scan', [VenueController::class, 'scanGate'])->name('venue.gate.scan');

    // Ticketing & Gate Access Routes
    Route::prefix('ticketing')->name('venue.ticketing.')->group(function () {
        Route::get('/', [VenueTicketingAccessController::class, 'ticketing'])->name('index');
        Route::post('/issue', [VenueTicketingAccessController::class, 'issueTicket'])->name('issue');
        Route::get('/gate', [VenueTicketingAccessController::class, 'gateAccess'])->name('gate');
        Route::post('/gate/scan', [VenueTicketingAccessController::class, 'scanGate'])->name('gate.scan');
    });

    // VIP Tables, Bottle Service & Liquor Telemetry
    Route::prefix('tables')->name('venue.tables.')->group(function () {
        Route::get('/', [VenueTableController::class, 'index'])->name('index');
        Route::post('/reserve', [VenueTableController::class, 'reserve'])->name('reserve');
        Route::post('/{booking}/settle', [VenueTableController::class, 'settle'])->name('settle');
        Route::post('/{booking}/forfeit', [VenueTableController::class, 'forfeit'])->name('forfeit');
        Route::get('/surge', [VenueTableBottleServiceController::class, 'bottleSurge'])->name('surge');
        Route::post('/surge/calculate', [VenueTableBottleServiceController::class, 'calcSurge'])->name('surge.calculate');
        Route::get('/liquor-audit', [VenueTableBottleServiceController::class, 'liquorAudit'])->name('liquor-audit');
        Route::post('/liquor-audit/reconcile', [VenueTableBottleServiceController::class, 'reconcileSpout'])->name('liquor-audit.reconcile');
    });

    // Artist Escrow & Creator Streaming Royalties
    Route::prefix('artist')->name('venue.artist.')->group(function () {
        Route::get('/', [VenueArtistRoyaltyEscrowController::class, 'artistEscrow'])->name('index');
        Route::post('/settle', [VenueArtistRoyaltyEscrowController::class, 'settleArtist'])->name('settle');
        Route::get('/royalties', [VenueArtistRoyaltyEscrowController::class, 'creatorRoyalties'])->name('royalties');
        Route::post('/royalties/accrue', [VenueArtistRoyaltyEscrowController::class, 'accrueRoyalty'])->name('royalties.accrue');
        Route::post('/royalties/release', [VenueArtistRoyaltyEscrowController::class, 'releaseRoyalties'])->name('royalties.release');
    });

    // 90.1 - 90.8 Venue Night Economics, Membership, Festival & Sponsor
    Route::prefix('economy')->name('venue.economy.')->group(function () {
        Route::get('/pnl', [VenueEconomyController::class, 'pnlReport'])->name('pnl');
        Route::get('/artists', [VenueEconomyController::class, 'artistStatement'])->name('artists');
        Route::post('/artists/settle', [VenueEconomyController::class, 'settleArtist'])->name('artists.settle');
        Route::get('/members', [VenueEconomyController::class, 'memberProfile'])->name('members');
        Route::post('/members/evaluate', [VenueEconomyController::class, 'evaluateMembership'])->name('members.evaluate');
        Route::get('/bundles', [VenueEconomyController::class, 'festivalBundles'])->name('bundles');
        Route::post('/bundles/allocate', [VenueEconomyController::class, 'allocateBundle'])->name('bundles.allocate');
        Route::get('/sponsors', [VenueEconomyController::class, 'sponsorReport'])->name('sponsors');
        Route::post('/sponsors/milestones/valuate', [VenueEconomyController::class, 'valuateSponsorMilestone'])->name('sponsors.milestones.valuate');
        Route::get('/bar', [VenueEconomyController::class, 'barShift'])->name('bar');
        Route::post('/bar/audit', [VenueEconomyController::class, 'auditBarShift'])->name('bar.audit');
    });

    // Direct Action Endpoints for API/Post JSON compatibility
    Route::post('/bar-shifts/audit', [VenueEconomyController::class, 'auditBarShift'])->name('venue.bar-shifts.audit');
});

// 116.1 - 116.7 Venue Secondary Ticket Market, Dynamic Bundling & Waitlist
Route::middleware(['web', 'auth', 'role:admin,venue_manager,venue_staff,customer'])->prefix('venue/secondary')->name('venue.secondary.')->group(function () {
    Route::get('/marketplace', [VenueSecondaryMarketController::class, 'marketplace'])->name('marketplace');
    Route::get('/sell', [VenueSecondaryMarketController::class, 'sell'])->name('sell');
    Route::post('/sell', [VenueSecondaryMarketController::class, 'storeListing'])->name('sell.store');
    Route::get('/orders', [VenueSecondaryMarketController::class, 'orders'])->name('orders');
    Route::get('/bundles', [VenueSecondaryMarketController::class, 'bundles'])->name('bundles');
    Route::post('/bundles', [VenueSecondaryMarketController::class, 'storeBundle'])->name('bundles.store');
    Route::get('/waitlist', [VenueSecondaryMarketController::class, 'waitlist'])->name('waitlist');
    Route::post('/waitlist/claim', [VenueSecondaryMarketController::class, 'claimWaitlist'])->name('waitlist.claim');
});

Route::middleware(['web', 'auth', 'role:admin,venue_manager,venue_staff'])->prefix('venue/secondary')->name('venue.secondary.')->group(function () {
    Route::get('/reports', [VenueSecondaryMarketController::class, 'reports'])->name('reports');
    Route::post('/admin/blacklist', [VenueSecondaryAdminController::class, 'blacklist'])->name('admin.blacklist');
    Route::post('/admin/reconcile', [VenueSecondaryAdminController::class, 'reconcile'])->name('admin.reconcile');
});

// 117.1 - 117.7 Crowd Safety, Biometric Access & Guest Experience
Route::middleware(['web', 'auth', 'role:admin,venue_manager,crowd_safety'])->prefix('venue/crowd')->name('venue.crowd.')->group(function () {
    Route::get('/dashboard', [VenueCrowdSafetyController::class, 'dashboard'])->name('dashboard');
    Route::get('/heatmap-data', [VenueCrowdSafetyController::class, 'heatmapJson'])->name('heatmap-data');
    Route::post('/emergency', [VenueCrowdSafetyController::class, 'triggerEmergency'])->name('emergency');
    Route::post('/incidents/{incident}/resolve', [VenueCrowdSafetyController::class, 'resolveEmergency'])->name('incidents.resolve');
});

Route::middleware(['web', 'auth', 'role:admin,venue_manager,venue_staff,customer'])->prefix('venue/biometrics')->name('venue.biometrics.')->group(function () {
    Route::get('/kiosk', [VenueBiometricKioskController::class, 'kiosk'])->name('kiosk');
    Route::get('/enrolment', [VenueBiometricKioskController::class, 'enrolment'])->name('enrolment');
    Route::post('/enrol', [VenueBiometricKioskController::class, 'processEnrol'])->name('enrol');
    Route::post('/scan', [VenueBiometricKioskController::class, 'scanGate'])->name('scan');
    Route::post('/revoke', [VenueBiometricKioskController::class, 'revokeConsent'])->name('revoke');
});

Route::middleware(['web', 'auth', 'role:admin,venue_manager,venue_staff,customer'])->prefix('venue/experience')->name('venue.experience.')->group(function () {
    Route::get('/wps', [VenueGuestExperienceController::class, 'wpsDashboard'])->name('wps');
    Route::get('/promos', [VenueGuestExperienceController::class, 'promosDashboard'])->name('promos');
    Route::post('/promos/dispatch', [VenueGuestExperienceController::class, 'dispatchOffer'])->name('promos.dispatch');
    Route::post('/reinforce', [VenueGuestExperienceController::class, 'requestBartenderReinforcement'])->name('reinforce');
});
