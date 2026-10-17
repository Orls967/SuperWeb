<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Venue\Http\Controllers\VenueController;

Route::middleware(['web', 'auth', 'role:admin,venue_manager,venue_staff'])->prefix('venue')->group(function () {
    Route::get('/', [VenueController::class, 'index'])->name('venue.index');
    Route::post('/tickets/{ticket}/scan', [VenueController::class, 'scanGate'])->name('venue.gate.scan');
});
