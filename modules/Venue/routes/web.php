<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Venue\Http\Controllers\GateScanController;
use Modules\Venue\Http\Controllers\GuestTicketPortalController;
use Modules\Venue\Http\Controllers\VenueArtistRoyaltyEscrowController;
use Modules\Venue\Http\Controllers\VenueController;
use Modules\Venue\Http\Controllers\VenueEventController;
use Modules\Venue\Http\Controllers\VenuePermitController;
use Modules\Venue\Http\Controllers\VenueReportController;
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
});
