<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Venue\Http\Controllers\VenueArtistRoyaltyEscrowController;
use Modules\Venue\Http\Controllers\VenueController;
use Modules\Venue\Http\Controllers\VenueTableBottleServiceController;
use Modules\Venue\Http\Controllers\VenueTicketingAccessController;

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
        Route::get('/', [VenueTableBottleServiceController::class, 'tableBookings'])->name('index');
        Route::post('/reserve', [VenueTableBottleServiceController::class, 'reserveTable'])->name('reserve');
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
