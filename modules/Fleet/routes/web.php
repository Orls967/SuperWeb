<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Fleet\Http\Controllers\FleetController;
use Modules\Fleet\Http\Controllers\FleetLeaseLifecycleController;

Route::middleware(['web', 'auth', 'role:admin,fleet_admin,lease_manager'])->prefix('fleet')->group(function () {
    Route::get('/', [FleetController::class, 'index'])->name('fleet.index');
    Route::post('/contracts', [FleetController::class, 'store'])->name('fleet.contracts.store');
    Route::post('/contracts/{contract}/amortize', [FleetController::class, 'amortize'])->name('fleet.contracts.amortize');
    Route::post('/contracts/{contract}/sla-claim', [FleetController::class, 'slaClaim'])->name('fleet.contracts.sla-claim');
    Route::post('/contracts/{contract}/return-unit', [FleetController::class, 'returnUnit'])->name('fleet.contracts.return-unit');

    // PSAK 73 Lease Capitalization & Lifecycle Deepening Routes
    Route::prefix('lifecycle')->name('fleet.lifecycle.')->group(function () {
        Route::get('/psak73-leases', [FleetLeaseLifecycleController::class, 'psak73Leases'])->name('psak73-leases');
        Route::get('/amortization-schedule', [FleetLeaseLifecycleController::class, 'amortizationSchedule'])->name('amortization-schedule');
        Route::get('/excess-mileage', [FleetLeaseLifecycleController::class, 'excessMileage'])->name('excess-mileage');
        Route::get('/tco-optimization', [FleetLeaseLifecycleController::class, 'tcoOptimization'])->name('tco-optimization');
        Route::post('/originate-psak73', [FleetLeaseLifecycleController::class, 'originatePsak73'])->name('originate-psak73');
        Route::post('/recognize-amortization', [FleetLeaseLifecycleController::class, 'recognizeAmortization'])->name('recognize-amortization');
        Route::post('/bill-excess', [FleetLeaseLifecycleController::class, 'billExcess'])->name('bill-excess');
        Route::post('/run-tco', [FleetLeaseLifecycleController::class, 'runTco'])->name('run-tco');
    });
});

Route::middleware(['web', 'auth'])->get('/oto/fleet', function (Request $request) {
    $user = $request->user();
    if (! $user->hasAnyRbacRole(['fleet_manager', 'admin', 'fleet_admin', 'lease_manager'])) {
        abort(403, 'Akses ditolak: Khusus peran manajemen armada.');
    }

    return app(FleetController::class)->index($request);
});
